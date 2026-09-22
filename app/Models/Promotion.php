<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read string $promo_type
 * @property-read string $funded_by
 * @property-read Carbon $effective_from
 * @property-read Carbon $effective_to
 * @property-read string|null $customer_scope
 * @property-read string|null $branch_scope
 */
class Promotion extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'code', 'name', 'promo_type', 'effective_from', 'effective_to',
        'customer_scope', 'branch_scope', 'funded_by', 'supplier_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PromotionLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PromotionLine::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isCurrentlyActive(?\DateTimeInterface $asOf = null): bool
    {
        $asOf ??= now();

        return $this->is_active
            && $this->effective_from <= $asOf
            && $this->effective_to >= $asOf;
    }
}
