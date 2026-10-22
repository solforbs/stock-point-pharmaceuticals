<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenantBranch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read string $status
 * @property-read Carbon|null $expected_date
 * @property-read Carbon|null $sent_at
 */
class PurchaseOrder extends Model
{
    use BelongsToTenantBranch, HasUuids;

    protected $fillable = [
        'doc_number', 'supplier_id', 'branch_id', 'requisition_id', 'rfq_id', 'status',
        'created_by', 'approved_by', 'sent_at', 'expected_date', 'transport_cost',
        'over_receipt_tolerance_pct', 'under_receipt_close_pct',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'expected_date' => 'date',
            'over_receipt_tolerance_pct' => 'decimal:2',
            'under_receipt_close_pct' => 'decimal:2',
            'transport_cost' => 'decimal:4',
        ];
    }

    /**
     * The transport cost each line carries, per unit ordered (in the line's
     * own unit): shared by value (qty × price), or by quantity when no line
     * is priced yet. A line's landed price is its unit price plus this.
     *
     * @return array<string, string> purchase_order_line_id => transport per unit
     */
    public function transportShares(): array
    {
        $transport = (string) ($this->transport_cost ?? '0');
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();
        if (bccomp($transport, '0', 4) <= 0 || $lines->isEmpty()) {
            return $lines->mapWithKeys(fn (PurchaseOrderLine $l) => [(string) $l->id => '0.0000'])->all();
        }

        $byValue = $lines->contains(fn (PurchaseOrderLine $l) => bccomp((string) ($l->unit_price ?? '0'), '0', 4) > 0);
        $weight = fn (PurchaseOrderLine $l) => $byValue
            ? bcmul((string) $l->qty_ordered, (string) ($l->unit_price ?? '0'), 6)
            : (string) $l->qty_ordered;
        $total = $lines->reduce(fn (string $sum, PurchaseOrderLine $l) => bcadd($sum, $weight($l), 6), '0');

        return $lines->mapWithKeys(function (PurchaseOrderLine $l) use ($transport, $weight, $total) {
            $lineTransport = bccomp($total, '0', 6) > 0 ? bcdiv(bcmul($transport, $weight($l), 8), $total, 8) : '0';
            $perUnit = bccomp((string) $l->qty_ordered, '0', 4) > 0 ? bcdiv($lineTransport, (string) $l->qty_ordered, 4) : '0.0000';

            return [(string) $l->id => $perUnit];
        })->all();
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Rfq, $this>
     */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * @return HasMany<GoodsReceipt, $this>
     */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
