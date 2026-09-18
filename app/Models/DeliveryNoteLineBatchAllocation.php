<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNoteLineBatchAllocation extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['delivery_note_line_id', 'batch_id', 'store_id', 'qty_base', 'unit_cost'];

    protected function casts(): array
    {
        return [
            'qty_base' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<DeliveryNoteLine, $this>
     */
    public function deliveryNoteLine(): BelongsTo
    {
        return $this->belongsTo(DeliveryNoteLine::class);
    }

    /**
     * @return BelongsTo<ProductBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }
}
