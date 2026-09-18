<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Part 8.5 — a period during which a store sat outside its temperature
 * window. It stays OPEN (even after the temperature recovers) until a
 * pharmacist records an impact assessment and closes it.
 *
 * @property-read Carbon $started_at
 * @property-read Carbon|null $ended_at
 * @property-read Carbon|null $closed_at
 */
class ColdChainExcursion extends Model
{
    use HasUuids;

    protected $fillable = [
        'branch_id', 'store_id', 'started_at', 'ended_at', 'min_temp', 'max_temp', 'range_min', 'range_max',
        'status', 'impact_assessment', 'action_taken', 'affected_batch_ids',
        'reviewed_by', 'review_started_at', 'closed_by', 'closed_at',
    ];

    protected $appends = ['duration_minutes'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'review_started_at' => 'datetime',
            'closed_at' => 'datetime',
            'min_temp' => 'decimal:2',
            'max_temp' => 'decimal:2',
            'range_min' => 'decimal:2',
            'range_max' => 'decimal:2',
            'affected_batch_ids' => 'array',
        ];
    }

    /** Minutes out of range so far (to now while it is still running). */
    public function getDurationMinutesAttribute(): int
    {
        $end = $this->ended_at ?? now();

        return (int) max(0, $this->started_at->diffInMinutes($end));
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<ColdChainReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(ColdChainReading::class, 'excursion_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
