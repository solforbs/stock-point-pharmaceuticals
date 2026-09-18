<?php

namespace App\Services\Pricing;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUom;
use App\Models\Promotion;
use Illuminate\Support\Carbon;

/**
 * Steps 1 and 2 of the blueprint's seven-step quote (Part 4.2–4.4 / V6 8.2):
 *   1. Customer contract price   (customer_prices)
 *   2. Active promotion          (promotions / promotion_lines)
 *   3. Customer tier price list  (price_lists.tier_id)
 *   4. Mode price list           (price_lists.sale_mode)
 *   5. Branch override           (price_lists.branch_id)
 *   6. Product default price    (products.default_price)
 *
 * Ranks 3-6 are resolved first into a single list price (applying quantity
 * breaks and factor-based pricing), then rank 1 (contract) and rank 2
 * (promotion) are compared against it — whichever is cheaper for the
 * customer wins, per Part 4.2's explicit tie-break rule.
 *
 * Steps 3-7 (discounts, bonus, margin guard, tax, rounding) live in
 * PriceQuoteService, which consumes this result.
 */
class PricingEngine
{
    /**
     * @return array{
     *     unit_price: string,
     *     source: string,
     *     list_price: string,
     *     rank_price: string,
     *     prices_include_tax: bool,
     *     price_list_id: ?string,
     *     explain: list<string>,
     * }
     */
    public function quote(
        Product $product,
        ProductUom $uom,
        string $qty,
        ?Customer $customer,
        string $saleMode,
        ?string $branchId,
        ?string $costPerBaseUnit = null,
        ?Carbon $asOf = null,
    ): array {
        $asOf ??= now();
        $explain = [];

        $resolved = $this->resolveListPrice($product, $uom, $qty, $customer, $saleMode, $branchId, $costPerBaseUnit, $asOf, $explain);
        $listPrice = $resolved['price'];

        $contractPrice = $customer ? $this->resolveContractPrice($product, $uom, $customer, $asOf, $explain) : null;
        $promoPrice = $this->resolvePromotionPrice($product, $uom, $customer, $branchId, $listPrice, $asOf, $explain);

        $candidates = array_filter([
            'CUSTOMER_CONTRACT' => $contractPrice,
            'PROMOTION' => $promoPrice,
        ], fn ($p) => $p !== null);

        $base = [
            'list_price' => $listPrice,
            'rank_price' => $resolved['rank_price'],
            'prices_include_tax' => $resolved['prices_include_tax'],
            'price_list_id' => $resolved['price_list_id'],
        ];

        if ($candidates === []) {
            return $base + [
                'unit_price' => $listPrice,
                'source' => 'LIST_PRICE',
                'explain' => $explain,
            ];
        }

        $bestSource = array_keys($candidates, min($candidates))[0];
        $explain[] = "Best of contract/promotion vs list ({$listPrice}): {$bestSource} at {$candidates[$bestSource]}";

        return $base + [
            'unit_price' => $candidates[$bestSource],
            'source' => $bestSource,
            'explain' => $explain,
        ];
    }

    private function resolveContractPrice(Product $product, ProductUom $uom, Customer $customer, Carbon $asOf, array &$explain): ?string
    {
        $asOfDate = $asOf->toDateString();

        $row = $customer->contractPrices()
            ->where('product_id', $product->id)
            ->where('uom_id', $uom->uom_id)
            ->whereDate('effective_from', '<=', $asOfDate)
            ->whereDate('effective_to', '>=', $asOfDate)
            ->orderByDesc('effective_from')
            ->first();

        if ($row) {
            $explain[] = "Rank 1 (contract): {$row->contract_ref} @ {$row->unit_price}";

            return (string) $row->unit_price;
        }

        return null;
    }

    private function resolvePromotionPrice(
        Product $product, ProductUom $uom, ?Customer $customer, ?string $branchId,
        string $listPrice, Carbon $asOf, array &$explain,
    ): ?string {
        $asOfDate = $asOf->toDateString();

        $promotions = Promotion::query()
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $asOfDate)
            ->whereDate('effective_to', '>=', $asOfDate)
            ->where(fn ($q) => $q->whereNull('customer_scope')->orWhere('customer_scope', $customer?->id))
            ->where(fn ($q) => $q->whereNull('branch_scope')->orWhere('branch_scope', $branchId))
            ->get();

        foreach ($promotions as $promotion) {
            $line = $promotion->lines()->where('product_id', $product->id)->where('uom_id', $uom->uom_id)->first();
            if (! $line) {
                continue;
            }

            $price = match ($promotion->promo_type) {
                'PRICE_OVERRIDE' => $line->promo_price !== null ? (string) $line->promo_price : null,
                'PERCENT_OFF' => $line->discount_pct !== null
                    ? bcmul($listPrice, bcsub('1', bcdiv((string) $line->discount_pct, '100', 6), 6), 4)
                    : null,
                default => null, // BUY_X_GET_Y affects quantity/bonus, not unit price — see BonusResolver
            };

            if ($price !== null) {
                $explain[] = "Rank 2 (promotion {$promotion->code}): {$price}";

                return $price;
            }
        }

        return null;
    }

    /**
     * @return array{price: string, rank_price: string, prices_include_tax: bool, price_list_id: ?string}
     */
    private function resolveListPrice(
        Product $product, ProductUom $uom, string $qty, ?Customer $customer,
        string $saleMode, ?string $branchId, ?string $costPerBaseUnit, Carbon $asOf, array &$explain,
    ): array {
        // Rank 3: customer tier price list.
        if ($customer?->tier_id) {
            $row = $this->findProductPrice($product, $uom, $asOf, fn ($q) => $q->where('tier_id', $customer->tier_id));
            if ($row) {
                $explain[] = 'Rank 3 (customer tier price list)';

                return $this->fromPriceRow($row, $qty, $costPerBaseUnit, $product, $explain);
            }
        }

        // Rank 4: mode price list (retail/wholesale standard list).
        $row = $this->findProductPrice($product, $uom, $asOf, fn ($q) => $q->where('sale_mode', $saleMode)->whereNull('tier_id')->whereNull('branch_id'));
        if ($row) {
            $explain[] = "Rank 4 (mode price list: {$saleMode})";

            return $this->fromPriceRow($row, $qty, $costPerBaseUnit, $product, $explain);
        }

        // Rank 5: branch override.
        if ($branchId) {
            $row = $this->findProductPrice($product, $uom, $asOf, fn ($q) => $q->where('branch_id', $branchId));
            if ($row) {
                $explain[] = 'Rank 5 (branch override)';

                return $this->fromPriceRow($row, $qty, $costPerBaseUnit, $product, $explain);
            }
        }

        // Rank 6: product default — the fallback that should rarely be hit.
        // default_price is per base unit (Part 5.6: derived per UOM).
        $explain[] = 'Rank 6 (product default price) — fallback, should rarely be hit in production';
        $perUom = bcmul((string) ($product->default_price ?? '0.0000'), (string) $uom->factor_to_base, 4);

        return ['price' => $perUom, 'rank_price' => $perUom, 'prices_include_tax' => false, 'price_list_id' => null];
    }

    /**
     * @return array{price: string, rank_price: string, prices_include_tax: bool, price_list_id: ?string}
     */
    private function fromPriceRow(ProductPrice $row, string $qty, ?string $costPerBaseUnit, Product $product, array &$explain): array
    {
        $rankPrice = $this->resolveFactorPrice($row, $costPerBaseUnit, $product);
        $price = $this->applyBreaks($row, $rankPrice, $qty, $explain);

        return [
            'price' => $price,
            'rank_price' => $rankPrice,
            'prices_include_tax' => (bool) ($row->priceList->prices_include_tax ?? false),
            'price_list_id' => $row->price_list_id,
        ];
    }

    private function findProductPrice(Product $product, ProductUom $uom, Carbon $asOf, \Closure $priceListScope): ?ProductPrice
    {
        $asOfDate = $asOf->toDateString();

        return $product->prices()
            ->with('priceList')
            ->where('uom_id', $uom->uom_id)
            ->whereDate('effective_from', '<=', $asOfDate)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $asOfDate))
            ->whereHas('priceList', function ($q) use ($priceListScope, $asOfDate) {
                $priceListScope($q);
                $q->where('is_active', true)
                    ->whereDate('effective_from', '<=', $asOfDate)
                    ->where(fn ($q2) => $q2->whereNull('effective_to')->orWhereDate('effective_to', '>=', $asOfDate));
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Step 2 — quantity breaks. STEP prices the whole quantity at the tier it
     * lands in; MARGINAL accumulates band by band like a tariff (Part 4.4).
     */
    private function applyBreaks(ProductPrice $row, string $unitPrice, string $qty, array &$explain): string
    {
        $break = $row->priceBreaks()
            ->where('min_qty', '<=', $qty)
            ->where(fn ($q) => $q->whereNull('max_qty')->orWhere('max_qty', '>=', $qty))
            ->orderByDesc('min_qty')
            ->first();

        if (! $break) {
            return $unitPrice;
        }

        if ($break->break_type === 'STEP') {
            $explain[] = "Quantity break (STEP) {$break->min_qty}–".($break->max_qty ?? '∞').": qty {$qty} -> {$break->unit_price}/unit";

            return (string) $break->unit_price;
        }

        $allBreaks = $row->priceBreaks()->orderBy('min_qty')->get();
        $total = '0.0000';
        $remaining = $qty;

        foreach ($allBreaks as $band) {
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }
            $bandSize = $band->max_qty !== null
                ? bcadd(bcsub((string) $band->max_qty, (string) $band->min_qty, 4), '1', 4)
                : $remaining;
            $qtyInBand = bccomp($remaining, $bandSize, 4) < 0 ? $remaining : $bandSize;
            $total = bcadd($total, bcmul($qtyInBand, (string) $band->unit_price, 4), 4);
            $remaining = bcsub($remaining, $qtyInBand, 4);
        }

        $explain[] = "Quantity break (MARGINAL): total {$total} for qty {$qty}";

        return bccomp($qty, '0', 4) > 0 ? bcdiv($total, $qty, 4) : $unitPrice;
    }

    private function resolveFactorPrice(ProductPrice $row, ?string $costPerBaseUnit, Product $product): string
    {
        return match ($row->factor_type) {
            'FIXED' => (string) $row->unit_price,
            'COST_PLUS_MARKUP' => $this->requireCost($costPerBaseUnit, fn ($cost) => bcmul($cost, bcadd('1', (string) $row->factor_value, 6), 4)),
            'TARGET_MARGIN' => $this->requireCost($costPerBaseUnit, fn ($cost) => bcdiv($cost, bcsub('1', (string) $row->factor_value, 6), 4)),
            // Simplification: "the base list price" for LIST_RELATIVE is the
            // product's own default_price — the blueprint names the concept
            // but not which list is the master when several exist.
            'LIST_RELATIVE' => bcmul((string) ($product->default_price ?? '0'), (string) $row->factor_value, 4),
            default => (string) $row->unit_price,
        };
    }

    private function requireCost(?string $costPerBaseUnit, \Closure $compute): string
    {
        if ($costPerBaseUnit === null) {
            throw new \RuntimeException('A cost-plus/target-margin price requires a cost basis (WAC) — none was supplied.');
        }

        return $compute($costPerBaseUnit);
    }
}
