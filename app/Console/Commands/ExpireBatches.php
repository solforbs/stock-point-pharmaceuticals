<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ProductBatch;
use Illuminate\Console\Command;

/**
 * Part 8.3 — the nightly job that moves batches past their expiry date to
 * EXPIRED. FEFO only ever sells RELEASED batches, so an expired batch
 * disappears from free-to-sell the moment its status changes; it stays in
 * valuation until it is written off through waste disposal.
 */
class ExpireBatches extends Command
{
    protected $signature = 'inventory:expire-batches {--dry-run : Report without changing anything}';

    protected $description = 'Mark batches whose expiry date has passed as EXPIRED (Part 8.3 nightly job)';

    public function handle(): int
    {
        $batches = ProductBatch::query()
            ->whereIn('status', ['PENDING_QC', 'RELEASED', 'QUARANTINED'])
            ->whereDate('expiry_date', '<', now()->toDateString())
            ->get();

        foreach ($batches as $batch) {
            $this->line("{$batch->batch_number} (expired {$batch->expiry_date->toDateString()}) {$batch->status} -> EXPIRED");

            if ($this->option('dry-run')) {
                continue;
            }

            $previous = $batch->status;
            $batch->update(['status' => 'EXPIRED']);

            AuditLog::record('BATCH_EXPIRED', 'product_batch', $batch->id, [
                'reference' => $batch->batch_number,
                'before_json' => ['status' => $previous],
                'after_json' => ['status' => 'EXPIRED', 'expiry_date' => $batch->expiry_date->toDateString()],
            ]);
        }

        $this->info($batches->count().' batch(es) expired.');

        return self::SUCCESS;
    }
}
