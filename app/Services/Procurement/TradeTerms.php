<?php

namespace App\Services\Procurement;

use App\Services\Pricing\Money;

/**
 * A distributor's terms as they quote them: a gross trade price less a
 * purchase discount. The net cost (trade × (1 − discount%)) is only the
 * default — the receiver may still key the figure on the supplier's
 * invoice, and that keyed figure is what posts.
 */
final class TradeTerms
{
    /** Net unit cost from a trade price and discount, rounded half-up to the cent. */
    public static function netCost(string $tradePrice, ?string $discountPct): string
    {
        $discount = $discountPct === null || $discountPct === '' ? '0' : $discountPct;
        $factor = bcsub('1', bcdiv($discount, '100', 10), 10);

        return Money::round(bcmul($tradePrice, $factor, 10), 2);
    }

    /**
     * Fills the line's cost field from its trade terms when the cost was
     * left out, and normalises the terms (a trade price with no discount
     * means 0%; a discount with no trade price is meaningless and dropped).
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    public static function applyTo(array $line, string $costField): array
    {
        $trade = isset($line['trade_price']) && $line['trade_price'] !== '' ? (string) $line['trade_price'] : null;

        if ($trade === null) {
            return ['trade_price' => null, 'discount_pct' => null] + $line;
        }

        $discount = isset($line['discount_pct']) && $line['discount_pct'] !== '' ? (string) $line['discount_pct'] : '0';
        $line['trade_price'] = $trade;
        $line['discount_pct'] = $discount;

        if (! isset($line[$costField]) || $line[$costField] === '') {
            $line[$costField] = self::netCost($trade, $discount);
        }

        return $line;
    }
}
