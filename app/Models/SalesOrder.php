<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $status
 */
class SalesOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'branch_id', 'store_id', 'sale_mode', 'sub_type',
        'customer_id', 'quotation_id', 'user_id', 'doc_number', 'status', 'required_date',
        'subtotal', 'discount_total', 'tax_total', 'grand_total', 'cost_total', 'idempotency_key',
        'cancelled_by', 'cancel_reason', 'cancelled_at',
        'payment_terms', 'credit_override_by', 'credit_override_reason', 'credit_override_at',
    ];

    protected function casts(): array
    {
        return [
            'required_date' => 'date',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'cost_total' => 'decimal:4',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<SalesOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    /**
     * @return HasMany<PickingList, $this>
     */
    public function pickingLists(): HasMany
    {
        return $this->hasMany(PickingList::class);
    }
}
