<?php

namespace App\Services\Procurement;

use App\Models\AccountsPayable;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\NumberSequence;
use App\Models\ProductBatch;
use App\Models\Recall;
use App\Models\StockBalance;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnLine;
use App\Services\Finance\JournalPoster;
use App\Services\Inventory\BatchQualityService;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Inventory\StockLedgerService;
use App\Services\Quality\RecallService;
use Illuminate\Support\Facades\DB;

/**
 * Part 11.2 — the reverse of a GRN: stock leaves at batch cost, a debit
 * note reduces what we owe the supplier (Dr Accounts payable / Cr Inventory).
 */
class SupplierReturnService
{
    public function __construct(
        private readonly StockLedgerService $ledger,
        private readonly JournalPoster $journalPoster,
        private readonly BatchQualityService $quality,
        private readonly RecallService $recalls,
    ) {}

    /**
     * @param  array{supplier_id: string, store_id: string, reason: string, user_id: int, recall_id?: ?string, lines: list<array{product_id: string, batch_id: string, qty_base: string}>}  $data
     */
    public function post(array $data): SupplierReturn
    {
        return DB::transaction(function () use ($data) {
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $store = Store::findOrFail($data['store_id']);
            $organisationId = (string) Branch::whereKey($store->branch_id)->value('organisation_id');
            $recall = ! empty($data['recall_id']) ? Recall::findOrFail($data['recall_id']) : null;

            $return = SupplierReturn::create([
                'doc_number' => NumberSequence::next($organisationId, 'SUPPLIER_RETURN', $store->branch_id, 'SRN'),
                'supplier_id' => $supplier->id,
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
                'status' => 'DRAFT',
                'reason' => $data['reason'],
                'recall_id' => $recall?->id,
                'created_by' => $data['user_id'],
            ]);

            $total = '0.0000';
            foreach ($data['lines'] as $line) {
                $batch = ProductBatch::findOrFail($line['batch_id']);
                if ($batch->product_id !== $line['product_id']) {
                    throw new \InvalidArgumentException("Batch {$batch->batch_number} does not belong to the product on that line.");
                }
                if ($batch->supplier_id && $batch->supplier_id !== $supplier->id) {
                    throw new \InvalidArgumentException("Batch {$batch->batch_number} was not supplied by {$supplier->name}.");
                }
                $qty = bcadd((string) $line['qty_base'], '0', 4);
                if (bccomp($qty, '0', 4) <= 0) {
                    throw new \InvalidArgumentException('A return line quantity must be positive.');
                }

                $balance = StockBalance::where('product_id', $batch->product_id)->where('batch_id', $batch->id)->where('store_id', $store->id)->lockForUpdate()->first();
                $onHand = (string) ($balance->qty_on_hand ?? '0');
                if (bccomp($onHand, $qty, 4) < 0) {
                    throw new InsufficientStockException($qty, $onHand, bcsub($qty, $onHand, 4));
                }

                $unitCost = (string) $batch->landed_unit_cost;
                $srLine = SupplierReturnLine::create([
                    'supplier_return_id' => $return->id, 'product_id' => $batch->product_id, 'batch_id' => $batch->id,
                    'qty_base' => $qty, 'unit_cost' => $unitCost,
                ]);

                $this->ledger->post([
                    'txn_type' => 'PURCHASE_RETURN',
                    'product_id' => $batch->product_id,
                    'batch_id' => $batch->id,
                    'store_id' => $store->id,
                    'qty_base' => bcmul($qty, '-1', 4),
                    'unit_cost' => $unitCost,
                    'source_doc_type' => 'supplier_return',
                    'source_doc_id' => $return->id,
                    'source_doc_line_id' => $srLine->id,
                    'user_id' => $data['user_id'],
                    'branch_id' => $store->branch_id,
                ]);
                $total = bcadd($total, bcmul($qty, $unitCost, 4), 4);

                $remaining = (string) StockBalance::where('batch_id', $batch->id)->sum('qty_on_hand');
                if (bccomp($remaining, '0', 4) <= 0 && in_array($batch->fresh()->status, ['RECALLED', 'REJECTED'], true)) {
                    $this->quality->transition($batch->fresh(), 'RETURNED_TO_SUPPLIER', $data['user_id'], "Supplier return {$return->doc_number}");
                }
                if ($recall) {
                    $this->recalls->recordDisposal($recall, $batch->id, $qty);
                }
            }

            // Debit note against the supplier.
            $outstanding = AccountsPayable::balanceFor($supplier->id);
            AccountsPayable::create([
                'organisation_id' => $organisationId, 'supplier_id' => $supplier->id, 'txn_type' => 'DEBIT_NOTE',
                'amount' => bcmul($total, '-1', 4), 'balance_after' => bcsub($outstanding, $total, 4),
                'branch_id' => $store->branch_id, 'created_at' => now(),
            ]);

            if (bccomp($total, '0', 4) > 0) {
                $this->journalPoster->post([
                    'organisation_id' => $organisationId,
                    'branch_id' => $store->branch_id,
                    'entry_date' => now(),
                    'source_doc_type' => 'supplier_return',
                    'source_doc_id' => $return->id,
                    'narration' => "Supplier return {$return->doc_number} to {$supplier->name}",
                    'posted_by' => $data['user_id'],
                ], [
                    ['account_role' => 'AP_CONTROL', 'debit' => $total, 'partner_type' => 'supplier', 'partner_id' => $supplier->id, 'narration' => "Debit note {$return->doc_number}"],
                    ['account_role' => 'INVENTORY', 'credit' => $total, 'narration' => "Supplier return {$return->doc_number}"],
                ]);
            }

            $return->update(['status' => 'POSTED', 'total_value' => $total, 'posted_by' => $data['user_id'], 'posted_at' => now()]);

            AuditLog::record('SUPPLIER_RETURN_POSTED', 'supplier_return', $return->id, [
                'user_id' => $data['user_id'], 'branch_id' => $store->branch_id, 'reference' => $return->doc_number, 'reason' => $data['reason'],
                'after_json' => ['total_value' => $total],
            ]);

            return $return->fresh(['lines']);
        });
    }
}
