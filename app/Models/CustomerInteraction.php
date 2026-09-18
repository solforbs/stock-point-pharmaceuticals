<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A logged customer touch-point (call, visit, message) with an optional
 * follow-up date that stays "due" until it is marked done.
 */
class CustomerInteraction extends Model
{
    use HasUuids;

    public const CHANNELS = ['CALL', 'VISIT', 'EMAIL', 'SMS', 'WHATSAPP', 'OTHER'];

    protected $fillable = ['customer_id', 'contact_id', 'channel', 'summary', 'follow_up_date', 'follow_up_done', 'user_id', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'follow_up_date' => 'date:Y-m-d',
            'follow_up_done' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<CustomerContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CustomerContact::class, 'contact_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
