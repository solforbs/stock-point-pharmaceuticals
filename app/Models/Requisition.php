<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $status
 */
class Requisition extends Model
{
    use HasUuids;

    protected $fillable = ['doc_number', 'branch_id', 'status', 'requested_by', 'approved_by', 'needed_by', 'notes'];

    protected function casts(): array
    {
        return ['needed_by' => 'date'];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<RequisitionLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RequisitionLine::class);
    }
}
