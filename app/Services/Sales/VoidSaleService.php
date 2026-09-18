<?php

namespace App\Services\Sales;

use App\Models\AccountsReceivable;
use App\Models\AuditLog;
use App\Models\CustomerCredit;
use App\Models\DeliveryNote;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Sale;
use App\Models\StockLedger;
use App\Services\Finance\JournalPoster;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SaleAlreadyVoidedException extends \RuntimeException {}

class VoidPeriodClosedException extends \RuntimeException {}

/**
 * Part 6.8 / V6 10.4 — void, never delete. Reverses stock (back to the same
 * batches), reverses the balanced journal, keeps the document number
 * permanently, and requires a reason. saleDel from V5 has no equivalent
 * here — there is no hard-delete path for a posted financial transaction.
 *
 * A sale created by a wholesale dispatch posted its stock and journal under
 * the delivery note; voiding it reverses those too, but the goods are
 * treated as never having left — a return after delivery is a credit note
 * with a quality disposition (Part 11.1), not a void.
 */
class VoidSaleService
{
    public function __construct(
        private readonly StockLedgerService $ledger,
        private readonly JournalPoster $journalPoster,
    ) {}

    public function void(Sale $sale, string $reason, int $voidedBy): Sale
    {
        if ($sale->status === 'VOIDED') {
            throw new SaleAlreadyVoidedException("Sale {$sale->doc_number} is already voided.");
        }

        // Part 6.8 — void is only permitted while the period the original
        // sale posted into is still open.
        $originalPeriod = $this->saleJournal($sale)?->period;
        if ($originalPeriod && $originalPeriod->status !== 'OPEN') {
            throw new VoidPeriodClosedException("The financial period for sale {$sale->doc_number} is {$originalPeriod->status}, not open.");
        }

        return DB::transaction(function () use ($sale, $reason, $voidedBy) {
            // 1. Reverse every stock ledger entry this sale posted, back to the same batches.
            foreach ($this->saleLedgerRows($sale) as $entry) {
                $this->ledger->reverse($entry, 'SALE_VOID', ['user_id' => $voidedBy]);
            }

            // 2. Reverse the journal entry exactly (debits <-> credits swapped).
            $original = $this->saleJournal($sale);
            if ($original) {
                $this->reverseJournal($original, $sale, $voidedBy);
            }

            // 3. Reverse the AR impact, if this was a credit sale.
            $arEntries = AccountsReceivable::where('sale_id', $sale->id)->get();
            foreach ($arEntries as $ar) {
                $credit = CustomerCredit::where('customer_id', $ar->customer_id)->lockForUpdate()->first();
                if ($credit) {
                    $newBalance = bcsub((string) $credit->current_balance, (string) $ar->amount, 4);
                    AccountsReceivable::create([
                        'organisation_id' => $ar->organisation_id,
                        'customer_id' => $ar->customer_id,
                        'txn_type' => 'ADJUSTMENT',
                        'sale_id' => $sale->id,
                        'amount' => bcmul((string) $ar->amount, '-1', 4),
                        'balance_after' => $newBalance,
                        'branch_id' => $ar->branch_id,
                        'created_at' => now(),
                    ]);
                    $credit->update(['current_balance' => $newBalance]);
                }
            }

            // 4. Mark the sale voided — document number retained permanently.
            $sale->update([
                'status' => 'VOIDED',
                'voided_by' => $voidedBy,
                'void_reason' => $reason,
                'voided_at' => now(),
            ]);

            DeliveryNote::where('sale_id', $sale->id)->update(['status' => 'CANCELLED']);

            AuditLog::record('SALE_VOIDED', 'sale', $sale->id, [
                'user_id' => $voidedBy,
                'branch_id' => $sale->branch_id,
                'reference' => $sale->doc_number,
                'reason' => $reason,
            ]);

            return $sale->fresh();
        });
    }

    /**
     * @return Collection<int, StockLedger>
     */
    private function saleLedgerRows(Sale $sale)
    {
        $noteIds = DeliveryNote::where('sale_id', $sale->id)->pluck('id');

        return StockLedger::query()
            ->whereIn('txn_type', ['SALE', 'SALE_BONUS'])
            ->where(function ($q) use ($sale, $noteIds) {
                $q->where(fn ($w) => $w->where('source_doc_type', 'sale')->where('source_doc_id', $sale->id));
                if ($noteIds->isNotEmpty()) {
                    $q->orWhere(fn ($w) => $w->where('source_doc_type', 'delivery_note')->whereIn('source_doc_id', $noteIds));
                }
            })
            ->get();
    }

    private function saleJournal(Sale $sale): ?JournalEntry
    {
        $journal = JournalEntry::where('source_doc_type', 'sale')->where('source_doc_id', $sale->id)->first();
        if ($journal) {
            return $journal;
        }

        $noteIds = DeliveryNote::where('sale_id', $sale->id)->pluck('id');
        if ($noteIds->isEmpty()) {
            return null;
        }

        return JournalEntry::where('source_doc_type', 'delivery_note')->whereIn('source_doc_id', $noteIds)->first();
    }

    private function reverseJournal(JournalEntry $original, Sale $sale, int $voidedBy): void
    {
        $lines = $original->lines->map(fn (JournalEntryLine $l) => [
            'account_role' => $l->account->system_role,
            'debit' => (string) $l->credit_amount,
            'credit' => (string) $l->debit_amount,
            'partner_type' => $l->partner_type,
            'partner_id' => $l->partner_id,
            'narration' => "Void of {$sale->doc_number}",
        ])->all();

        $this->journalPoster->post([
            'organisation_id' => $sale->organisation_id,
            'branch_id' => $sale->branch_id,
            'entry_date' => now(),
            'source_doc_type' => 'sale_void',
            'source_doc_id' => $sale->id,
            'narration' => "Reversal of {$original->doc_number} — void of sale {$sale->doc_number}",
            'reverses_journal_id' => $original->id,
            'posted_by' => $voidedBy,
        ], $lines);
    }
}
