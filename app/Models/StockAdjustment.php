<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $reason_code
 * @property-read string $approval_status
 */
class StockAdjustment extends Model
{
    use HasUuids;

    protected $fillable = [
        'doc_number', 'store_id', 'reason_code', 'approval_status',
        'created_by', 'approved_by', 'total_value',
    ];

    protected function casts(): array
    {
        return ['total_value' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<StockAdjustmentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockAdjustmentLine::class);
    }
}
