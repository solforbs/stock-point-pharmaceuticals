<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $notified_at
 */
class RecallCustomer extends Model
{
    use HasUuids;

    protected $fillable = [
        'recall_id', 'customer_id', 'customer_name_snapshot', 'contact_snapshot',
        'qty_distributed', 'qty_recovered', 'notified_at', 'notification_reference',
    ];

    protected function casts(): array
    {
        return [
            'qty_distributed' => 'decimal:4',
            'qty_recovered' => 'decimal:4',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Recall, $this>
     */
    public function recall(): BelongsTo
    {
        return $this->belongsTo(Recall::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
