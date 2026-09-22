<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenantBranch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierReturn extends Model
{
    use BelongsToTenantBranch, HasUuids;

    protected $fillable = ['doc_number', 'supplier_id', 'branch_id', 'store_id', 'status', 'reason', 'recall_id', 'total_value', 'created_by', 'posted_by', 'posted_at'];

    protected function casts(): array
    {
        return ['total_value' => 'decimal:4', 'posted_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<SupplierReturnLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierReturnLine::class);
    }
}
