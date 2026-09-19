<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Part 7 — one thing the customer was told about their order.
 *
 * @property-read Carbon|null $sent_at
 */
class SalesOrderUpdate extends Model
{
    use HasUuids;

    /**
     * The journey a customer is told about, in order. Anything the warehouse
     * does that does not change what the customer would see is not here.
     */
    public const MILESTONES = [
        'CONFIRMED' => 'Order confirmed and stock reserved',
        'PICKING' => 'Being picked in the warehouse',
        'PACKED' => 'Packed and ready to leave',
        'DISPATCHED' => 'On its way to you',
        'DELIVERED' => 'Delivered',
        'CANCELLED' => 'Cancelled',
    ];

    protected $fillable = [
        'sales_order_id', 'milestone', 'channel', 'recipient', 'sent_at', 'failure_reason', 'note',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }
}
