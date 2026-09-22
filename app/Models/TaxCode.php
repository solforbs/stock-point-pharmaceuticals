<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxCode extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = ['organisation_id', 'code', 'name', 'tax_type', 'is_recoverable', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_recoverable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<TaxRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    public function currentRate(?\DateTimeInterface $asOf = null): ?TaxRate
    {
        $asOf ??= now();

        return $this->rates()
            ->where('effective_from', '<=', $asOf)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $asOf))
            ->orderByDesc('effective_from')
            ->first();
    }
}
