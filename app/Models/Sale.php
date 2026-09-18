<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read string $sale_mode
 * @property-read string $status
 * @property-read Carbon $posted_at
 */
class Sale extends Model
{
    use HasUuids;

    public $timestamps = false; // posted_at is the transaction timestamp; voiding updates in place

    protected $fillable = [
        'organisation_id', 'branch_id', 'store_id', 'sale_mode', 'sub_type',
        'customer_id', 'quote_id', 'user_id', 'terminal_id', 'doc_number', 'status',
        'subtotal', 'discount_total', 'tax_total', 'grand_total', 'cost_total', 'idempotency_key',
        'voided_by', 'void_reason', 'voided_at',
        'etims_status', 'etims_control_code', 'etims_invoice_number', 'etims_submitted_at', 'etims_error',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'cost_total' => 'decimal:4',
            'voided_at' => 'datetime',
            'etims_submitted_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<SaleLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function isCreditSale(): bool
    {
        return $this->sale_mode === 'WHOLESALE' && $this->customer_id !== null;
    }
}
