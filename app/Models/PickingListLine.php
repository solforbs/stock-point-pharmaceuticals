<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $status
 */
class PickingListLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'picking_list_id', 'sales_order_line_id', 'product_id', 'batch_id', 'store_id',
        'qty_to_pick_base', 'qty_picked_base', 'unit_cost', 'status', 'picked_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_to_pick_base' => 'decimal:4',
            'qty_picked_base' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'picked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PickingList, $this>
     */
    public function pickingList(): BelongsTo
    {
        return $this->belongsTo(PickingList::class);
    }

    /**
     * @return BelongsTo<SalesOrderLine, $this>
     */
    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
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
