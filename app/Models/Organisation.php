<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An institution: the tenant every other record belongs to.
 *
 * @property-read string $id
 * @property-read string $name
 * @property-read bool $is_complimentary
 * @property-read Carbon|null $trial_ends_at
 * @property-read Carbon|null $suspended_at
 */
class Organisation extends Model
{
    use HasUuids;

    /** Full use: paying, complimentary, or inside the free trial. */
    public const ACCESS_ACTIVE = 'ACTIVE';

    public const ACCESS_TRIAL = 'TRIAL';

    /** The trial or the paid period ran out: read-only until a plan is paid for. */
    public const ACCESS_LAPSED = 'LAPSED';

    /** Stopped by the platform. */
    public const ACCESS_SUSPENDED = 'SUSPENDED';

    public const TRIAL_DAYS = 7;

    protected $fillable = [
        'name', 'legal_name', 'kra_pin', 'vat_number', 'vat_registered',
        'base_currency', 'fiscal_year_start', 'is_active', 'updated_by',
        'contact_email', 'contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'vat_registered' => 'boolean',
            'is_active' => 'boolean',
            'is_complimentary' => 'boolean',
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** The subscription whose paid period covers today, if any. */
    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where('status', 'ACTIVE')
            ->where('current_period_end', '>=', now())
            ->with('plan')
            ->orderByDesc('current_period_end')
            ->first();
    }

    /**
     * What this institution may do today, worked out live so nothing
     * depends on a nightly job having run.
     */
    public function accessState(): string
    {
        return match (true) {
            $this->suspended_at !== null => self::ACCESS_SUSPENDED,
            $this->is_complimentary => self::ACCESS_ACTIVE,
            $this->currentSubscription() !== null => self::ACCESS_ACTIVE,
            $this->trial_ends_at !== null && $this->trial_ends_at->isFuture() => self::ACCESS_TRIAL,
            default => self::ACCESS_LAPSED,
        };
    }

    /** Only an active or trialling institution may post anything. */
    public function canWrite(): bool
    {
        return in_array($this->accessState(), [self::ACCESS_ACTIVE, self::ACCESS_TRIAL], true);
    }

    /** Keeps the stored status (used for platform listings) in step with the live rule. */
    public function refreshSubscriptionStatus(): void
    {
        $this->forceFill(['subscription_status' => $this->accessState()])->save();
    }
}
