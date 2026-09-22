<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
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
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'branch_id', 'customer_id', 'method', 'reference',
        'amount', 'received_by', 'received_at', 'reconciled_at',
        'reconciled_by', 'reconciliation_ref', 'statement_date', 'statement_amount',
        'status', 'voided_by', 'void_reason', 'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'received_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'statement_date' => 'date:Y-m-d',
            'statement_amount' => 'decimal:4',
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
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
