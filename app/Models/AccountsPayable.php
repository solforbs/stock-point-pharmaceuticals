<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $txn_type
 * @property-read string $amount
 * @property-read string $balance_after
 */
class AccountsPayable extends Model
{
    use BelongsToOrganisation, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'organisation_id', 'supplier_id', 'txn_type', 'supplier_invoice_id', 'supplier_payment_id',
        'amount', 'balance_after', 'due_date', 'branch_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'due_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public static function balanceFor(string $supplierId): string
    {
        return number_format((float) static::where('supplier_id', $supplierId)->sum('amount'), 4, '.', '');
    }
}
