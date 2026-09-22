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
        'created_by', 'approved_by', 'sent_at', 'expected_date',
        'over_receipt_tolerance_pct', 'under_receipt_close_pct',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'expected_date' => 'date',
            'over_receipt_tolerance_pct' => 'decimal:2',
            'under_receipt_close_pct' => 'decimal:2',
        ];
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
