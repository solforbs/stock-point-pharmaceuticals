<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenantBranch;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use BelongsToTenantBranch, HasUuids;

    protected $fillable = ['branch_id', 'code', 'name', 'store_type', 'storage_condition_id', 'is_sellable', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['is_sellable' => 'boolean'];
    }

    protected static function booted(): void
    {
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
}
