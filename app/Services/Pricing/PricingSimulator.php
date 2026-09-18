<?php

namespace App\Services\Pricing;

/**
 * Part 4.10 — the price modelling screen's arithmetic, in one pure
 * function so the UI, the tests and the blueprint's worked examples all
 * run the same numbers. No database access; nothing is posted.
 */
class PricingSimulator
{
    /**
     * @param  array{
     *     cost: string, list_price: string, quantity?: string, discount_pct?: string,
     *     min_margin_pct?: string, bonus_buy_qty?: string, bonus_free_qty?: string,
     *     funded_by?: string, monthly_volume?: string, round_to?: string,
     * }  $in
     * @return array<string, mixed>
     */
    public function simulate(array $in): array
    {
        $cost = bcadd((string) $in['cost'], '0', 4);
        $list = bcadd((string) $in['list_price'], '0', 4);
        $qty = bcadd((string) ($in['quantity'] ?? '1'), '0', 4);
        $discountPct = bcadd((string) ($in['discount_pct'] ?? '0'), '0', 4);
        $minMargin = bcadd((string) ($in['min_margin_pct'] ?? '0'), '0', 4);
        $bonusBuy = bcadd((string) ($in['bonus_buy_qty'] ?? '0'), '0', 4);
        $bonusFree = bcadd((string) ($in['bonus_free_qty'] ?? '0'), '0', 4);
        $fundedBy = $in['funded_by'] ?? 'US';
        $monthly = bcadd((string) ($in['monthly_volume'] ?? '0'), '0', 4);
        $roundTo = (string) ($in['round_to'] ?? '0.01');

        $requestedPrice = Money::round(bcmul($list, bcsub('1', bcdiv($discountPct, '100', 6), 6), 6), 2);
        $floorPrice = bccomp($minMargin, '0', 4) > 0
            ? Money::round(bcdiv($cost, bcsub('1', bcdiv($minMargin, '100', 6), 6), 6), 2)
            : null;

        $floorBreached = $floorPrice !== null && bccomp($requestedPrice, $floorPrice, 4) < 0;
        $unitPrice = $floorBreached ? $floorPrice : $requestedPrice;
        $rounded = Money::roundToStep($unitPrice, $roundTo);
        if ($floorPrice !== null && bccomp($rounded, $floorPrice, 4) < 0) {
            $rounded = Money::ceilToStep($floorPrice, $roundTo);
        }
        $unitPrice = Money::round($rounded, 2);

        $appliedDiscountPct = bccomp($list, '0', 4) > 0 ? Money::pct(bcsub($list, $unitPrice, 4), $list) : '0.0000';
        $maxAllowableDiscountPct = $floorPrice !== null && bccomp($list, '0', 4) > 0 ? Money::pct(bcsub($list, $floorPrice, 4), $list) : null;

        $bonusUnits = '0.0000';
        if (bccomp($bonusBuy, '0', 4) > 0 && bccomp($bonusFree, '0', 4) > 0) {
            $bonusUnits = bcmul(bcdiv($qty, $bonusBuy, 0), $bonusFree, 4);
        }

        $revenue = Money::round(bcmul($unitPrice, $qty, 6), 2);
        $lineCost = bcmul($cost, $qty, 4);
        // Supplier-funded bonus: the free units cost us nothing (Part 4.6).
        $bonusCost = $fundedBy === 'SUPPLIER' ? '0.0000' : bcmul($cost, $bonusUnits, 4);
        $grossProfit = bcsub($revenue, $lineCost, 4);
        $effectiveProfit = bcsub($grossProfit, $bonusCost, 4);

        $marginPct = bccomp($revenue, '0', 4) > 0 ? Money::pct($grossProfit, $revenue) : '0.0000';
        $markupPct = bccomp($lineCost, '0', 4) > 0 ? Money::pct($grossProfit, $lineCost) : '0.0000';
        $effectiveMarginPct = bccomp($revenue, '0', 4) > 0 ? Money::pct($effectiveProfit, $revenue) : '0.0000';

        // Part 4.10 / 24.7 — break-even: discount% ÷ (margin% − discount%).
        $undiscountedMargin = bccomp($list, '0', 4) > 0 ? Money::pct(bcsub($list, $cost, 4), $list) : '0.0000';
        $breakEvenIncrease = null;
        if (bccomp($appliedDiscountPct, '0', 4) > 0 && bccomp($undiscountedMargin, $appliedDiscountPct, 4) > 0) {
            $breakEvenIncrease = bcmul(bcdiv($appliedDiscountPct, bcsub($undiscountedMargin, $appliedDiscountPct, 6), 8), '100', 2);
        }

        $profitPerUnit = bcsub($unitPrice, $cost, 4);
        $undiscountedProfitPerUnit = bcsub($list, $cost, 4);
        $monthlyProfit = bcmul($profitPerUnit, $monthly, 2);
        $monthlyProfitAtList = bcmul($undiscountedProfitPerUnit, $monthly, 2);

        return [
            'unit_price' => $unitPrice,
            'requested_price' => $requestedPrice,
            'applied_discount_pct' => $appliedDiscountPct,
            'floor_price' => $floorPrice,
            'floor_breached' => $floorBreached,
            'max_allowable_discount_pct' => $maxAllowableDiscountPct,
            'quantity' => $qty,
            'bonus_units' => $bonusUnits,
            'units_issued' => bcadd($qty, $bonusUnits, 4),
            'revenue' => $revenue,
            'line_cost' => $lineCost,
            'bonus_cost' => $bonusCost,
            'gross_profit' => $grossProfit,
            'margin_pct' => $marginPct,
            'markup_pct' => $markupPct,
            'effective_margin_pct' => $effectiveMarginPct,
            'undiscounted_margin_pct' => $undiscountedMargin,
            'break_even_volume_increase_pct' => $breakEvenIncrease,
            'monthly_profit' => $monthlyProfit,
            'monthly_profit_at_list' => $monthlyProfitAtList,
            'monthly_profit_delta' => bcsub($monthlyProfit, $monthlyProfitAtList, 2),
        ];
    }
}
