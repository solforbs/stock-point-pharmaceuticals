<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $posted_at
 * @property-read array<int, string>|null $photos_json
 */
class WasteDisposal extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'branch_id', 'store_id', 'recall_id', 'doc_number', 'status', 'stock_effect', 'reason',
        'disposal_method', 'disposal_contractor', 'certificate_reference', 'ppb_reference',
        'witnessed_by_1', 'witnessed_by_2', 'photos_json', 'notes', 'total_value',
        'created_by', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'stock_effect' => 'boolean',
            'photos_json' => 'array',
            'total_value' => 'decimal:4',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<WasteDisposalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(WasteDisposalLine::class);
    }
}
