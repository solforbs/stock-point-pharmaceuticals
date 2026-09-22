<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqLine extends Model
{
    use HasUuids;

    protected $fillable = ['rfq_id', 'product_id', 'uom_id', 'qty', 'notes', 'sort_order', 'awarded_supplier_id', 'recommended_supplier_id'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<Rfq, $this>
     */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * @return HasMany<RfqQuoteLine, $this>
     */
    public function quoteLines(): HasMany
    {
        return $this->hasMany(RfqQuoteLine::class);
    }
}
