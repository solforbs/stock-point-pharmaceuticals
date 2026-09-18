<?php

namespace App\Services\Pricing;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUom;
use App\Models\Promotion;
use Illuminate\Support\Carbon;

/**
 * Step 4 — free goods (Part 4.6). A bonus is not a discount: it is extra
 * stock leaving the warehouse at zero revenue but full cost, so it is
 * resolved as a quantity and later posted as its own SALE_BONUS line.
 */
class BonusResolver
{
    /**
     * @return array{bonus_qty: string, bonus_product_id: ?string, promotion_id: ?string, promotion_code: ?string, funded_by: ?string, explain: ?string}
     */
    public function resolve(Product $product, ProductUom $uom, string $qty, ?Customer $customer, ?string $branchId, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $none = ['bonus_qty' => '0.0000', 'bonus_product_id' => null, 'promotion_id' => null, 'promotion_code' => null, 'funded_by' => null, 'explain' => null];

        $asOfDate = $asOf->toDateString();

        $promotions = Promotion::query()
            ->where('is_active', true)
            ->where('promo_type', 'BUY_X_GET_Y')
            ->whereDate('effective_from', '<=', $asOfDate)
            ->whereDate('effective_to', '>=', $asOfDate)
            ->where(fn ($q) => $q->whereNull('customer_scope')->orWhere('customer_scope', $customer?->id))
            ->where(fn ($q) => $q->whereNull('branch_scope')->orWhere('branch_scope', $branchId))
            ->get();

        foreach ($promotions as $promotion) {
            $line = $promotion->lines()->where('product_id', $product->id)->where('uom_id', $uom->uom_id)->first();
            if (! $line || $line->buy_qty === null || bccomp((string) $line->buy_qty, '0', 4) <= 0 || $line->free_qty === null) {
                continue;
            }

            $multiples = bcdiv($qty, (string) $line->buy_qty, 0);
            if (bccomp($multiples, '0', 0) <= 0) {
                continue;
            }

            $free = $line->repeat
                ? bcmul($multiples, (string) $line->free_qty, 4)
                : (string) $line->free_qty;

            if ($line->max_free_per_order !== null && bccomp($free, (string) $line->max_free_per_order, 4) > 0) {
                $free = (string) $line->max_free_per_order;
            }

            if (bccomp($free, '0', 4) <= 0) {
                continue;
            }

            return [
                'bonus_qty' => $free,
                'bonus_product_id' => $line->bonus_product_id,
                'promotion_id' => $promotion->id,
                'promotion_code' => $promotion->code,
                'funded_by' => $promotion->funded_by,
                'explain' => "Bonus {$promotion->code}: buy {$line->buy_qty} get {$line->free_qty} free"
                    .($line->repeat ? ' (repeating)' : '')." → {$free} free, funded by {$promotion->funded_by}",
            ];
        }

        return $none;
    }
}
