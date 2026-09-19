<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'goods_receipt_id', 'purchase_order_line_id', 'product_id', 'uom_id',
        'qty_ordered', 'qty_delivered', 'qty_accepted', 'qty_rejected', 'rejection_reason',
        'batch_number', 'expiry_date', 'manufacture_date', 'batch_id',
        'unit_cost', 'landed_unit_cost', 'temperature_on_arrival', 'coa_received',
    ];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:4',
            'qty_delivered' => 'decimal:4',
            'qty_accepted' => 'decimal:4',
            'qty_rejected' => 'decimal:4',
            'expiry_date' => 'date',
            'manufacture_date' => 'date',
            'unit_cost' => 'decimal:4',
            'landed_unit_cost' => 'decimal:4',
            'temperature_on_arrival' => 'decimal:2',
            'coa_received' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /**
     * @return BelongsTo<PurchaseOrderLine, $this>
     */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The purchase unit the line was received in.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * @return BelongsTo<ProductBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    /**
     * Part 5.4 — a GRN line is entered in its purchase UOM (boxes, cartons);
     * stock, batch cost and WAC are always held in the base unit.
     */
    public function factorToBase(): int
    {
        $factor = ProductUom::where('product_id', $this->product_id)->where('uom_id', $this->uom_id)->value('factor_to_base');

        if ($factor === null) {
            throw new \DomainException("Product {$this->product_id} has no conversion for UOM {$this->uom_id}; it cannot be received in that unit.");
        }

        return (int) $factor;
    }

    public function qtyAcceptedBase(): string
    {
        return bcmul((string) $this->qty_accepted, (string) $this->factorToBase(), 4);
    }

    public function unitCostBase(): string
    {
        return bcdiv((string) $this->unit_cost, (string) $this->factorToBase(), 4);
    }

    public function landedUnitCostBase(): string
    {
        return bcdiv((string) ($this->landed_unit_cost ?? $this->unit_cost), (string) $this->factorToBase(), 4);
    }
}
