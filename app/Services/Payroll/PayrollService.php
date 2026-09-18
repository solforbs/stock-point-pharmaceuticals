<?php

namespace App\Services\Payroll;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\NumberSequence;
use App\Models\PayrollBand;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Services\Finance\JournalPoster;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InvalidPayrollStatusException extends \RuntimeException {}

/**
 * Part 21.16 — a payroll run: DRAFT → COMPUTED (every active employee of
 * the branch, with the bands in force on the period end stamped onto the
 * run) → APPROVED → POSTED (Part 12.3 journal) → PAID (net pay leaves the
 * bank). Re-computing a run always uses the snapshot it was first computed
 * with, so an old payroll reproduces its original figures exactly.
 */
class PayrollService
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
        private readonly JournalPoster $journalPoster,
    ) {}

    public function open(string $organisationId, string $branchId, int $year, int $month, int $userId): PayrollRun
    {
        if (PayrollRun::where('branch_id', $branchId)->where('period_year', $year)->where('period_month', $month)->exists()) {
            throw new \DomainException(sprintf('A payroll run for %04d-%02d already exists for this branch.', $year, $month));
        }

        $run = PayrollRun::create([
            'organisation_id' => $organisationId,
            'branch_id' => $branchId,
            'doc_number' => NumberSequence::next($organisationId, 'PAYROLL', $branchId, 'PAY'),
            'period_year' => $year,
            'period_month' => $month,
            'status' => 'DRAFT',
            'created_by' => $userId,
        ]);

        AuditLog::record('PAYROLL_OPENED', 'payroll_run', $run->id, ['user_id' => $userId, 'branch_id' => $branchId, 'reference' => $run->doc_number]);

        return $run;
    }

    /**
     * @param  array<string, array{allowances?: string, overtime?: string, pension_contribution?: string, other_deductions?: string}>  $inputs  keyed by employee id
     */
    public function compute(PayrollRun $run, int $userId, array $inputs = []): PayrollRun
    {
        $this->assertStatus($run, ['DRAFT', 'COMPUTED']);

        return DB::transaction(function () use ($run, $userId, $inputs) {
            $asOf = Carbon::create($run->period_year, $run->period_month, 1)->endOfMonth()->toDateString();
            $bands = $run->bands_snapshot_json ?: PayrollBand::inForce($run->organisation_id, $asOf);
            if ($bands === []) {
                throw new \DomainException("No statutory bands are in force on {$asOf}; seed the PAYE, NSSF, SHIF and housing levy tables first.");
            }

            $run->lines()->delete();
            $employees = Employee::where('organisation_id', $run->organisation_id)->where('branch_id', $run->branch_id)->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('date_left')->orWhereDate('date_left', '>=', Carbon::create($run->period_year, $run->period_month, 1)->toDateString()))
                ->orderBy('employee_no')->get();

            $totals = array_fill_keys(['gross', 'paye', 'nssf_employee', 'nssf_employer', 'shif', 'housing_levy_employee', 'housing_levy_employer', 'other_deductions', 'net'], '0.0000');
            foreach ($employees as $employee) {
                $in = $inputs[$employee->id] ?? [];
                $figures = $this->calculator->compute([
                    'basic' => (string) $employee->basic_salary,
                    'allowances' => (string) ($in['allowances'] ?? $employee->regular_allowances),
                    'overtime' => (string) ($in['overtime'] ?? '0'),
                    'pension_contribution' => (string) ($in['pension_contribution'] ?? $employee->pension_contribution),
                    'other_deductions' => (string) ($in['other_deductions'] ?? '0'),
                ], $bands);

                PayrollRunLine::create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'breakdown_json' => $figures['breakdown']]
                    + collect($figures)->only(['basic', 'allowances', 'overtime', 'gross', 'pension_contribution', 'taxable', 'paye', 'nssf_employee', 'nssf_employer', 'shif', 'housing_levy_employee', 'housing_levy_employer', 'other_deductions', 'net'])->all());

                foreach ($totals as $key => $value) {
                    $totals[$key] = bcadd($value, $figures[$key], 4);
                }
            }

            $run->update([
                'status' => 'COMPUTED', 'bands_snapshot_json' => $bands, 'bands_as_of' => $asOf, 'computed_at' => now(),
                'total_gross' => $totals['gross'], 'total_paye' => $totals['paye'], 'total_nssf_employee' => $totals['nssf_employee'], 'total_nssf_employer' => $totals['nssf_employer'],
                'total_shif' => $totals['shif'], 'total_housing_levy_employee' => $totals['housing_levy_employee'], 'total_housing_levy_employer' => $totals['housing_levy_employer'],
                'total_other_deductions' => $totals['other_deductions'], 'total_net' => $totals['net'],
            ]);

            AuditLog::record('PAYROLL_COMPUTED', 'payroll_run', $run->id, [
                'user_id' => $userId, 'branch_id' => $run->branch_id, 'reference' => $run->doc_number,
                'after_json' => ['employees' => $employees->count(), 'total_gross' => $totals['gross'], 'total_net' => $totals['net'], 'bands_as_of' => $asOf],
            ]);

            return $run->fresh(['lines']);
        });
    }

    public function approve(PayrollRun $run, int $approverId): PayrollRun
    {
        $this->assertStatus($run, ['COMPUTED']);
        if ((int) $run->created_by === $approverId) {
            throw new \DomainException('Payroll must be approved by someone other than the person who prepared it (Part 18.3 separation of duties).');
        }
        $run->update(['status' => 'APPROVED', 'approved_by' => $approverId, 'approved_at' => now()]);
        AuditLog::record('PAYROLL_APPROVED', 'payroll_run', $run->id, ['user_id' => $approverId, 'branch_id' => $run->branch_id, 'reference' => $run->doc_number]);

        return $run->fresh(['lines']);
    }

    /** Part 12.3 — Dr Salaries expense / Cr PAYE, NSSF, SHIF, housing levy and net pay payable. */
    public function post(PayrollRun $run, int $userId): PayrollRun
    {
        $this->assertStatus($run, ['APPROVED']);

        return DB::transaction(function () use ($run, $userId) {
            $employerCost = bcadd((string) $run->total_nssf_employer, (string) $run->total_housing_levy_employer, 4);
            $lines = [
                ['account_role' => 'SALARIES_EXPENSE', 'debit' => (string) $run->total_gross, 'narration' => "Payroll {$run->periodLabel()} — gross pay"],
            ];
            if (bccomp($employerCost, '0', 4) > 0) {
                $lines[] = ['account_role' => 'SALARIES_EXPENSE', 'debit' => $employerCost, 'narration' => "Payroll {$run->periodLabel()} — employer NSSF and housing levy"];
            }
            foreach ([
                ['PAYE_PAYABLE', (string) $run->total_paye, 'PAYE'],
                ['NSSF_PAYABLE', bcadd((string) $run->total_nssf_employee, (string) $run->total_nssf_employer, 4), 'NSSF (employee + employer)'],
                ['SHIF_PAYABLE', (string) $run->total_shif, 'SHIF'],
                ['HOUSING_LEVY_PAYABLE', bcadd((string) $run->total_housing_levy_employee, (string) $run->total_housing_levy_employer, 4), 'Housing levy (employee + employer)'],
                ['OTHER_DEDUCTIONS_PAYABLE', (string) $run->total_other_deductions, 'Other deductions'],
                ['NET_PAY_PAYABLE', (string) $run->total_net, 'Net pay'],
            ] as [$role, $amount, $label]) {
                if (bccomp($amount, '0', 4) > 0) {
                    $lines[] = ['account_role' => $role, 'credit' => $amount, 'narration' => "Payroll {$run->periodLabel()} — {$label}"];
                }
            }

            $journal = $this->journalPoster->post([
                'organisation_id' => $run->organisation_id,
                'branch_id' => $run->branch_id,
                'entry_date' => now(),
                'source_doc_type' => 'payroll_run',
                'source_doc_id' => $run->id,
                'narration' => "Payroll {$run->doc_number} ({$run->periodLabel()})",
                'posted_by' => $userId,
            ], $lines);

            $run->update(['status' => 'POSTED', 'posted_by' => $userId, 'posted_at' => now(), 'journal_id' => $journal->id]);
            AuditLog::record('PAYROLL_POSTED', 'payroll_run', $run->id, ['user_id' => $userId, 'branch_id' => $run->branch_id, 'reference' => $run->doc_number, 'after_json' => ['journal' => $journal->doc_number]]);

            return $run->fresh(['lines']);
        });
    }

    /** Net pay leaves the bank: Dr Net pay payable / Cr Bank. */
    public function pay(PayrollRun $run, int $userId, ?string $reference = null): PayrollRun
    {
        $this->assertStatus($run, ['POSTED']);

        return DB::transaction(function () use ($run, $userId, $reference) {
            $journal = $this->journalPoster->post([
                'organisation_id' => $run->organisation_id,
                'branch_id' => $run->branch_id,
                'entry_date' => now(),
                'source_doc_type' => 'payroll_payment',
                'source_doc_id' => $run->id,
                'narration' => "Net salaries {$run->periodLabel()}".($reference ? " ({$reference})" : ''),
                'posted_by' => $userId,
            ], [
                ['account_role' => 'NET_PAY_PAYABLE', 'debit' => (string) $run->total_net, 'narration' => "Net pay {$run->periodLabel()}"],
                ['account_role' => 'BANK', 'credit' => (string) $run->total_net, 'narration' => "Net pay {$run->periodLabel()}"],
            ]);

            $run->update(['status' => 'PAID', 'paid_at' => now(), 'payment_journal_id' => $journal->id]);
            AuditLog::record('PAYROLL_PAID', 'payroll_run', $run->id, ['user_id' => $userId, 'branch_id' => $run->branch_id, 'reference' => $run->doc_number, 'after_json' => ['journal' => $journal->doc_number, 'bank_reference' => $reference]]);

            return $run->fresh(['lines']);
        });
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertStatus(PayrollRun $run, array $expected): void
    {
        if (! in_array($run->status, $expected, true)) {
            throw new InvalidPayrollStatusException("Payroll run {$run->doc_number} is {$run->status}, not ".implode('/', $expected).'.');
        }
    }
}
