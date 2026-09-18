<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read string $method
 * @property-read string $status
 * @property-read Carbon $received_at
 */
class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'branch_id', 'customer_id', 'method', 'reference',
        'amount', 'received_by', 'received_at', 'reconciled_at',
        'status', 'voided_by', 'void_reason', 'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'received_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
