<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $txn_type
 * @property-read string $qty_base
 * @property-read string $unit_cost
 * @property-read string $total_cost
 */
class StockLedger extends Model
{
    use HasUuids;

    public $timestamps = false; // append-only; created_at is set explicitly, no updated_at at all

    protected $fillable = [
        'organisation_id', 'txn_type', 'product_id', 'batch_id', 'store_id', 'location_id',
        'qty_base', 'unit_cost', 'total_cost',
        'source_doc_type', 'source_doc_id', 'source_doc_line_id', 'reverses_ledger_id',
        'user_id', 'branch_id', 'txn_datetime', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_base' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'txn_datetime' => 'datetime',
            'created_at' => 'datetime',
        ];
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

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('stock_ledgers is append-only — rows are never updated. Post a compensating row instead.');
    }

    public function delete(): ?bool
    {
        throw new \LogicException('stock_ledgers is append-only — rows are never deleted. Post a compensating row instead.');
    }
}
