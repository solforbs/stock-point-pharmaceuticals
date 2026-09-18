<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentLine extends Model
{
    use HasUuids;

    protected $fillable = ['stock_adjustment_id', 'product_id', 'batch_id', 'qty_base', 'unit_cost', 'line_value'];

    protected function casts(): array
    {
        return [
            'qty_base' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'line_value' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<StockAdjustment, $this>
     */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    /**
     * @return BelongsTo<ProductBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
