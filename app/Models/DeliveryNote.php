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
class DeliveryNote extends Model
{
    use BelongsToOrganisation, HasUuids;

    /** How the goods travel. HAND is carried on foot by a staff member. */
    public const DELIVERY_MODES = ['VEHICLE', 'MOTORBIKE', 'HAND', 'CUSTOMER_PICKUP'];

    public const DELIVERY_MODE_LABELS = [
        'VEHICLE' => 'Vehicle',
        'MOTORBIKE' => 'Motorbike',
        'HAND' => 'Hand delivery',
        'CUSTOMER_PICKUP' => 'Customer pickup',
    ];

    protected $fillable = [
        'organisation_id', 'branch_id', 'store_id', 'sales_order_id', 'picking_list_id', 'customer_id',
        'doc_number', 'status', 'delivery_mode', 'vehicle_reg', 'driver_name', 'driver_phone',
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
