<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read string $rate_pct
 * @property-read Carbon $effective_from
 * @property-read Carbon|null $effective_to
 */
class TaxRate extends Model
{
    use HasUuids;

    protected $fillable = ['tax_code_id', 'rate_pct', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return [
            'rate_pct' => 'decimal:3',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }
}
