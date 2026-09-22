<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property-read string $customer_type
 * @property-read string $tax_status
 * @property-read Carbon|null $exemption_expiry
 */
class Customer extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'code', 'name', 'customer_type', 'tier_id', 'tax_status',
        'exemption_ref', 'exemption_expiry', 'payment_terms_days',
        'fulfilment_policy', 'price_list_id', 'email', 'phone', 'address', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'exemption_expiry' => 'date',
        ];
    }

    /**
     * @return BelongsTo<CustomerTier, $this>
     */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(CustomerTier::class, 'tier_id');
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * @return HasMany<CustomerContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    /**
     * @return HasMany<CustomerPrice, $this>
     */
    public function contractPrices(): HasMany
    {
        return $this->hasMany(CustomerPrice::class);
    }

    /**
     * @return HasOne<CustomerCredit, $this>
     */
    public function credit(): HasOne
    {
        return $this->hasOne(CustomerCredit::class);
    }
}
