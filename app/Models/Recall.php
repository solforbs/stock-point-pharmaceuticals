<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon $initiated_at
 * @property-read Carbon|null $blocked_at
 * @property-read Carbon|null $closed_at
 */
class Recall extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'doc_number', 'status', 'source', 'external_reference', 'reason', 'disposition',
        'effectiveness_pct', 'initiated_by', 'initiated_at', 'blocked_at', 'closed_at', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'effectiveness_pct' => 'decimal:2',
            'initiated_at' => 'datetime',
            'blocked_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<RecallBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(RecallBatch::class);
    }

    /**
     * @return HasMany<RecallCustomer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(RecallCustomer::class);
    }
}
