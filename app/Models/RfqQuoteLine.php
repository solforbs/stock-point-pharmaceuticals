<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqQuoteLine extends Model
{
    use HasUuids;

    protected $fillable = ['rfq_supplier_id', 'rfq_line_id', 'unit_price', 'qty_available', 'lead_time_days', 'shelf_life_months', 'notes'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'qty_available' => 'decimal:4',
            'lead_time_days' => 'integer',
            'shelf_life_months' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RfqSupplier, $this>
     */
    public function rfqSupplier(): BelongsTo
    {
        return $this->belongsTo(RfqSupplier::class);
    }

    /**
     * @return BelongsTo<RfqLine, $this>
     */
    public function rfqLine(): BelongsTo
    {
        return $this->belongsTo(RfqLine::class);
    }
}
