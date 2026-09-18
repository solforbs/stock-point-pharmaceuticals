<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleLineBatchAllocation extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'sale_line_id', 'batch_id', 'store_id', 'qty_base', 'unit_cost',
        'is_bonus', 'fefo_overridden', 'override_reason',
    ];

    protected function casts(): array
    {
        return [
            'qty_base' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'is_bonus' => 'boolean',
            'fefo_overridden' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SaleLine, $this>
     */
    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }

    /**
     * @return BelongsTo<ProductBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }
}
