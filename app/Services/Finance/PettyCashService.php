<?php

namespace App\Services\Finance;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\NumberSequence;
use App\Models\PettyCashVoucher;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Petty cash is an imprest float held at a branch. A top-up moves money into
 * it (Dr Petty cash, Cr Cash or Bank); a voucher spends from it (Dr the
 * expense account, Cr Petty cash). Each voucher is one journal, so the book
 * and the ledger can never disagree, and a voucher is voided by a reversing
 * journal — never deleted.
 */
class PettyCashService
{
    public function __construct(private readonly JournalPoster $journalPoster) {}

    /** The float currently held at a branch: everything posted to the Petty cash account there. */
    public function balance(string $organisationId, string $branchId): string
    {
        ChartOfAccountsSeeder::provisionAccounts($organisationId);
        $account = ChartOfAccount::byRole($organisationId, 'PETTY_CASH');

        $net = JournalEntryLine::query()
            ->join('journal_entries as j', 'j.id', '=', 'journal_entry_lines.journal_id')
            ->where('journal_entry_lines.account_id', $account->id)
            ->where('j.branch_id', $branchId)
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit_amount - journal_entry_lines.credit_amount), 0) as net')
            ->value('net');

        return number_format((float) $net, 4, '.', '');
    }

    /**
     * @param  array{voucher_date: string, funding_source: string, amount: string, description: string, receipt_ref?: ?string}  $data
     */
    public function topUp(string $organisationId, string $branchId, int $userId, array $data): PettyCashVoucher
    {
        ChartOfAccountsSeeder::provisionAccounts($organisationId);

        return DB::transaction(function () use ($organisationId, $branchId, $userId, $data) {
            $voucher = $this->newVoucher($organisationId, $branchId, $userId, 'TOPUP', $data);
            $voucher->update(['funding_source' => $data['funding_source']]);

            $journal = $this->journalPoster->post([
                'organisation_id' => $organisationId,
                'branch_id' => $branchId,
                'entry_date' => Carbon::parse($data['voucher_date']),
                'source_doc_type' => 'petty_cash',
                'source_doc_id' => $voucher->id,
                'narration' => "Petty cash top-up {$voucher->doc_number} — {$data['description']}",
                'posted_by' => $userId,
            ], [
                ['account_role' => 'PETTY_CASH', 'debit' => (string) $data['amount']],
                ['account_role' => $data['funding_source'] === 'BANK' ? 'BANK' : 'CASH', 'credit' => (string) $data['amount']],
            ]);

            $voucher->update(['journal_id' => $journal->id]);
            AuditLog::record('PETTY_CASH_TOPUP', 'petty_cash_voucher', $voucher->id, ['after_json' => ['amount' => (string) $data['amount'], 'source' => $data['funding_source']]]);

            return $voucher->fresh(['account']);
        });
    }

    /**
     * @param  array{voucher_date: string, account_id: string, amount: string, payee?: ?string, description: string, receipt_ref?: ?string}  $data
     */
    public function spend(string $organisationId, string $branchId, int $userId, array $data): PettyCashVoucher
    {
        ChartOfAccountsSeeder::provisionAccounts($organisationId);

        return DB::transaction(function () use ($organisationId, $branchId, $userId, $data) {
            // Serialise spending per branch so two vouchers cannot both spend the last shilling.
            Branch::whereKey($branchId)->lockForUpdate()->first();

            $expense = ChartOfAccount::query()->where('is_active', true)->where('is_postable', true)->where('account_type', 'EXPENSE')->find($data['account_id']);
            if (! $expense) {
                throw new \DomainException('Choose an expense account for this voucher.');
            }

            $balance = $this->balance($organisationId, $branchId);
            if (bccomp((string) $data['amount'], $balance, 4) > 0) {
                throw new \DomainException('The petty cash float holds KES '.number_format((float) $balance, 2).'; this voucher is KES '.number_format((float) $data['amount'], 2).'. Top the float up first.');
            }

            $voucher = $this->newVoucher($organisationId, $branchId, $userId, 'EXPENSE', $data);
            $voucher->update(['account_id' => $expense->id]);

            $journal = $this->journalPoster->post([
                'organisation_id' => $organisationId,
                'branch_id' => $branchId,
                'entry_date' => Carbon::parse($data['voucher_date']),
                'source_doc_type' => 'petty_cash',
                'source_doc_id' => $voucher->id,
                'narration' => "Petty cash voucher {$voucher->doc_number} — {$data['description']}",
                'posted_by' => $userId,
            ], [
                ['account_id' => $expense->id, 'debit' => (string) $data['amount'], 'narration' => $data['description']],
                ['account_role' => 'PETTY_CASH', 'credit' => (string) $data['amount']],
            ]);

            $voucher->update(['journal_id' => $journal->id]);
            AuditLog::record('PETTY_CASH_SPENT', 'petty_cash_voucher', $voucher->id, ['after_json' => ['amount' => (string) $data['amount'], 'account' => $expense->code]]);

            return $voucher->fresh(['account']);
        });
    }

    public function void(PettyCashVoucher $voucher, string $reason, int $userId): PettyCashVoucher
    {
        if ($voucher->status !== 'POSTED') {
            throw new \DomainException("Voucher {$voucher->doc_number} is already void.");
        }

        return DB::transaction(function () use ($voucher, $reason, $userId) {
            $original = JournalEntry::with('lines')->find($voucher->journal_id);
            if ($original) {
                $this->journalPoster->post([
                    'organisation_id' => $voucher->organisation_id,
                    'branch_id' => $voucher->branch_id,
                    'entry_date' => now(),
                    'source_doc_type' => 'petty_cash_void',
                    'source_doc_id' => $voucher->id,
                    'narration' => "Void of petty cash voucher {$voucher->doc_number} — {$reason}",
                    'posted_by' => $userId,
                    'reverses_journal_id' => $original->id,
                ], $original->lines->map(fn (JournalEntryLine $l) => [
                    'account_id' => $l->account_id,
                    'debit' => (string) $l->credit_amount,
                    'credit' => (string) $l->debit_amount,
                ])->all());
            }

            $voucher->update(['status' => 'VOID', 'voided_by' => $userId, 'void_reason' => $reason, 'voided_at' => now()]);
            AuditLog::record('PETTY_CASH_VOIDED', 'petty_cash_voucher', $voucher->id, ['after_json' => ['reason' => $reason]]);

            return $voucher->fresh(['account']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function newVoucher(string $organisationId, string $branchId, int $userId, string $type, array $data): PettyCashVoucher
    {
        return PettyCashVoucher::create([
            'organisation_id' => $organisationId,
            'branch_id' => $branchId,
            'doc_number' => NumberSequence::next(organisationId: $organisationId, scope: 'PETTY_CASH', branchId: null, prefix: 'PCV'),
            'voucher_type' => $type,
            'voucher_date' => $data['voucher_date'],
            'amount' => (string) $data['amount'],
            'payee' => $data['payee'] ?? null,
            'description' => $data['description'],
            'receipt_ref' => $data['receipt_ref'] ?? null,
            'status' => 'POSTED',
            'created_by' => $userId,
        ]);
    }
}
