<?php

namespace App\Services\Inventory;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\NumberSequence;
use App\Models\ProductBatch;
use App\Models\Setting;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\Store;
use App\Services\Finance\JournalPoster;
use Illuminate\Support\Facades\DB;

class InvalidCountStatusException extends \RuntimeException {}

class SecondApproverRequiredException extends \RuntimeException
{
    public function __construct(public readonly string $varianceValue, public readonly string $threshold)
    {
        parent::__construct("Count variance of {$varianceValue} exceeds the {$threshold} threshold and needs a second approver.");
    }
}

/**
 * Part 7.7 — stock counts are batch-level, never product-level.
 *
 * PLANNED → COUNTING (system quantities frozen per batch) → REVIEW
 * (variances computed, reason required on every variance) → APPROVED
 * (variance posted as COUNT_VARIANCE ledger rows and a Stock variance
 * journal) → CLOSED. Above a configured value the approver must be a
 * different person from the one who ran the count.
 */
class StockCountService
{
    public const DEFAULT_APPROVAL_THRESHOLD = '10000.0000';

    public const VARIANCE_REASONS = ['BREAKAGE', 'THEFT', 'EXPIRY', 'SAMPLING', 'CORRECTION_OF_ERROR', 'DONATION', 'COLD_CHAIN_LOSS', 'MISCOUNT', 'UNRECORDED_RECEIPT'];

    public function __construct(
        private readonly StockLedgerService $ledger,
        private readonly JournalPoster $journalPoster,
    ) {}

    /**
     * @param  array{store_id: string, user_id: int, product_ids?: list<string>|null}  $data
     */
    public function plan(array $data): StockCount
    {
        return DB::transaction(function () use ($data) {
            $store = Store::findOrFail($data['store_id']);
            $organisationId = (string) Branch::whereKey($store->branch_id)->value('organisation_id');

            $count = StockCount::create([
                'doc_number' => NumberSequence::next($organisationId, 'STOCK_COUNT', $store->branch_id, 'CNT'),
                'store_id' => $store->id,
                'status' => 'PLANNED',
                'created_by' => $data['user_id'],
            ]);

            $this->snapshot($count, $data['product_ids'] ?? null);

            AuditLog::record('STOCK_COUNT_PLANNED', 'stock_count', $count->id, [
                'user_id' => $data['user_id'], 'branch_id' => $store->branch_id, 'reference' => $count->doc_number,
            ]);

            return $count->fresh(['lines']);
        });
    }

    /** Freezes the system quantity of every line at the moment counting begins. */
    public function start(StockCount $count, int $userId): StockCount
    {
        $this->assertStatus($count, 'PLANNED');

        return DB::transaction(function () use ($count, $userId) {
            foreach ($count->lines as $line) {
                $line->update(['system_qty' => $this->onHand($line->product_id, $line->batch_id, $count->store_id)]);
            }
            $count->update(['status' => 'COUNTING']);
            AuditLog::record('STOCK_COUNT_STARTED', 'stock_count', $count->id, ['user_id' => $userId, 'reference' => $count->doc_number]);

            return $count->fresh(['lines']);
        });
    }

    public function enter(StockCountLine $line, string $countedQty, ?string $reasonCode = null): StockCountLine
    {
        $count = $line->count;
        $this->assertStatus($count, 'COUNTING');
        if (bccomp($countedQty, '0', 4) < 0) {
            throw new \InvalidArgumentException('A counted quantity cannot be negative.');
        }
        if ($reasonCode !== null && ! in_array($reasonCode, self::VARIANCE_REASONS, true)) {
            throw new \InvalidArgumentException("Unknown variance reason {$reasonCode}.");
        }

        $variance = bcsub($countedQty, (string) $line->system_qty, 4);
        $unitCost = (string) ProductBatch::whereKey($line->batch_id)->value('landed_unit_cost');

        $line->update([
            'counted_qty' => bcadd($countedQty, '0', 4),
            'variance_qty' => $variance,
            'variance_value' => bcmul($variance, $unitCost, 4),
            'reason_code' => bccomp($variance, '0', 4) === 0 ? null : $reasonCode,
        ]);

        return $line->fresh();
    }

    /** Every line counted; every variance explained. */
    public function submitForReview(StockCount $count, int $userId): StockCount
    {
        $this->assertStatus($count, 'COUNTING');

        $uncounted = $count->lines()->whereNull('counted_qty')->count();
        if ($uncounted > 0) {
            throw new \DomainException("{$uncounted} line(s) have not been counted yet.");
        }
        $unexplained = $count->lines()->where('variance_qty', '<>', 0)->whereNull('reason_code')->count();
        if ($unexplained > 0) {
            throw new \DomainException("{$unexplained} variance line(s) have no reason code (Part 7.7).");
        }

        $count->update(['status' => 'REVIEW']);
        AuditLog::record('STOCK_COUNT_REVIEW', 'stock_count', $count->id, ['user_id' => $userId, 'reference' => $count->doc_number]);

        return $count->fresh(['lines']);
    }

    public function approve(StockCount $count, int $approverId): StockCount
    {
        $this->assertStatus($count, 'REVIEW');

        $store = Store::findOrFail($count->store_id);
        $organisationId = (string) Branch::whereKey($store->branch_id)->value('organisation_id');
        $threshold = (string) Setting::resolve($organisationId, $store->branch_id, 'inventory', 'count_variance_approval_threshold', self::DEFAULT_APPROVAL_THRESHOLD);

        $absTotal = $count->lines->reduce(fn ($c, StockCountLine $l) => bcadd($c, $this->abs((string) $l->variance_value), 4), '0.0000');
        if (bccomp($absTotal, $threshold, 4) > 0 && $approverId === (int) $count->created_by) {
            throw new SecondApproverRequiredException($absTotal, $threshold);
        }

        return DB::transaction(function () use ($count, $approverId, $store, $organisationId) {
            $gain = '0.0000';
            $loss = '0.0000';

            foreach ($count->lines as $line) {
                if (bccomp((string) $line->variance_qty, '0', 4) === 0) {
                    continue;
                }

                $unitCost = (string) ProductBatch::whereKey($line->batch_id)->value('landed_unit_cost');
                $this->ledger->post([
                    'txn_type' => 'COUNT_VARIANCE',
                    'product_id' => $line->product_id,
                    'batch_id' => $line->batch_id,
                    'store_id' => $store->id,
                    'qty_base' => (string) $line->variance_qty,
                    'unit_cost' => $unitCost,
                    'source_doc_type' => 'stock_count',
                    'source_doc_id' => $count->id,
                    'source_doc_line_id' => $line->id,
                    'user_id' => $approverId,
                    'branch_id' => $store->branch_id,
                ]);

                $value = bcmul((string) $line->variance_qty, $unitCost, 4);
                if (bccomp($value, '0', 4) > 0) {
                    $gain = bcadd($gain, $value, 4);
                } else {
                    $loss = bcadd($loss, $this->abs($value), 4);
                }
            }

            // Part 12.3 — loss: Dr Stock variance / Cr Inventory; gain: the reverse.
            $lines = [];
            if (bccomp($loss, '0', 4) > 0) {
                $lines[] = ['account_role' => 'STOCK_VARIANCE', 'debit' => $loss, 'narration' => "Count {$count->doc_number} — shortage"];
                $lines[] = ['account_role' => 'INVENTORY', 'credit' => $loss, 'narration' => "Count {$count->doc_number} — shortage"];
            }
            if (bccomp($gain, '0', 4) > 0) {
                $lines[] = ['account_role' => 'INVENTORY', 'debit' => $gain, 'narration' => "Count {$count->doc_number} — surplus"];
                $lines[] = ['account_role' => 'STOCK_VARIANCE', 'credit' => $gain, 'narration' => "Count {$count->doc_number} — surplus"];
            }
            if ($lines !== []) {
                $this->journalPoster->post([
                    'organisation_id' => $organisationId,
                    'branch_id' => $store->branch_id,
                    'entry_date' => now(),
                    'source_doc_type' => 'stock_count',
                    'source_doc_id' => $count->id,
                    'narration' => "Stock count {$count->doc_number} variance",
                    'posted_by' => $approverId,
                ], $lines);
            }

            $count->update(['status' => 'APPROVED', 'approved_by' => $approverId, 'approved_at' => now()]);

            AuditLog::record('STOCK_COUNT_APPROVED', 'stock_count', $count->id, [
                'user_id' => $approverId, 'branch_id' => $store->branch_id, 'reference' => $count->doc_number,
                'after_json' => ['gain_value' => $gain, 'loss_value' => $loss],
            ]);

            return $count->fresh(['lines']);
        });
    }

    public function close(StockCount $count, int $userId): StockCount
    {
        $this->assertStatus($count, 'APPROVED');
        $count->update(['status' => 'CLOSED']);
        AuditLog::record('STOCK_COUNT_CLOSED', 'stock_count', $count->id, ['user_id' => $userId, 'reference' => $count->doc_number]);

        return $count->fresh(['lines']);
    }

    /**
     * @param  list<string>|null  $productIds
     */
    private function snapshot(StockCount $count, ?array $productIds): void
    {
        $balances = StockBalance::query()
            ->where('store_id', $count->store_id)
            ->when($productIds, fn ($q) => $q->whereIn('product_id', $productIds))
            ->where('qty_on_hand', '<>', 0)
            ->get();

        foreach ($balances as $balance) {
            StockCountLine::create([
                'stock_count_id' => $count->id,
                'product_id' => $balance->product_id,
                'batch_id' => $balance->batch_id,
                'system_qty' => (string) $balance->qty_on_hand,
            ]);
        }
    }

    private function onHand(string $productId, string $batchId, string $storeId): string
    {
        return (string) (StockBalance::query()
            ->where('product_id', $productId)->where('batch_id', $batchId)->where('store_id', $storeId)
            ->value('qty_on_hand') ?? '0.0000');
    }

    private function abs(string $value): string
    {
        return bccomp($value, '0', 4) < 0 ? bcmul($value, '-1', 4) : $value;
    }

    private function assertStatus(StockCount $count, string $expected): void
    {
        if ($count->status !== $expected) {
            throw new InvalidCountStatusException("Stock count {$count->doc_number} is {$count->status}, not {$expected}.");
        }
    }
}
