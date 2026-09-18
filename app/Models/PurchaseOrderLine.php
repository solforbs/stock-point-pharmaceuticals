<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderLine extends Model
{
    use HasUuids;

    protected $fillable = ['purchase_order_id', 'product_id', 'uom_id', 'qty_ordered', 'unit_price', 'tax_code_id'];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:4',
            'unit_price' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<GoodsReceiptLine, $this>
     */
    public function goodsReceiptLines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    /**
     * Part 9.2 — outstanding = ordered minus accepted across all GRNs.
     */
    public function qtyOutstanding(): string
    {
        $accepted = $this->goodsReceiptLines()->sum('qty_accepted');

        return bcsub((string) $this->qty_ordered, (string) $accepted, 4);
    }

    /**
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
