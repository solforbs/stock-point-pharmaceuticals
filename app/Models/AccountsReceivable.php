<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $txn_type
 */
class AccountsReceivable extends Model
{
    use BelongsToOrganisation, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'organisation_id', 'customer_id', 'txn_type', 'sale_id', 'payment_id',
        'amount', 'balance_after', 'branch_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
