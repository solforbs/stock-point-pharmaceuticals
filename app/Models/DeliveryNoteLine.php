<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryNoteLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'delivery_note_id', 'sales_order_line_id', 'line_number', 'product_id', 'uom_id',
        'qty', 'qty_base', 'list_price', 'unit_price', 'discount_amount', 'discount_pct', 'discount_source',
        'tax_code_id', 'tax_rate', 'tax_amount', 'line_total', 'unit_cost', 'line_cost',
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
            'unit_cost' => 'decimal:4',
            'line_cost' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<DeliveryNote, $this>
     */
    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    /**
     * @return BelongsTo<SalesOrderLine, $this>
     */
    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    /**
     * @return HasMany<DeliveryNoteLineBatchAllocation, $this>
     */
    public function batchAllocations(): HasMany
    {
        return $this->hasMany(DeliveryNoteLineBatchAllocation::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
