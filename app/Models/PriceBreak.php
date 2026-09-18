<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $break_type
 */
class PriceBreak extends Model
{
    use HasUuids;

    protected $fillable = ['product_price_id', 'min_qty', 'max_qty', 'unit_price', 'break_type'];

    protected function casts(): array
    {
        return [
            'min_qty' => 'decimal:4',
            'max_qty' => 'decimal:4',
            'unit_price' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<ProductPrice, $this>
     */
    public function productPrice(): BelongsTo
    {
        return $this->belongsTo(ProductPrice::class);
    }
}
