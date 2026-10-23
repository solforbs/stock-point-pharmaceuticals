<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenantBranch;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use BelongsToTenantBranch, HasUuids;

    protected $fillable = ['branch_id', 'code', 'name', 'store_type', 'business_mode', 'storage_condition_id', 'is_sellable', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['is_sellable' => 'boolean'];
    }

    protected static function booted(): void
    {
        // A store belongs to the retail or the wholesale business by its kind
        // unless it was set explicitly (null serves both).
        static::creating(function (Store $store) {
            if (! array_key_exists('business_mode', $store->getAttributes())) {
                $store->business_mode = in_array($store->store_type, ['RETAIL', 'DISPENSARY'], true) ? 'RETAIL' : 'WHOLESALE';
            }
        });
        // A new store joins the institution's cached store list at once.
        static::created(fn () => app(TenantContext::class)->refresh());
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function storageCondition(): BelongsTo
    {
        return $this->belongsTo(StorageCondition::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * The business this request runs in (RETAIL or WHOLESALE), chosen at
     * sign-in on a branch that trades in both; null when it runs both.
     */
    public static function currentBusinessMode(): ?string
    {
        return app()->bound('current.business_mode') ? app('current.business_mode') : null;
    }

    /** Stores that serve the business this request runs in, or every store when it runs both. */
    public function scopeInBusinessMode(Builder $query): void
    {
        $mode = self::currentBusinessMode();
        if ($mode !== null) {
            $query->where(fn (Builder $q) => $q->whereNull($this->qualifyColumn('business_mode'))->orWhere($this->qualifyColumn('business_mode'), $mode));
        }
    }

    /**
     * The same restriction as raw SQL on a stores alias, for correlated
     * subqueries. The mode is one of two fixed words, so it is inlined.
     */
    public static function businessModeSql(string $alias): string
    {
        $mode = self::currentBusinessMode();

        return $mode === null ? '' : " AND ({$alias}.business_mode IS NULL OR {$alias}.business_mode = '{$mode}')";
    }

    /**
     * The other business's stores: their stock and sales stay out of view
     * while the session runs in this one.
     *
     * @return list<string>
     */
    public static function outsideBusinessMode(): array
    {
        $mode = self::currentBusinessMode();
        if ($mode === null) {
            return [];
        }

        return self::query()->whereNotNull('business_mode')->where('business_mode', '!=', $mode)
            ->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /**
     * A branch trading in both retail and wholesale keeps its retail stock
     * apart: it gets an (empty) retail store if it has none.
     */
    public static function ensureRetailStoreFor(Branch $branch): void
    {
        if (! $branch->retail_enabled || ! $branch->wholesale_enabled
            || self::where('branch_id', $branch->id)->where('business_mode', 'RETAIL')->exists()) {
            return;
        }

        $code = self::where('branch_id', $branch->id)->where('code', 'RETAIL')->exists() ? 'RETAIL-1' : 'RETAIL';
        self::create([
            'branch_id' => $branch->id, 'code' => $code, 'name' => 'Retail pharmacy',
            'store_type' => 'RETAIL', 'business_mode' => 'RETAIL', 'is_sellable' => true,
        ]);
    }
}
