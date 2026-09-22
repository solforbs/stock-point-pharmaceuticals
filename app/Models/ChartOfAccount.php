<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $account_type
 * @property-read string|null $system_role
 */
class ChartOfAccount extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'code', 'name', 'account_type', 'parent_id',
        'is_postable', 'system_role', 'currency', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Find the postable account for a system role (Part 12.2) — the posting
     * engine never hardcodes an account number or code.
     */
    public static function byRole(string $organisationId, string $role): self
    {
        return static::query()
            ->where('organisation_id', $organisationId)
            ->where('system_role', $role)
            ->where('is_postable', true)
            ->firstOrFail();
    }
}
