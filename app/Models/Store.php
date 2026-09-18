<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasUuids;

    protected $fillable = ['branch_id', 'code', 'name', 'store_type', 'storage_condition_id', 'is_sellable', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['is_sellable' => 'boolean'];
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
