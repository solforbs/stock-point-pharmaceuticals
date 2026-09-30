<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A request for non-pharmaceutical supplies (cleaning, stationery). These
 * are expensed when bought and never enter stock, so they live apart from
 * requisitions and purchase orders.
 *
 * @property-read string $status
 */
class SupplyRequest extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'branch_id', 'doc_number', 'status', 'needed_by', 'notes', 'supplier_id',
        'supplier_name', 'paid_from', 'payment_reference', 'total_cost', 'journal_id', 'requested_by',
        'approved_by', 'purchased_by', 'reject_reason', 'approved_at', 'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'needed_by' => 'date:Y-m-d',
            'total_cost' => 'decimal:4',
            'approved_at' => 'datetime',
            'purchased_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SupplyRequestLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplyRequestLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
