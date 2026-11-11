<?php

namespace App\Services\Procurement;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ProductBatch;
use App\Models\StockBalance;
use App\Models\SupplierInvoiceLine;
use App\Services\Finance\DocumentJournals;
use App\Services\Finance\JournalPoster;
use App\Services\Inventory\StockLedgerService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GoodsReceiptCorrectionBlockedException extends \RuntimeException {}

/**
 * Corrects a posted goods receipt (quantity, unit, cost, batch details,
 * received date) as if the right figures had been posted, without
 * rewriting history:
 *
 *  - Stock: GRN_CORRECTION ledger rows. A cost change first revalues what
 *    is still on hand of the receipt's batch, in every store (out at the
 *    current average, back in at the corrected cost); the quantity
 *    difference then goes in or out of the receiving store at the
 *    corrected cost. A reduction is refused when that much of the batch is
 *    no longer free in the receiving store (sold, moved or reserved).
 *  - GL: one adjusting journal. Inventory moves by exactly the value the
 *    ledger rows moved; the GRN accrual (what is owed to the supplier,
 *    awaiting their invoice) and the freight accrual move by the change in
 *    the receipt's value; the cost difference on units already sold or
 *    moved out at the old cost goes to cost of sales.
 *  - A changed received date moves the receipt's journals: each is
 *    reversed on its own date and posted again on the new one.
 *
 * Closed periods refuse the postings (JournalPoster), and the whole
 * correction rolls back.
 */
class GoodsReceiptCorrectionService
{
    public function __construct(
        private readonly StockLedgerService $ledger,
        private readonly JournalPoster $journalPoster,
        private readonly DocumentJournals $journals,
        private readonly GoodsReceiptService $receipts,
    ) {}

    /**
     * @param  array{received_at?: ?string, lines: list<array{id: string, qty_accepted?: string|int|float, qty_rejected?: string|int|float|null, uom_id?: string, unit_cost?: string|int|float, batch_number?: string, expiry_date?: string, manufacture_date?: ?string}>}  $changes
     */
    public function correct(GoodsReceipt $receipt, array $changes, string $reason, int $userId): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $changes, $reason, $userId) {
            $receipt = GoodsReceipt::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if ($receipt->status !== 'POSTED') {
                throw new GoodsReceiptCorrectionBlockedException("Receipt {$receipt->doc_number} is {$receipt->status}; only a posted receipt can be corrected.");
            }

            $organisationId = (string) Branch::whereKey($receipt->branch_id)->value('organisation_id');
            $before = $this->snapshot($receipt);

            $newDate = ! empty($changes['received_at']) ? Carbon::parse($changes['received_at']) : null;
            $dateChanged = $newDate && $newDate->toDateString() !== $receipt->received_at?->toDateString();
            if ($dateChanged) {
                $this->moveJournals($receipt, $newDate, $userId);
                $receipt->update(['received_at' => $newDate->copy()->setTimeFrom($receipt->received_at ?? now())]);
            }

            $inventoryDelta = '0.0000';
            $goodsDelta = '0.0000';
            $freightDelta = '0.0000';

            foreach ($changes['lines'] as $change) {
                $line = $receipt->lines()->whereKey($change['id'])->firstOrFail();
                [$inventory, $goods, $freight] = $this->correctLine($receipt, $line, $change, $organisationId, $userId);
                $inventoryDelta = bcadd($inventoryDelta, $inventory, 4);
                $goodsDelta = bcadd($goodsDelta, $goods, 4);
                $freightDelta = bcadd($freightDelta, $freight, 4);
            }

            $this->postAdjustment($receipt, $organisationId, $inventoryDelta, $goodsDelta, $freightDelta, $reason, $userId);
            $this->receipts->refreshPurchaseOrderStatus($receipt->fresh());

            $receipt = $receipt->fresh(['lines.batch']);
            AuditLog::record('GRN_CORRECTED', 'goods_receipt', $receipt->id, [
                'user_id' => $userId,
                'branch_id' => $receipt->branch_id,
                'reference' => $receipt->doc_number,
                'reason' => $reason,
                'before_json' => $before,
                'after_json' => $this->snapshot($receipt),
            ]);

            return $receipt;
        });
    }

    /**
     * @param  array<string, mixed>  $change
     * @return array{0: string, 1: string, 2: string} [inventory value moved, goods value change, freight value change]
     */
    private function correctLine(GoodsReceipt $receipt, GoodsReceiptLine $line, array $change, string $organisationId, int $userId): array
    {
        $oldBase = $line->qtyAcceptedBase();
        $oldGoods = $line->unitCostBase();
        $oldLanded = $line->landedUnitCostBase();
        $freightPerBase = bcsub($oldLanded, $oldGoods, 4);
        $oldAccepted = (string) $line->qty_accepted;

        $newAccepted = (string) ($change['qty_accepted'] ?? $line->qty_accepted);
        $rejected = (string) ($change['qty_rejected'] ?? $line->qty_rejected);
        // Whatever was miscounted as accepted was miscounted as delivered too.
        $delivered = bcadd((string) $line->qty_delivered, bcsub($newAccepted, $oldAccepted, 4), 4);
        $minimumDelivered = bcadd($newAccepted, $rejected, 4);
        $line->fill([
            'qty_accepted' => $newAccepted,
            'qty_rejected' => $rejected,
            'qty_delivered' => bccomp($delivered, $minimumDelivered, 4) < 0 ? $minimumDelivered : $delivered,
            'uom_id' => $change['uom_id'] ?? $line->uom_id,
            'unit_cost' => (string) ($change['unit_cost'] ?? $line->unit_cost),
            'batch_number' => $change['batch_number'] ?? $line->batch_number,
            'expiry_date' => $change['expiry_date'] ?? $line->expiry_date,
            'manufacture_date' => array_key_exists('manufacture_date', $change) ? $change['manufacture_date'] : $line->manufacture_date,
        ]);
        if ($line->isDirty('unit_cost') && ! $line->isDirty('uom_id') && $line->trade_price !== null) {
            // A keyed cost replaces the trade-price derivation.
            $line->trade_price = null;
            $line->discount_pct = null;
        }

        $factor = $line->factorToBase();
        $newBase = $line->qtyAcceptedBase();
        $newGoods = $line->unitCostBase();
        $newLanded = bcadd($newGoods, $freightPerBase, 4);
        $line->landed_unit_cost = bccomp($freightPerBase, '0', 4) > 0 ? bcmul($newLanded, (string) $factor, 4) : null;

        $this->guardMatchedInvoices($line);

        $batch = $line->batch_id ? ProductBatch::lockForUpdate()->find($line->batch_id) : null;
        if (! $batch && bccomp($newBase, '0', 4) > 0) {
            $batch = ProductBatch::create([
                'organisation_id' => $organisationId,
                'product_id' => $line->product_id,
                'batch_number' => $line->batch_number,
                'brand_name' => $line->brand_name,
                'expiry_date' => $line->expiry_date,
                'manufacture_date' => $line->manufacture_date,
                'supplier_id' => $receipt->supplier_id,
                'grn_line_id' => $line->id,
                'unit_cost' => $newGoods,
                'landed_unit_cost' => $newLanded,
                'status' => 'PENDING_QC',
            ]);
            $line->batch_id = $batch->id;
        }
        $line->save();

        $inventoryDelta = '0.0000';
        $ledgerRow = fn (string $storeId, string $qty, string $cost) => $this->ledger->post([
            'organisation_id' => $organisationId,
            'txn_type' => 'GRN_CORRECTION',
            'product_id' => $line->product_id,
            'batch_id' => $batch->id,
            'store_id' => $storeId,
            'qty_base' => $qty,
            'unit_cost' => $cost,
            'source_doc_type' => 'goods_receipt',
            'source_doc_id' => $receipt->id,
            'source_doc_line_id' => $line->id,
            'user_id' => $userId,
            'branch_id' => $receipt->branch_id,
        ]);

        if ($batch) {
            // 1. A cost change revalues what is still on hand of the batch, wherever it sits.
            if (bccomp($newLanded, $oldLanded, 4) !== 0) {
                $balances = StockBalance::where('batch_id', $batch->id)->where('qty_on_hand', '>', 0)->lockForUpdate()->get();
                foreach ($balances as $balance) {
                    $qty = (string) $balance->qty_on_hand;
                    $wac = (string) $balance->wac;
                    $ledgerRow($balance->store_id, bcmul($qty, '-1', 4), $wac);
                    $ledgerRow($balance->store_id, $qty, $newLanded);
                    $inventoryDelta = bcadd($inventoryDelta, bcmul($qty, bcsub($newLanded, $wac, 4), 4), 4);
                }
            }

            // 2. The quantity difference, in or out of the receiving store.
            $qtyDelta = bcsub($newBase, $oldBase, 4);
            if (bccomp($qtyDelta, '0', 4) < 0) {
                $balance = StockBalance::where('batch_id', $batch->id)->where('store_id', $receipt->store_id)->lockForUpdate()->first();
                $free = $balance ? bcsub((string) $balance->qty_on_hand, (string) $balance->qty_reserved, 4) : '0';
                $needed = bcmul($qtyDelta, '-1', 4);
                if (bccomp($free, $needed, 4) < 0) {
                    throw new GoodsReceiptCorrectionBlockedException(sprintf(
                        'Line %s (batch %s): the correction takes back %s base units, but only %s are still free in the receiving store — the rest has already been sold, moved or reserved. Bring the stock back to the store or record a stock adjustment instead.',
                        $line->product?->name ?? $line->product_id, $batch->batch_number, $this->trim($needed), $this->trim($free),
                    ));
                }
                $cost = (string) $balance->wac;
                $ledgerRow($receipt->store_id, $qtyDelta, $cost);
                $inventoryDelta = bcadd($inventoryDelta, bcmul($qtyDelta, $cost, 4), 4);
            } elseif (bccomp($qtyDelta, '0', 4) > 0) {
                $ledgerRow($receipt->store_id, $qtyDelta, $newLanded);
                $inventoryDelta = bcadd($inventoryDelta, bcmul($qtyDelta, $newLanded, 4), 4);
            }

            $batch->update([
                'batch_number' => $line->batch_number,
                'expiry_date' => $line->expiry_date,
                'manufacture_date' => $line->manufacture_date,
                'unit_cost' => $newGoods,
                'landed_unit_cost' => $newLanded,
            ]);
        }

        $goodsDelta = bcsub(bcmul($newBase, $newGoods, 4), bcmul($oldBase, $oldGoods, 4), 4);
        $freightDelta = bcmul(bcsub($newBase, $oldBase, 4), $freightPerBase, 4);

        return [$inventoryDelta, $goodsDelta, $freightDelta];
    }

    /** A matched supplier invoice already billed some quantity; the receipt cannot fall below it. */
    private function guardMatchedInvoices(GoodsReceiptLine $line): void
    {
        if (! $line->purchase_order_line_id) {
            return;
        }

        $invoiced = (string) SupplierInvoiceLine::where('purchase_order_line_id', $line->purchase_order_line_id)
            ->whereHas('invoice', fn ($q) => $q->where('match_status', 'MATCHED'))
            ->sum('qty');
        $accepted = (string) GoodsReceiptLine::where('purchase_order_line_id', $line->purchase_order_line_id)
            ->whereKeyNot($line->id)->sum('qty_accepted');
        $accepted = bcadd($accepted, (string) $line->qty_accepted, 4);

        if (bccomp($accepted, $invoiced, 4) < 0) {
            throw new GoodsReceiptCorrectionBlockedException("A matched supplier invoice already bills {$this->trim($invoiced)} on this order line; the receipt cannot be reduced below that. Correct or unmatch the supplier invoice first.");
        }
    }

    private function postAdjustment(GoodsReceipt $receipt, string $organisationId, string $inventory, string $goods, string $freight, string $reason, int $userId): void
    {
        $costOfSales = bcsub(bcadd($goods, $freight, 4), $inventory, 4);
        $narration = "GRN {$receipt->doc_number} correction";
        $lines = array_values(array_filter([
            DocumentJournals::signedLine('INVENTORY', $inventory, "{$narration} — stock"),
            DocumentJournals::signedLine('GRN_ACCRUAL', bcmul($goods, '-1', 4), "{$narration} — awaiting supplier invoice", ['partner_type' => 'supplier', 'partner_id' => $receipt->supplier_id]),
            DocumentJournals::signedLine('FREIGHT_ACCRUAL', bcmul($freight, '-1', 4), "{$narration} — transport"),
            DocumentJournals::signedLine('COGS', $costOfSales, "{$narration} — cost of stock already sold or moved"),
        ]));
        if ($lines === []) {
            return;
        }
        if (bccomp($freight, '0', 4) !== 0) {
            ChartOfAccountsSeeder::provisionAccounts($organisationId);
        }

        $this->journalPoster->post([
            'organisation_id' => $organisationId,
            'branch_id' => $receipt->branch_id,
            'entry_date' => $receipt->received_at ?? now(),
            'source_doc_type' => 'goods_receipt_correction',
            'source_doc_id' => $receipt->id,
            'narration' => "{$narration}: {$reason}",
            'posted_by' => $userId,
        ], $lines);
    }

    /** Re-dates the receipt's journals: each reversed on its own date, posted again on the new one. */
    private function moveJournals(GoodsReceipt $receipt, Carbon $newDate, int $userId): void
    {
        foreach ($this->journals->live($receipt->id, ['goods_receipt', 'goods_receipt_correction']) as $journal) {
            /** @var JournalEntry $journal */
            $this->journals->reverse($journal, 'goods_receipt_correction', "GRN {$receipt->doc_number} re-dated", $userId);
            $this->journalPoster->post([
                'organisation_id' => $journal->organisation_id,
                'branch_id' => $journal->branch_id,
                'entry_date' => $newDate,
                'source_doc_type' => 'goods_receipt_correction',
                'source_doc_id' => $receipt->id,
                'narration' => "{$journal->narration} (re-dated to {$newDate->toDateString()})",
                'posted_by' => $userId,
            ], $journal->lines->map(fn (JournalEntryLine $l) => [
                'account_id' => $l->account_id,
                'debit' => (string) $l->debit_amount,
                'credit' => (string) $l->credit_amount,
                'branch_id' => $l->branch_id,
                'partner_type' => $l->partner_type,
                'partner_id' => $l->partner_id,
                'tax_code_id' => $l->tax_code_id,
                'narration' => $l->narration,
            ])->all());
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(GoodsReceipt $receipt): array
    {
        return [
            'received_at' => $receipt->received_at?->toDateTimeString(),
            'lines' => $receipt->lines()->get()->map(fn (GoodsReceiptLine $l) => $l->only([
                'id', 'uom_id', 'qty_delivered', 'qty_accepted', 'qty_rejected', 'unit_cost', 'landed_unit_cost', 'batch_number', 'expiry_date', 'manufacture_date',
            ]))->all(),
        ];
    }

    private function trim(string $qty): string
    {
        return rtrim(rtrim($qty, '0'), '.') ?: '0';
    }
}
