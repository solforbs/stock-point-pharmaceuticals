<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $factor_type
 */
class ProductPrice extends Model
{
    use HasUuids;

    protected $fillable = [
        'price_list_id', 'product_id', 'uom_id',
        'factor_type', 'unit_price', 'factor_value', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'factor_value' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * @return HasMany<PriceBreak, $this>
     */
    public function priceBreaks(): HasMany
    {
        return $this->hasMany(PriceBreak::class);
    }
}
