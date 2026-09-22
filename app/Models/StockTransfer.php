<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenantStore;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $status
 */
class StockTransfer extends Model
{
    use BelongsToTenantStore, HasUuids;

    /**
     * A transfer is visible through its source store, and both ends must be
     * the institution's own.
     *
     * @return list<string>
     */
    public function tenantStoreColumns(): array
    {
        return ['from_store_id', 'to_store_id'];
    }

    protected $fillable = [
        'doc_number', 'from_store_id', 'to_store_id', 'status',
        'requested_by', 'approved_by', 'dispatched_at', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function fromStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function toStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    /**
     * @return HasMany<StockTransferLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }
}
