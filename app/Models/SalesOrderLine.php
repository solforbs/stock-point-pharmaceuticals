<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'sales_order_id', 'line_number', 'product_id', 'uom_id', 'qty', 'qty_base',
        'list_price', 'unit_price', 'discount_amount', 'discount_pct', 'discount_source',
        'tax_code_id', 'tax_rate', 'tax_amount', 'line_total',
        'qty_reserved_base', 'qty_picked_base', 'qty_dispatched_base', 'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'qty_base' => 'decimal:4',
            'list_price' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'discount_pct' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
            'qty_reserved_base' => 'decimal:4',
            'qty_picked_base' => 'decimal:4',
            'qty_dispatched_base' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The unit the customer ordered in — a box is not 24 tablets to them.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
