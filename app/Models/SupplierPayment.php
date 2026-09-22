<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read string $method
 * @property-read string $amount
 * @property-read Carbon $paid_at
 */
class SupplierPayment extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = ['organisation_id', 'branch_id', 'supplier_id', 'method', 'reference', 'amount', 'paid_by', 'paid_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
