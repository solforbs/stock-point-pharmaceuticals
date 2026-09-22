<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $status
 */
class PickingList extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'branch_id', 'store_id', 'sales_order_id', 'doc_number', 'status',
        'assigned_picker_id', 'started_at', 'completed_at',
        'packed_at', 'packed_by', 'package_count', 'total_weight_kg', 'packing_notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'packed_at' => 'datetime',
            'package_count' => 'integer',
            'total_weight_kg' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function packer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packed_by');
    }

    /**
     * @return HasMany<DeliveryNote, $this>
     */
    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class, 'picking_list_id');
    }

    /**
     * @return HasMany<PickingListLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PickingListLine::class);
    }
}
