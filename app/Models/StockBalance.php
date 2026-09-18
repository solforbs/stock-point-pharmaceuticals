<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $qty_on_hand
 * @property string $qty_reserved
 * @property string $qty_quarantined
 * @property string $wac
 */
class StockBalance extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id', 'batch_id', 'store_id',
        'qty_on_hand', 'qty_reserved', 'qty_quarantined', 'wac', 'last_movement_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_on_hand' => 'decimal:4',
            'qty_reserved' => 'decimal:4',
            'qty_quarantined' => 'decimal:4',
            'wac' => 'decimal:4',
            'last_movement_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Part 7.3 — "free to sell" excludes reserved and quarantined. Expired
     * and recalled stock is excluded via the batch's own status (a RELEASED
     * batch is the only sellable state), not tracked again here.
     */
    public function freeToSell(): string
    {
        return bcsub(bcsub((string) $this->qty_on_hand, (string) $this->qty_reserved, 4), (string) $this->qty_quarantined, 4);
    }
}
