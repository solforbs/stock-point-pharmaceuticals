<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $round_to
 */
class ProductDiscountPolicy extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id', 'discount_allowed', 'max_discount_pct', 'max_discount_amount',
        'min_margin_pct', 'bonus_allowed', 'promo_stackable', 'discount_approval_pct', 'round_to',
    ];

    protected function casts(): array
    {
        return [
            'discount_allowed' => 'boolean',
            'max_discount_pct' => 'decimal:3',
            'max_discount_amount' => 'decimal:4',
            'min_margin_pct' => 'decimal:3',
            'bonus_allowed' => 'boolean',
            'promo_stackable' => 'boolean',
            'discount_approval_pct' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
