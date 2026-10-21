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
 * and the ledger can never disagree. Voiding, editing and deleting all go
 * through a reversing journal, so the ledger keeps the trail even when the
 * voucher itself changes or leaves the list.
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
            $this->assertFloatSurvivesRemoving($voucher);
            $this->reverse($voucher, 'petty_cash_void', "Void of petty cash voucher {$voucher->doc_number} — {$reason}", $userId);

            $voucher->update(['status' => 'VOID', 'voided_by' => $userId, 'void_reason' => $reason, 'voided_at' => now()]);
            AuditLog::record('PETTY_CASH_VOIDED', 'petty_cash_voucher', $voucher->id, ['after_json' => ['reason' => $reason]]);

            return $voucher->fresh(['account']);
        });
    }

    /**
     * Corrects a posted voucher: its journal is reversed and a new one posted
     * with the corrected date, amount, account or funding source. The voucher
     * keeps its number.
     *
     * @param  array{voucher_date: string, amount: string, description: string, account_id?: ?string, funding_source?: ?string, payee?: ?string, receipt_ref?: ?string}  $data
     */
    public function update(PettyCashVoucher $voucher, array $data, int $userId): PettyCashVoucher
    {
        if ($voucher->status !== 'POSTED') {
            throw new \DomainException("Voucher {$voucher->doc_number} is void; record a new voucher instead.");
        }

        return DB::transaction(function () use ($voucher, $data, $userId) {
            Branch::whereKey($voucher->branch_id)->lockForUpdate()->first();
            $before = $voucher->attributesToArray();
            $amount = (string) $data['amount'];
            $balance = $this->balance($voucher->organisation_id, $voucher->branch_id);

            if ($voucher->voucher_type === 'EXPENSE') {
                $expense = ChartOfAccount::query()->where('is_active', true)->where('is_postable', true)->where('account_type', 'EXPENSE')->find($data['account_id'] ?? null);
                if (! $expense) {
                    throw new \DomainException('Choose an expense account for this voucher.');
                }
                // The voucher's own amount comes back to the float before the corrected amount is spent.
                $available = bcadd($balance, (string) $voucher->amount, 4);
                if (bccomp($amount, $available, 4) > 0) {
                    throw new \DomainException('The petty cash float can cover KES '.number_format((float) $available, 2).' for this voucher; it is now KES '.number_format((float) $amount, 2).'.');
                }
                $lines = [
                    ['account_id' => $expense->id, 'debit' => $amount, 'narration' => $data['description']],
                    ['account_role' => 'PETTY_CASH', 'credit' => $amount],
                ];
                $changes = ['account_id' => $expense->id];
            } else {
                $source = ($data['funding_source'] ?? $voucher->funding_source) === 'BANK' ? 'BANK' : 'CASH';
                if (bccomp(bcadd(bcsub($balance, (string) $voucher->amount, 4), $amount, 4), '0', 4) < 0) {
                    throw new \DomainException('That would leave the petty cash float below zero: part of this top-up has already been spent.');
                }
                $lines = [
                    ['account_role' => 'PETTY_CASH', 'debit' => $amount],
                    ['account_role' => $source, 'credit' => $amount],
                ];
                $changes = ['funding_source' => $source];
            }

            $this->reverse($voucher, 'petty_cash_edit', "Correction of petty cash voucher {$voucher->doc_number}", $userId);

            $voucher->update($changes + [
                'voucher_date' => $data['voucher_date'],
                'amount' => $amount,
                'payee' => $data['payee'] ?? null,
                'description' => $data['description'],
                'receipt_ref' => $data['receipt_ref'] ?? null,
            ]);

            $label = $voucher->voucher_type === 'EXPENSE' ? 'Petty cash voucher' : 'Petty cash top-up';
            $journal = $this->journalPoster->post([
                'organisation_id' => $voucher->organisation_id,
                'branch_id' => $voucher->branch_id,
                'entry_date' => Carbon::parse($data['voucher_date']),
                'source_doc_type' => 'petty_cash',
                'source_doc_id' => $voucher->id,
                'narration' => "{$label} {$voucher->doc_number} (corrected) — {$data['description']}",
                'posted_by' => $userId,
            ], $lines);

            $voucher->update(['journal_id' => $journal->id]);
            AuditLog::record('PETTY_CASH_UPDATED', 'petty_cash_voucher', $voucher->id, [
                'user_id' => $userId, 'reference' => $voucher->doc_number,
                'before_json' => $before, 'after_json' => $voucher->fresh()->attributesToArray(),
            ]);

            return $voucher->fresh(['account']);
        });
    }

    /**
     * Removes a voucher from the book. A posted one is reversed first, so
     * the float and the ledger stay right; the whole row goes into the
     * audit log so a deletion made in error can be read back.
     */
    public function delete(PettyCashVoucher $voucher, string $reason, int $userId): void
    {
        DB::transaction(function () use ($voucher, $reason, $userId) {
            Branch::whereKey($voucher->branch_id)->lockForUpdate()->first();

            if ($voucher->status === 'POSTED') {
                $this->assertFloatSurvivesRemoving($voucher);
                $this->reverse($voucher, 'petty_cash_delete', "Deletion of petty cash voucher {$voucher->doc_number} — {$reason}", $userId);
            }

            AuditLog::record('PETTY_CASH_DELETED', 'petty_cash_voucher', $voucher->id, [
                'user_id' => $userId, 'reference' => $voucher->doc_number, 'reason' => $reason,
                'before_json' => $voucher->attributesToArray(),
            ]);

            $voucher->delete();
        });
    }

    /** Posts the mirror image of the voucher's current journal. */
    private function reverse(PettyCashVoucher $voucher, string $sourceType, string $narration, int $userId): void
    {
        $original = JournalEntry::with('lines')->find($voucher->journal_id);
        if (! $original) {
            return;
        }

        $this->journalPoster->post([
            'organisation_id' => $voucher->organisation_id,
            'branch_id' => $voucher->branch_id,
            'entry_date' => now(),
            'source_doc_type' => $sourceType,
            'source_doc_id' => $voucher->id,
            'narration' => $narration,
            'posted_by' => $userId,
            'reverses_journal_id' => $original->id,
        ], $original->lines->map(fn (JournalEntryLine $l) => [
            'account_id' => $l->account_id,
            'debit' => (string) $l->credit_amount,
            'credit' => (string) $l->debit_amount,
        ])->all());
    }

    /** Taking a top-up back out must not leave the float spending money it never had. */
    private function assertFloatSurvivesRemoving(PettyCashVoucher $voucher): void
    {
        if ($voucher->voucher_type !== 'TOPUP') {
            return;
        }
        $balance = $this->balance($voucher->organisation_id, $voucher->branch_id);
        if (bccomp($balance, (string) $voucher->amount, 4) < 0) {
            throw new \DomainException('The float holds KES '.number_format((float) $balance, 2)."; part of top-up {$voucher->doc_number} has already been spent, so it cannot be taken back out.");
        }
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
