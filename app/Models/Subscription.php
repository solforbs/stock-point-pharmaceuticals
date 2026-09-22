<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An institution's paid plan. Each successful payment extends the current
 * period by one billing interval from whichever is later: today, or the end
 * of the period already paid for.
 *
 * @property-read string $organisation_id
 * @property-read string $billing_interval
 * @property-read Carbon|null $current_period_end
 */
class Subscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'plan_id', 'billing_interval', 'status', 'current_period_start', 'current_period_end',
        'paystack_customer_code', 'paystack_subscription_code', 'paystack_email_token', 'cancelled_at',
    ];

    protected $hidden = ['paystack_email_token'];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
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
