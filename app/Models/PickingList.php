<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $status
 */
class PickingList extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'branch_id', 'store_id', 'sales_order_id', 'doc_number', 'status',
        'assigned_picker_id', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
