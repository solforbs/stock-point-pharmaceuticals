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

    /** A KRA PIN: A or P, nine digits, a letter. */
    public const KRA_PIN_PATTERN = '/^[AP]\d{9}[A-Z]$/';

    protected $fillable = [
        'name', 'legal_name', 'kra_pin', 'vat_number', 'vat_registered',
        'base_currency', 'fiscal_year_start', 'is_active', 'updated_by',
        'contact_email', 'contact_phone',
        'tagline', 'about', 'mission', 'vision', 'core_values', 'services', 'website',
        'physical_address', 'postal_address', 'signatory_name', 'signatory_title',
        'mpesa_paybill', 'mpesa_account', 'mpesa_till', 'payment_note',
    ];

    /**
     * The company profile images, by the name the API uses for each, and the
     * column holding its path on the private disk.
     *
     * @var array<string, string>
     */
    public const PROFILE_IMAGES = [
        'logo' => 'logo_path',
        'stamp' => 'stamp_path',
        'signature' => 'signature_path',
    ];

    /**
     * The installation is seeded with P000000000X, a PIN no business holds.
     * Anything printing or filing the PIN treats it, or a blank, as not set.
     */
    public static function isPlaceholderKraPin(?string $pin): bool
    {
        $pin = trim((string) $pin);

        return $pin === '' || preg_match('/^P0{6,}/i', $pin) === 1;
    }

    /** The business's real KRA PIN, or null while only the placeholder is recorded. */
    public function realKraPin(): ?string
    {
        return self::isPlaceholderKraPin($this->kra_pin) ? null : trim((string) $this->kra_pin);
    }

    protected function casts(): array
    {
        return [
            'vat_registered' => 'boolean',
            'core_values' => 'array',
            'services' => 'array',
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
