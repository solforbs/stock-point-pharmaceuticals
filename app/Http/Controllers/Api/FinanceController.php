<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\ChartOfAccount;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Finance\JournalPoster;
use App\Services\Finance\NoOpenPeriodException;
use App\Services\Finance\UnbalancedJournalException;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends ApiController
{
    /** GET /api/finance/trial-balance — derived from posted journals only (Part 20.1). */
    public function trialBalance(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'report.financial.view');
        $filters = $request->validate(['as_of' => ['nullable', 'date']]);
        $asOf = $filters['as_of'] ?? now()->toDateString();

        $rows = DB::table('chart_of_accounts as a')
            ->where('a.organisation_id', $this->organisationId($request))
            ->leftJoin('journal_entry_lines as l', 'l.account_id', '=', 'a.id')
            ->leftJoin('journal_entries as j', function ($join) use ($asOf) {
                $join->on('j.id', '=', 'l.journal_id')->whereDate('j.entry_date', '<=', $asOf);
            })
            ->groupBy('a.id', 'a.code', 'a.name', 'a.account_type', 'a.system_role')
            ->selectRaw('a.code, a.name, a.account_type, a.system_role, COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.debit_amount END), 0) as debit, COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.credit_amount END), 0) as credit')
            ->orderBy('a.code')
            ->get()
            ->map(fn ($r) => [
                'code' => $r->code, 'name' => $r->name, 'account_type' => $r->account_type, 'system_role' => $r->system_role,
                'debit' => number_format((float) $r->debit, 4, '.', ''), 'credit' => number_format((float) $r->credit, 4, '.', ''),
                'net' => number_format((float) $r->debit - (float) $r->credit, 4, '.', ''),
            ]);

        $totalDebit = $rows->reduce(fn ($c, $r) => bcadd($c, $r['debit'], 4), '0.0000');
        $totalCredit = $rows->reduce(fn ($c, $r) => bcadd($c, $r['credit'], 4), '0.0000');

        return response()->json([
            'as_of' => $asOf,
            'accounts' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced' => bccomp($totalDebit, $totalCredit, 4) === 0,
        ]);
    }

    /** GET /api/finance/chart-of-accounts — every account with its posted balance (Dr − Cr), Part 12.1. */
    public function chartOfAccounts(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'report.financial.view');
        $organisationId = $this->organisationId($request);

        $balances = DB::table('journal_entry_lines as l')
            ->join('journal_entries as j', 'j.id', '=', 'l.journal_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('a.organisation_id', $organisationId)->whereNotNull('j.posted_at')
            ->selectRaw('l.account_id, SUM(l.debit_amount) - SUM(l.credit_amount) as balance')
            ->groupBy('l.account_id')->pluck('balance', 'account_id');

        $accounts = ChartOfAccount::where('organisation_id', $organisationId)->orderBy('code')->get()
            ->map(fn (ChartOfAccount $a) => $a->only(['id', 'code', 'name', 'account_type', 'parent_id', 'is_postable', 'system_role', 'currency', 'is_active']) + [
                'balance' => number_format((float) ($balances[$a->id] ?? 0), 4, '.', ''),
            ]);

        return response()->json(['data' => $accounts->values()]);
    }

    public function journals(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'journal.post');

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'source_doc_type' => ['nullable', 'string', 'max:40'],
            'period_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort_by' => ['nullable', 'string', 'in:doc_number,entry_date,posted_at'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $sortBy = $filters['sort_by'] ?? 'posted_at';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc');

        return response()->json(
            JournalEntry::where('organisation_id', $this->organisationId($request))
                ->when($filters['q'] ?? null, function ($query, $term) {
                    $query->where(function ($sub) use ($term) {
                        $sub->where('doc_number', 'like', "%{$term}%")
                            ->orWhere('narration', 'like', "%{$term}%");
                    });
                })
                ->when($filters['source_doc_type'] ?? null, fn ($q, $v) => $q->where('source_doc_type', $v))
                ->when($filters['period_id'] ?? null, fn ($q, $v) => $q->where('period_id', $v))
                ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('entry_date', '>=', $v))
                ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('entry_date', '<=', $v))
                ->with('lines.account:id,code,name,system_role')
                ->orderBy($sortBy, $sortDir)
                ->paginate($filters['per_page'] ?? 25)
        );
    }

    public function showJournal(Request $request, string $journal): JsonResponse
    {
        $this->requirePermission($request, 'journal.post');

        $entry = JournalEntry::where('organisation_id', $this->organisationId($request))
            ->with('lines.account:id,code,name,system_role')
            ->findOrFail($journal);

        return response()->json($entry);
    }

    public function storeJournal(Request $request, JournalPoster $poster): JsonResponse
    {
        $this->requirePermission($request, 'journal.post');

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'narration' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'uuid', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.narration' => ['nullable', 'string', 'max:255'],
            'lines.*.branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
        ]);

        $organisationId = $this->organisationId($request);
        $branchId = $validated['branch_id'] ?? $this->branchId($request);

        $accountIds = collect($validated['lines'])->pluck('account_id')->unique();
        $accounts = ChartOfAccount::where('organisation_id', $organisationId)
            ->whereIn('id', $accountIds)
            ->get()
            ->keyBy('id');

        if ($accounts->count() !== $accountIds->count()) {
            return $this->error('INVALID_ACCOUNT', 'One or more accounts do not belong to this organisation.', 422);
        }

        foreach ($accounts as $account) {
            if (! $account->is_postable) {
                return $this->error('NON_POSTABLE_ACCOUNT', "Account {$account->code} ({$account->name}) is a summary/header account and cannot be posted to.", 422);
            }
            if (! $account->is_active) {
                return $this->error('INACTIVE_ACCOUNT', "Account {$account->code} ({$account->name}) is inactive.", 422);
            }
        }

        $totalDebit = '0.0000';
        $totalCredit = '0.0000';
        $normalizedLines = [];

        foreach ($validated['lines'] as $index => $line) {
            $debit = number_format((float) ($line['debit'] ?? 0), 4, '.', '');
            $credit = number_format((float) ($line['credit'] ?? 0), 4, '.', '');
            $hasDebit = bccomp($debit, '0.0000', 4) > 0;
            $hasCredit = bccomp($credit, '0.0000', 4) > 0;

            if ($hasDebit && $hasCredit) {
                return $this->error('INVALID_LINE', 'Line '.($index + 1).' cannot have both debit and credit amounts.', 422);
            }
            if (! $hasDebit && ! $hasCredit) {
                return $this->error('INVALID_LINE', 'Line '.($index + 1).' must specify either a debit or credit amount.', 422);
            }

            $totalDebit = bcadd($totalDebit, $debit, 4);
            $totalCredit = bcadd($totalCredit, $credit, 4);

            $normalizedLines[] = [
                'account_id' => $line['account_id'],
                'debit' => $debit,
                'credit' => $credit,
                'branch_id' => $line['branch_id'] ?? $branchId,
                'narration' => $line['narration'] ?? null,
            ];
        }

        if (bccomp($totalDebit, $totalCredit, 4) !== 0) {
            return $this->error('UNBALANCED_JOURNAL', "Total debits ({$totalDebit}) do not equal total credits ({$totalCredit}).", 422);
        }

        try {
            $journal = $poster->post([
                'organisation_id' => $organisationId,
                'branch_id' => $branchId,
                'entry_date' => Carbon::parse($validated['entry_date']),
                'source_doc_type' => 'manual',
                'source_doc_id' => null,
                'narration' => $validated['narration'],
                'posted_by' => $request->user()->id,
            ], $normalizedLines);
        } catch (NoOpenPeriodException $e) {
            return $this->error('NO_OPEN_PERIOD', $e->getMessage(), 422);
        } catch (UnbalancedJournalException $e) {
            return $this->error('UNBALANCED_JOURNAL', $e->getMessage(), 422);
        }

        AuditLog::record('MANUAL_JOURNAL_POSTED', 'journal_entry', $journal->id, [
            'doc_number' => $journal->doc_number,
            'amount' => $totalDebit,
            'lines_count' => count($validated['lines']),
        ]);

        return response()->json($journal->load('lines.account:id,code,name,system_role'), 201);
    }

    public function reverseJournal(Request $request, string $journal, JournalPoster $poster): JsonResponse
    {
        $this->requirePermission($request, 'journal.reverse');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $entry = JournalEntry::where('organisation_id', $this->organisationId($request))
            ->with('lines')
            ->findOrFail($journal);

        if ($entry->reverses_journal_id !== null) {
            return $this->error('ALREADY_REVERSAL', 'This journal is already a reversal entry and cannot be reversed again.', 422);
        }

        $existingReversal = JournalEntry::where('reverses_journal_id', $entry->id)->first();
        if ($existingReversal) {
            return $this->error('ALREADY_REVERSED', "This journal has already been reversed by {$existingReversal->doc_number}.", 422);
        }

        $reversedLines = $entry->lines->map(fn (JournalEntryLine $l) => [
            'account_id' => $l->account_id,
            'debit' => (string) $l->credit_amount,
            'credit' => (string) $l->debit_amount,
            'branch_id' => $l->branch_id,
            'partner_type' => $l->partner_type,
            'partner_id' => $l->partner_id,
            'tax_code_id' => $l->tax_code_id,
            'narration' => "Reversal: {$l->narration}",
        ])->all();

        try {
            $reversal = $poster->post([
                'organisation_id' => $entry->organisation_id,
                'branch_id' => $entry->branch_id,
                'entry_date' => now(),
                'source_doc_type' => 'journal_reversal',
                'source_doc_id' => $entry->id,
                'narration' => "Reversal of {$entry->doc_number}: {$validated['reason']}",
                'reverses_journal_id' => $entry->id,
                'posted_by' => $request->user()->id,
            ], $reversedLines);
        } catch (NoOpenPeriodException $e) {
            return $this->error('NO_OPEN_PERIOD', $e->getMessage(), 422);
        } catch (UnbalancedJournalException $e) {
            return $this->error('UNBALANCED_JOURNAL', $e->getMessage(), 422);
        }

        AuditLog::record('JOURNAL_REVERSED', 'journal_entry', $reversal->id, [
            'original_doc_number' => $entry->doc_number,
            'reversal_doc_number' => $reversal->doc_number,
            'reason' => $validated['reason'],
        ]);

        return response()->json($reversal->load('lines.account:id,code,name,system_role'), 201);
    }

    public function periods(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'report.financial.view');

        return response()->json(FinancialPeriod::where('organisation_id', $this->organisationId($request))->orderByDesc('start_date')->get());
    }

    /**
     * POST /api/finance/periods/{id}/close — Part 12.4. The closing checklist
     * that can be verified from data is verified: every journal in the
     * period balances and the ledger reconciles.
     */
    public function closePeriod(Request $request, string $period): JsonResponse
    {
        $this->requirePermission($request, 'period.close');

        $period = FinancialPeriod::where('organisation_id', $this->organisationId($request))->findOrFail($period);
        if ($period->status !== 'OPEN') {
            return $this->error('INVALID_STATE', "Period {$period->fiscal_year}/{$period->period_no} is {$period->status}.", 409);
        }

        $unbalanced = JournalEntry::where('period_id', $period->id)->get()->reject(fn (JournalEntry $j) => $j->isBalanced())->count();
        $driftRows = DB::table('stock_balances as b')
            ->leftJoin('stock_ledgers as l', fn ($j) => $j->on('l.product_id', '=', 'b.product_id')->on('l.batch_id', '=', 'b.batch_id')->on('l.store_id', '=', 'b.store_id'))
            ->groupBy('b.id', 'b.qty_on_hand')->havingRaw('COALESCE(SUM(l.qty_base), 0) <> b.qty_on_hand')->select('b.id');
        $drift = DB::query()->fromSub($driftRows, 'drift')->count();

        if ($unbalanced > 0 || $drift > 0) {
            return $this->error('CLOSE_CHECKLIST_FAILED', 'The period cannot close until every journal balances and the stock ledger reconciles.', 422, [
                'unbalanced_journals' => $unbalanced, 'stock_balance_drift_rows' => $drift,
            ]);
        }

        $period->update(['status' => 'CLOSED', 'closed_by' => $request->user()->id, 'closed_at' => now()]);
        AuditLog::record('PERIOD_CLOSED', 'financial_period', $period->id, ['reference' => "{$period->fiscal_year}/{$period->period_no}"]);

        return response()->json($period->fresh());
    }
}
