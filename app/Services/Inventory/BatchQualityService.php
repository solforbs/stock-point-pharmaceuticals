<?php

namespace App\Services\Inventory;

use App\Models\AuditLog;
use App\Models\ProductBatch;
use App\Models\StockBalance;
use Illuminate\Support\Facades\DB;

class InvalidBatchTransitionException extends \RuntimeException {}

/**
 * Part 8.2 — the batch status state machine. Only RELEASED batches are
 * sellable; every transition is a named, reasoned, audited decision.
 */
class BatchQualityService
{
    private const TRANSITIONS = [
        'PENDING_QC' => ['RELEASED', 'REJECTED', 'QUARANTINED'],
        'RELEASED' => ['QUARANTINED', 'RECALLED', 'EXPIRED'],
        'QUARANTINED' => ['RELEASED', 'DISPOSED', 'RECALLED'],
        'RECALLED' => ['RETURNED_TO_SUPPLIER', 'DISPOSED'],
        'EXPIRED' => ['DISPOSED'],
        'REJECTED' => ['RETURNED_TO_SUPPLIER', 'DISPOSED'],
    ];

    public function release(ProductBatch $batch, int $userId, ?string $justification = null): ProductBatch
    {
        return $this->transition($batch, 'RELEASED', $userId, $justification, [
            'qc_released_by' => $userId,
            'qc_released_at' => now(),
        ]);
    }

    public function quarantine(ProductBatch $batch, int $userId, string $reason): ProductBatch
    {
        return $this->transition($batch, 'QUARANTINED', $userId, $reason);
    }

    public function transition(ProductBatch $batch, string $to, int $userId, ?string $reason = null, array $extra = []): ProductBatch
    {
        $from = $batch->status;
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new InvalidBatchTransitionException("Batch {$batch->batch_number} cannot move from {$from} to {$to}.");
        }
        if (in_array($to, ['QUARANTINED', 'RECALLED', 'DISPOSED', 'REJECTED'], true) && ! $reason) {
            throw new \InvalidArgumentException("A reason is required to move a batch to {$to}.");
        }

        return DB::transaction(function () use ($batch, $from, $to, $userId, $reason, $extra) {
            $batch->update(array_merge(['status' => $to], $extra));

            // Keep the eight-state cache honest: quarantined quantity mirrors
            // on-hand while the batch is held, and clears when it is released.
            $held = in_array($to, ['QUARANTINED', 'RECALLED', 'EXPIRED', 'REJECTED', 'PENDING_QC'], true);
            StockBalance::where('batch_id', $batch->id)->get()->each(function (StockBalance $balance) use ($held) {
                $balance->qty_quarantined = $held ? (string) $balance->qty_on_hand : '0.0000';
                $balance->save();
            });

            AuditLog::record('BATCH_'.$to, 'product_batch', $batch->id, [
                'user_id' => $userId,
                'reference' => $batch->batch_number,
                'reason' => $reason,
                'before_json' => ['status' => $from],
                'after_json' => ['status' => $to],
            ]);

            return $batch->fresh();
        });
    }
}
