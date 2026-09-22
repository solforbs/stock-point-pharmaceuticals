<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read string $status
 * @property-read Carbon $expiry_date
 */
class ProductBatch extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'product_id', 'batch_number', 'manufacturer_batch_ref', 'expiry_date', 'manufacture_date',
        'manufacturer_id', 'supplier_id', 'grn_line_id', 'unit_cost', 'landed_unit_cost',
        'status', 'qc_released_by', 'qc_released_at', 'coa_document_id', 'storage_condition_id',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'manufacture_date' => 'date',
            'unit_cost' => 'decimal:4',
            'landed_unit_cost' => 'decimal:4',
            'qc_released_at' => 'datetime',
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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<GoodsReceiptLine, $this>
     */
    public function grnLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'grn_line_id');
    }

    /**
     * @return HasMany<StockBalance, $this>
     */
    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'batch_id');
    }

    public function isSellable(): bool
    {
        return $this->status === 'RELEASED';
    }

    public function daysUntilExpiry(?Carbon $asOf = null): int
    {
        return (int) ($asOf ?? now())->diffInDays($this->expiry_date, false);
    }
}
