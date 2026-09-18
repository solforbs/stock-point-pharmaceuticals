<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCostAllocation extends Model
{
    use HasUuids;

    protected $fillable = ['landed_cost_id', 'goods_receipt_line_id', 'allocated_amount'];

    protected function casts(): array
    {
        return ['allocated_amount' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<LandedCost, $this>
     */
    public function landedCost(): BelongsTo
    {
        return $this->belongsTo(LandedCost::class);
    }

    /**
     * @return BelongsTo<GoodsReceiptLine, $this>
     */
    public function goodsReceiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class);
    }
}
