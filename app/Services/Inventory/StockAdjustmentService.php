<?php

namespace App\Services\Inventory;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\NumberSequence;
use App\Models\ProductBatch;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\Store;
use App\Services\Finance\JournalPoster;
use Illuminate\Support\Facades\DB;

class AdjustmentAlreadyPostedException extends \RuntimeException {}

/**
 * Part 7.8 — every adjustment carries a reason code from a controlled list
 * and, above a configured value (Part 29 #19: KES 10,000 suggested), a
 * second approver. Nothing touches stock until the adjustment is approved:
 * the ledger rows and the Part 12.3 "stock variance" journal post together.
 */
class StockAdjustmentService
{
    public const DEFAULT_APPROVAL_THRESHOLD = '10000.0000';

    public function __construct(
        private readonly StockLedgerService $ledger,
        private readonly JournalPoster $journalPoster,
    ) {}

    /**
     * @param  array{
     *     store_id: string, reason_code: string, user_id: int, notes?: ?string,
     *     lines: list<array{product_id: string, batch_id: string, qty_base: string}>,
     * }  $data
     */
    public function create(array $data): StockAdjustment
    {
        return DB::transaction(function () use ($data) {
            $store = Store::findOrFail($data['store_id']);
            $organisationId = Branch::whereKey($store->branch_id)->value('organisation_id');

            $adjustment = StockAdjustment::create([
                'doc_number' => NumberSequence::next($organisationId, 'ADJUSTMENT', $store->branch_id, 'ADJ'),
                'store_id' => $store->id,
                'reason_code' => $data['reason_code'],
                'approval_status' => 'PENDING',
                'created_by' => $data['user_id'],
                'total_value' => '0',
            ]);

            $totalValue = '0.0000';
            foreach ($data['lines'] as $line) {
                $batch = ProductBatch::findOrFail($line['batch_id']);
                if ($batch->product_id !== $line['product_id']) {
                    throw new \InvalidArgumentException("Batch {$batch->batch_number} does not belong to the product on that line.");
                }
                $qty = bcadd((string) $line['qty_base'], '0', 4);
                if (bccomp($qty, '0', 4) === 0) {
                    throw new \InvalidArgumentException('An adjustment line cannot be zero.');
                }

                $unitCost = (string) $batch->landed_unit_cost;
                $value = bcmul($qty, $unitCost, 4);

                StockAdjustmentLine::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $line['product_id'],
                    'batch_id' => $batch->id,
                    'qty_base' => $qty,
                    'unit_cost' => $unitCost,
                    'line_value' => $value,
                ]);

                $totalValue = bcadd($totalValue, $value, 4);
            }

            $adjustment->update(['total_value' => $totalValue]);

            AuditLog::record('STOCK_ADJUSTMENT_REQUESTED', 'stock_adjustment', $adjustment->id, [
                'user_id' => $data['user_id'],
                'branch_id' => $store->branch_id,
                'reference' => $adjustment->doc_number,
                'reason' => $data['reason_code'].(isset($data['notes']) ? " — {$data['notes']}" : ''),
                'after_json' => ['total_value' => $totalValue],
            ]);

            // Below the threshold the requester's own authority is enough.
            $threshold = (string) Setting::resolve($organisationId, $store->branch_id, 'inventory', 'adjustment_approval_threshold', self::DEFAULT_APPROVAL_THRESHOLD);
            $absValue = bccomp($totalValue, '0', 4) < 0 ? bcmul($totalValue, '-1', 4) : $totalValue;
            if (bccomp($absValue, $threshold, 4) <= 0) {
                return $this->post($adjustment->fresh(['lines']), $data['user_id']);
            }

            return $adjustment->fresh(['lines']);
        });
    }

    public function approve(StockAdjustment $adjustment, int $approverId): StockAdjustment
    {
        if ($adjustment->approval_status !== 'PENDING') {
            throw new AdjustmentAlreadyPostedException("Adjustment {$adjustment->doc_number} is {$adjustment->approval_status}.");
        }

        return DB::transaction(fn () => $this->post($adjustment, $approverId));
    }

    public function reject(StockAdjustment $adjustment, int $approverId, string $reason): StockAdjustment
    {
        if ($adjustment->approval_status !== 'PENDING') {
            throw new AdjustmentAlreadyPostedException("Adjustment {$adjustment->doc_number} is {$adjustment->approval_status}.");
        }

        $adjustment->update(['approval_status' => 'REJECTED', 'approved_by' => $approverId]);
        AuditLog::record('STOCK_ADJUSTMENT_REJECTED', 'stock_adjustment', $adjustment->id, [
            'user_id' => $approverId, 'reference' => $adjustment->doc_number, 'reason' => $reason,
        ]);

        return $adjustment->fresh();
    }

    private function post(StockAdjustment $adjustment, int $approverId): StockAdjustment
    {
        $store = $adjustment->store;
        $organisationId = Branch::whereKey($store->branch_id)->value('organisation_id');

        foreach ($adjustment->lines as $line) {
            $up = bccomp((string) $line->qty_base, '0', 4) > 0;

            $this->ledger->post([
                'organisation_id' => $organisationId,
                'txn_type' => $up ? 'ADJUSTMENT_UP' : 'ADJUSTMENT_DOWN',
                'product_id' => $line->product_id,
                'batch_id' => $line->batch_id,
                'store_id' => $store->id,
                'qty_base' => (string) $line->qty_base,
                'unit_cost' => (string) $line->unit_cost,
                'source_doc_type' => 'stock_adjustment',
                'source_doc_id' => $adjustment->id,
                'source_doc_line_id' => $line->id,
                'user_id' => $approverId,
                'branch_id' => $store->branch_id,
            ]);
        }

        // Part 12.3 — variance loss: Dr Stock variance / Cr Inventory; gain the reverse.
        $value = (string) $adjustment->total_value;
        if (bccomp($value, '0', 4) !== 0) {
            $abs = bccomp($value, '0', 4) < 0 ? bcmul($value, '-1', 4) : $value;
            $loss = bccomp($value, '0', 4) < 0;
            $this->journalPoster->post([
                'organisation_id' => $organisationId,
                'branch_id' => $store->branch_id,
                'entry_date' => now(),
                'source_doc_type' => 'stock_adjustment',
                'source_doc_id' => $adjustment->id,
                'narration' => "Stock adjustment {$adjustment->doc_number} ({$adjustment->reason_code})",
                'posted_by' => $approverId,
            ], [
                ['account_role' => $loss ? 'STOCK_VARIANCE' : 'INVENTORY', 'debit' => $abs],
                ['account_role' => $loss ? 'INVENTORY' : 'STOCK_VARIANCE', 'credit' => $abs],
            ]);
        }

        $adjustment->update(['approval_status' => 'APPROVED', 'approved_by' => $approverId]);

        AuditLog::record('STOCK_ADJUSTED', 'stock_adjustment', $adjustment->id, [
            'user_id' => $approverId,
            'branch_id' => $store->branch_id,
            'reference' => $adjustment->doc_number,
            'reason' => $adjustment->reason_code,
            'after_json' => ['total_value' => $value],
        ]);

        return $adjustment->fresh(['lines']);
    }
}
