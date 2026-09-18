<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecallBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'recall_id', 'product_id', 'batch_id', 'status_before',
        'on_hand_at_scope', 'in_transit_at_scope', 'distributed_qty', 'recovered_qty', 'disposed_qty',
    ];

    protected function casts(): array
    {
        return [
            'on_hand_at_scope' => 'decimal:4',
            'in_transit_at_scope' => 'decimal:4',
            'distributed_qty' => 'decimal:4',
            'recovered_qty' => 'decimal:4',
            'disposed_qty' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Recall, $this>
     */
    public function recall(): BelongsTo
    {
        return $this->belongsTo(Recall::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }
}
