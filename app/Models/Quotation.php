<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read string $status
 * @property-read Carbon|null $valid_until
 */
class Quotation extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'branch_id', 'store_id', 'sale_mode', 'customer_id', 'user_id',
        'doc_number', 'status', 'valid_until',
        'subtotal', 'discount_total', 'tax_total', 'grand_total',
        'notes', 'converted_sales_order_id',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'grand_total' => 'decimal:4',
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
     * @return HasMany<QuotationLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class);
    }
}
