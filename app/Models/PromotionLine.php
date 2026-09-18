<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'promotion_id', 'product_id', 'uom_id',
        'buy_qty', 'free_qty', 'bonus_product_id', 'promo_price', 'discount_pct',
        'max_free_per_order', 'repeat',
    ];

    protected function casts(): array
    {
        return [
            'promo_price' => 'decimal:4',
            'discount_pct' => 'decimal:3',
            'buy_qty' => 'decimal:4',
            'free_qty' => 'decimal:4',
            'max_free_per_order' => 'decimal:4',
            'repeat' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function bonusProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bonus_product_id');
    }
}
