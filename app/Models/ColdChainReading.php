<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Part 8.5 — one temperature (and optional humidity) reading for a store.
 *
 * @property-read Carbon $recorded_at
 */
class ColdChainReading extends Model
{
    use HasUuids;

    protected $fillable = [
        'branch_id', 'store_id', 'recorded_at', 'temperature_c', 'humidity_pct',
        'recorded_by', 'source', 'note', 'is_excursion', 'excursion_id',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'temperature_c' => 'decimal:2',
            'humidity_pct' => 'decimal:2',
            'is_excursion' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<ColdChainExcursion, $this>
     */
    public function excursion(): BelongsTo
    {
        return $this->belongsTo(ColdChainExcursion::class, 'excursion_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
