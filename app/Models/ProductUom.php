<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUom extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id', 'uom_id', 'factor_to_base',
        'is_base', 'is_purchase', 'is_sales', 'is_default_sales', 'barcode',
    ];

    protected function casts(): array
    {
        return [
            'factor_to_base' => 'integer',
            'is_base' => 'boolean',
            'is_purchase' => 'boolean',
            'is_sales' => 'boolean',
            'is_default_sales' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProductUom $row) {
            if ($row->is_base && (int) $row->factor_to_base !== 1) {
                throw new \InvalidArgumentException('The base UOM must have factor_to_base = 1 (Part 5.3, rule 1).');
            }

            if ((int) $row->factor_to_base < 1) {
                throw new \InvalidArgumentException('factor_to_base must be a positive integer (Part 5.5).');
            }
        });
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
}
