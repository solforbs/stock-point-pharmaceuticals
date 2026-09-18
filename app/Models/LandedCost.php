<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $allocation_basis
 */
class LandedCost extends Model
{
    use HasUuids;

    protected $fillable = ['goods_receipt_id', 'freight', 'clearing', 'insurance', 'duty', 'supplier_rebate', 'allocation_basis'];

    protected function casts(): array
    {
        return [
            'freight' => 'decimal:4',
            'clearing' => 'decimal:4',
            'insurance' => 'decimal:4',
            'duty' => 'decimal:4',
            'supplier_rebate' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /**
     * @return HasMany<LandedCostAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(LandedCostAllocation::class);
    }

    /**
     * Part 9.5 — total extra cost to allocate across the shipment's lines.
     */
    public function totalToAllocate(): string
    {
        return bcsub(
            bcadd(bcadd(bcadd((string) $this->freight, (string) $this->clearing, 4), (string) $this->insurance, 4), (string) $this->duty, 4),
            (string) $this->supplier_rebate,
            4
        );
    }
}
