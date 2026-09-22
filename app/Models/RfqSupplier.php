<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An invited supplier and, once received, the header of their quote.
 *
 * @property-read string $quote_status
 * @property-read Carbon|null $valid_until
 */
class RfqSupplier extends Model
{
    use HasUuids;

    protected $fillable = [
        'rfq_id', 'supplier_id', 'quote_status', 'quote_reference', 'quote_date', 'valid_until',
        'payment_terms_days', 'delivery_charge', 'notes', 'entered_by', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'valid_until' => 'date',
            'received_at' => 'datetime',
            'payment_terms_days' => 'integer',
            'delivery_charge' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Rfq, $this>
     */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<RfqQuoteLine, $this>
     */
    public function quoteLines(): HasMany
    {
        return $this->hasMany(RfqQuoteLine::class);
    }
}
