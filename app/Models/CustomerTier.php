<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerTier extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'code', 'name', 'default_price_list_id',
        'default_discount_pct', 'max_discount_pct', 'credit_terms_days',
    ];

    protected function casts(): array
    {
        return [
            'default_discount_pct' => 'decimal:3',
            'max_discount_pct' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function defaultPriceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'default_price_list_id');
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'tier_id');
    }
}
