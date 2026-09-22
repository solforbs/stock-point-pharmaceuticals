<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Paystack charge for a plan. Recorded as PENDING when checkout starts
 * and settled by verification or the webhook, whichever arrives first;
 * settling twice changes nothing.
 */
class SubscriptionPayment extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'subscription_id', 'plan_id', 'billing_interval', 'reference', 'amount', 'currency',
        'status', 'channel', 'paid_at', 'initiated_by', 'gateway_response',
    ];

    protected $hidden = ['gateway_response'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'gateway_response' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
