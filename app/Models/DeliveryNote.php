<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $status
 */
class DeliveryNote extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'branch_id', 'store_id', 'sales_order_id', 'picking_list_id', 'customer_id',
        'doc_number', 'status', 'vehicle_reg', 'driver_name', 'driver_phone',
        'dispatched_at', 'delivered_at', 'received_by_name', 'sale_id', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
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
     * @return BelongsTo<PickingList, $this>
     */
    public function pickingList(): BelongsTo
    {
        return $this->belongsTo(PickingList::class);
    }

    /**
     * @return HasMany<DeliveryNoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryNoteLine::class);
    }
}
