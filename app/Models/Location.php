<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Part 10.6 — a physical slot inside a store (aisle / rack / bin). Stock
 * ledger rows may carry a location_id when goods are put away to it.
 */
class Location extends Model
{
    use HasUuids;

    public const TYPES = ['BIN', 'SHELF', 'PALLET', 'COLD_SHELF'];

    protected $fillable = ['store_id', 'code', 'name', 'location_type', 'aisle', 'rack', 'bin', 'capacity', 'is_active', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
