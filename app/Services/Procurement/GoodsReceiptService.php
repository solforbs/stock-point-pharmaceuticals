<?php

namespace App\Services\Procurement;

use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\NumberSequence;
use App\Models\ProductBatch;
use App\Services\Finance\JournalPoster;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Part 9.2 — "Only qty_accepted creates stock." Posting a GRN atomically
 * creates one batch per line (starting PENDING_QC per the Part 8.2 state
 * machine — QC release is a separate later action), writes the stock
 * ledger IN entry for the accepted quantity, and posts the Part 12.3
 * "Goods received" journal (Dr Inventory / Cr GRN accrual) so the GL
 * inventory account moves with the stock ledger. Rejected quantity never
 * touches inventory; it becomes a supplier claim only.
 *
 * GRN lines are entered in their purchase UOM; everything that leaves
 * this service — batch cost, ledger quantity, ledger cost — is per base
 * unit (Part 5.2's "one base unit of truth").
 */
class GoodsReceiptService
{
    public function __construct(
        private readonly StockLedgerService $ledger,
        private readonly JournalPoster $journalPoster,
    ) {}

    public function post(GoodsReceipt $receipt): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt) {
            $organisationId = Branch::whereKey($receipt->branch_id)->value('organisation_id');
            $userId = $receipt->received_by ?? Auth::id();
            $inventoryValue = '0.0000';

            foreach ($receipt->lines as $line) {
                if (bccomp((string) $line->qty_accepted, '0', 4) <= 0) {
                    continue;
                }

                $qtyBase = $line->qtyAcceptedBase();
                $landedCostBase = $line->landedUnitCostBase();

                $batch = ProductBatch::create([
                    'organisation_id' => $organisationId,
                    'product_id' => $line->product_id,
                    'batch_number' => $line->batch_number,
                    'expiry_date' => $line->expiry_date,
                    'manufacture_date' => $line->manufacture_date,
                    'supplier_id' => $receipt->supplier_id,
                    'grn_line_id' => $line->id,
                    'unit_cost' => $line->unitCostBase(),
                    'landed_unit_cost' => $landedCostBase,
                    'status' => 'PENDING_QC',
                ]);

                $line->update(['batch_id' => $batch->id]);

                $this->ledger->post([
                    'organisation_id' => $organisationId,
                    'txn_type' => 'GRN_RECEIPT',
                    'product_id' => $line->product_id,
                    'batch_id' => $batch->id,
                    'store_id' => $receipt->store_id,
                    'qty_base' => $qtyBase,
                    'unit_cost' => $landedCostBase,
                    'source_doc_type' => 'goods_receipt',
                    'source_doc_id' => $receipt->id,
                    'source_doc_line_id' => $line->id,
                    'user_id' => $userId,
                    'branch_id' => $receipt->branch_id,
                ]);

                $inventoryValue = bcadd($inventoryValue, bcmul($qtyBase, $landedCostBase, 4), 4);
            }

            if (bccomp($inventoryValue, '0', 4) > 0) {
                $this->journalPoster->post([
                    'organisation_id' => $organisationId,
                    'branch_id' => $receipt->branch_id,
                    'entry_date' => $receipt->received_at ?? now(),
                    'source_doc_type' => 'goods_receipt',
                    'source_doc_id' => $receipt->id,
                    'narration' => "Goods received {$receipt->doc_number}",
                    'posted_by' => $userId,
                ], [
                    ['account_role' => 'INVENTORY', 'debit' => $inventoryValue, 'narration' => "GRN {$receipt->doc_number} — stock in"],
                    ['account_role' => 'GRN_ACCRUAL', 'credit' => $inventoryValue, 'partner_type' => 'supplier', 'partner_id' => $receipt->supplier_id, 'narration' => "GRN {$receipt->doc_number} — awaiting supplier invoice"],
                ]);
            }

            $receipt->update(['status' => 'POSTED', 'received_at' => $receipt->received_at ?? now()]);
            $this->updatePurchaseOrderStatus($receipt);

            return $receipt->fresh(['lines']);
        });
    }

    private function updatePurchaseOrderStatus(GoodsReceipt $receipt): void
    {
        $po = $receipt->purchaseOrder;
        if (! $po) {
            return;
        }

        $anyOutstanding = $po->lines->contains(fn ($line) => bccomp($line->qtyOutstanding(), '0', 4) > 0);
        $anyReceived = $po->lines->contains(fn ($line) => bccomp((string) $line->goodsReceiptLines()->sum('qty_accepted'), '0', 4) > 0);

        $po->update(['status' => ! $anyOutstanding ? 'RECEIVED' : ($anyReceived ? 'PARTIALLY_RECEIVED' : $po->status)]);
    }

    public function newDocNumber(string $branchId): string
    {
        $organisationId = Branch::whereKey($branchId)->value('organisation_id');

        return NumberSequence::next(organisationId: $organisationId, scope: 'GRN', branchId: $branchId, prefix: 'GRN');
    }
}
