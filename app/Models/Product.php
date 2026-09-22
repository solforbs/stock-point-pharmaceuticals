<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'code', 'sku', 'gtin', 'name', 'generic_name', 'strength', 'description',
        'dosage_form_id', 'category_id', 'manufacturer_id', 'base_uom_id',
        'tax_code_id', 'storage_condition_id',
        'is_discrete', 'pack_integrity', 'requires_batch',
        'reorder_point', 'safety_stock', 'lead_time_days', 'default_price', 'is_active',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_discrete' => 'boolean',
            'pack_integrity' => 'boolean',
            'requires_batch' => 'boolean',
            'reorder_point' => 'decimal:4',
            'safety_stock' => 'decimal:4',
            'default_price' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function dosageForm(): BelongsTo
    {
        return $this->belongsTo(DosageForm::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_uom_id');
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function storageCondition(): BelongsTo
    {
        return $this->belongsTo(StorageCondition::class);
    }

    /**
     * @return HasMany<ProductUom, $this>
     */
    public function uoms(): HasMany
    {
        return $this->hasMany(ProductUom::class);
    }

    /**
     * @return HasMany<ProductPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * @return HasOne<ProductDiscountPolicy, $this>
     */
    public function discountPolicy(): HasOne
    {
        return $this->hasOne(ProductDiscountPolicy::class);
    }

    /**
     * @return HasMany<ProductBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    /**
     * Part 5.4 conversion arithmetic, done in bcmath to avoid the float-drift
     * the blueprint explicitly calls out ("0.1 + 0.2 in binary floating point
     * is 0.30000000000000004"). All quantities are decimal strings with 4
     * decimal places to match the DECIMAL(18,4) columns they came from.
     */
    public function toBase(ProductUom $uom, string $qtyInUom): string
    {
        return bcmul($qtyInUom, (string) $uom->factor_to_base, 4);
    }

    public function fromBase(ProductUom $uom, string $qtyBase): string
    {
        return bcdiv($qtyBase, (string) $uom->factor_to_base, 4);
    }

    /**
     * Largest-unit-first breakdown for display (Part 5.4's worked example:
     * 6,820 tablets -> 3 cartons, 4 boxes, 2 strips). Returns [uom_code => qty].
     */
    public function formatQuantity(string $qtyBase): array
    {
        $remaining = $qtyBase;
        $breakdown = [];

        $uoms = $this->uoms()
            ->get()
            ->sortByDesc(fn (ProductUom $u) => (int) $u->factor_to_base);

        foreach ($uoms as $uom) {
            $factor = (string) $uom->factor_to_base;
            $count = bcdiv($remaining, $factor, 0);

            if (bccomp($count, '0', 0) > 0) {
                $breakdown[$uom->uom->code] = $count;
                $remaining = bcmod($remaining, $factor);
            }
        }

        return $breakdown;
    }
}
