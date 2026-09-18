<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $sale_mode
 */
class PriceList extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'code', 'name', 'sale_mode', 'tier_id', 'branch_id',
        'currency', 'prices_include_tax', 'effective_from', 'effective_to', 'is_active', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'prices_include_tax' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CustomerTier, $this>
     */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(CustomerTier::class, 'tier_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<ProductPrice, $this>
     */
    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }
}
