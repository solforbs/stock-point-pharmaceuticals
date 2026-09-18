<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $posted_at
 */
class CustomerReturn extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'branch_id', 'store_id', 'sale_id', 'customer_id', 'recall_id',
        'doc_number', 'credit_note_number', 'status', 'reason', 'refund_method', 'refund_reference',
        'subtotal', 'tax_total', 'grand_total', 'cost_total',
        'created_by', 'inspected_by', 'posted_by', 'posted_at',
        'etims_status', 'etims_control_code', 'etims_invoice_number', 'etims_submitted_at', 'etims_error',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'cost_total' => 'decimal:4',
            'posted_at' => 'datetime',
            'etims_submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<CustomerReturnLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CustomerReturnLine::class);
    }
}
