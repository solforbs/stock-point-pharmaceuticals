<?php

namespace App\Services\Quality;

use App\Models\AuditLog;
use App\Models\ColdChainExcursion;
use App\Models\ColdChainReading;
use App\Models\ProductBatch;
use App\Models\StockBalance;
use App\Models\StorageCondition;
use App\Models\Store;
use App\Services\Inventory\BatchQualityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InvalidExcursionStatusException extends \RuntimeException {}

/**
 * Part 8.5 — cold chain. The window comes from the store's storage condition
 * when it defines one; otherwise COLD stores default to 2–8 °C and every other
 * store to room temperature, 15–25 °C. An out-of-range reading opens (or
 * extends) an excursion; it is never closed without a recorded impact
 * assessment and decision.
 */
class ColdChainService
{
    public function __construct(private BatchQualityService $quality) {}

    /**
     * @return array{min: string, max: string, source: string}
     */
    public function rangeFor(Store $store): array
    {
        $condition = $store->storage_condition_id ? StorageCondition::find($store->storage_condition_id) : null;
        if ($condition && ($condition->min_temp_c !== null || $condition->max_temp_c !== null)) {
            return [
                'min' => $condition->min_temp_c !== null ? (string) $condition->min_temp_c : '-99.00',
                'max' => $condition->max_temp_c !== null ? (string) $condition->max_temp_c : '99.00',
                'source' => 'STORAGE_CONDITION',
            ];
        }

        return $store->store_type === 'COLD'
            ? ['min' => '2.00', 'max' => '8.00', 'source' => 'COLD_DEFAULT']
            : ['min' => '15.00', 'max' => '25.00', 'source' => 'ROOM_DEFAULT'];
    }

    /**
     * @param  array{min: string, max: string}  $range
     */
    public function isInRange(string $temperature, array $range): bool
    {
        return bccomp($temperature, $range['min'], 2) >= 0 && bccomp($temperature, $range['max'], 2) <= 0;
    }

    /**
     * @param  array{temperature_c: numeric-string|float|int, humidity_pct?: numeric-string|float|int|null, recorded_at?: string|null, source?: string|null, note?: string|null}  $data
     */
    public function record(Store $store, array $data, int $userId): ColdChainReading
    {
        $range = $this->rangeFor($store);
        $temperature = number_format((float) $data['temperature_c'], 2, '.', '');
        $recordedAt = isset($data['recorded_at']) ? Carbon::parse($data['recorded_at']) : now();
        $inRange = $this->isInRange($temperature, $range);

        return DB::transaction(function () use ($store, $data, $userId, $range, $temperature, $recordedAt, $inRange) {
            $running = ColdChainExcursion::where('store_id', $store->id)
                ->where('status', '!=', 'CLOSED')->whereNull('ended_at')
                ->lockForUpdate()->latest('started_at')->first();

            $excursion = null;
            if (! $inRange) {
                if ($running) {
                    $running->update([
                        'min_temp' => bccomp($temperature, (string) $running->min_temp, 2) < 0 ? $temperature : $running->min_temp,
                        'max_temp' => bccomp($temperature, (string) $running->max_temp, 2) > 0 ? $temperature : $running->max_temp,
                    ]);
                    $excursion = $running;
                } else {
                    $excursion = ColdChainExcursion::create([
                        'branch_id' => $store->branch_id,
                        'store_id' => $store->id,
                        'started_at' => $recordedAt,
                        'min_temp' => $temperature,
                        'max_temp' => $temperature,
                        'range_min' => $range['min'],
                        'range_max' => $range['max'],
                        'status' => 'OPEN',
                    ]);
                    AuditLog::record('COLD_CHAIN_EXCURSION_OPENED', 'cold_chain_excursion', $excursion->id, [
                        'user_id' => $userId,
                        'reference' => $store->code,
                        'after_json' => ['temperature_c' => $temperature, 'range' => $range],
                    ]);
                }
            } elseif ($running) {
                // Back in range: the excursion has ended, but stays OPEN until reviewed.
                $running->update(['ended_at' => $recordedAt]);
            }

            return ColdChainReading::create([
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
                'recorded_at' => $recordedAt,
                'temperature_c' => $temperature,
                'humidity_pct' => $data['humidity_pct'] ?? null,
                'recorded_by' => $userId,
                'source' => $data['source'] ?? 'MANUAL',
                'note' => $data['note'] ?? null,
                'is_excursion' => ! $inRange,
                'excursion_id' => $excursion?->id,
            ]);
        });
    }

    public function startReview(ColdChainExcursion $excursion, int $userId): ColdChainExcursion
    {
        if ($excursion->status !== 'OPEN') {
            throw new InvalidExcursionStatusException("Only an OPEN excursion can be taken under review (it is {$excursion->status}).");
        }

        $excursion->update(['status' => 'UNDER_REVIEW', 'reviewed_by' => $userId, 'review_started_at' => now()]);
        AuditLog::record('COLD_CHAIN_EXCURSION_REVIEW', 'cold_chain_excursion', $excursion->id, [
            'user_id' => $userId,
            'before_json' => ['status' => 'OPEN'],
            'after_json' => ['status' => 'UNDER_REVIEW'],
        ]);

        return $excursion->fresh() ?? $excursion;
    }

    /**
     * Closing records the pharmacist's decision. STOCK_QUARANTINED holds every
     * RELEASED batch that has stock in the store.
     */
    public function close(ColdChainExcursion $excursion, string $impactAssessment, string $action, int $userId): ColdChainExcursion
    {
        if ($excursion->status === 'CLOSED') {
            throw new InvalidExcursionStatusException('This excursion is already closed.');
        }
        if ($excursion->ended_at === null && $action === 'NO_IMPACT') {
            throw new InvalidExcursionStatusException('The store is still out of range; an excursion in progress cannot be closed as having no impact.');
        }

        return DB::transaction(function () use ($excursion, $impactAssessment, $action, $userId) {
            $affected = [];
            if ($action === 'STOCK_QUARANTINED') {
                $batchIds = StockBalance::where('store_id', $excursion->store_id)
                    ->where('qty_on_hand', '>', 0)->whereNotNull('batch_id')->pluck('batch_id')->unique();
                $reason = 'Cold chain excursion '.$excursion->started_at->format('Y-m-d H:i').' ('.$excursion->id.'): '.$impactAssessment;
                ProductBatch::whereIn('id', $batchIds)->where('status', 'RELEASED')->get()
                    ->each(function (ProductBatch $batch) use ($userId, $reason, &$affected) {
                        $this->quality->quarantine($batch, $userId, mb_substr($reason, 0, 1000));
                        $affected[] = $batch->id;
                    });
            }

            $before = $excursion->status;
            $excursion->update([
                'status' => 'CLOSED',
                'impact_assessment' => $impactAssessment,
                'action_taken' => $action,
                'affected_batch_ids' => $affected,
                'closed_by' => $userId,
                'closed_at' => now(),
            ]);

            AuditLog::record('COLD_CHAIN_EXCURSION_CLOSED', 'cold_chain_excursion', $excursion->id, [
                'user_id' => $userId,
                'reason' => $impactAssessment,
                'before_json' => ['status' => $before],
                'after_json' => ['status' => 'CLOSED', 'action_taken' => $action, 'quarantined_batches' => count($affected)],
            ]);

            return $excursion->fresh() ?? $excursion;
        });
    }
}
