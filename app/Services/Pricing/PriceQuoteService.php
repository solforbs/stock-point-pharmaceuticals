<?php

namespace App\Services\Pricing;

use App\Models\Customer;
use App\Models\PriceQuoteLog;
use App\Models\Product;
use App\Models\ProductDiscountPolicy;
use App\Models\ProductUom;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class QuoteNotFoundException extends \RuntimeException {}

class QuoteExpiredException extends \RuntimeException
{
    public function __construct(public readonly string $quoteId, public readonly Carbon $expiredAt)
    {
        parent::__construct("Quote {$quoteId} expired at {$expiredAt->toIso8601String()} — re-quote required.");
    }
}

class PriceChangedException extends \RuntimeException
{
    /**
     * @param  list<array<string, mixed>>  $changedLines
     * @param  array<string, mixed>  $newQuote
     */
    public function __construct(
        public readonly string $quoteId,
        public readonly string $oldTotal,
        public readonly string $newTotal,
        public readonly array $changedLines,
        public readonly array $newQuote,
    ) {
        parent::__construct("Prices changed since quote {$quoteId} was issued: {$oldTotal} -> {$newTotal}.");
    }
}

/**
 * The seven-step, server-authoritative price quote (Part 4.1 / V6 8.2):
 *
 *   1. BASE PRICE    → PricingEngine (six-rank hierarchy)
 *   2. QTY BREAK     → PricingEngine
 *   3. DISCOUNTS     → product policy × tier ceiling × role authority, most restrictive wins
 *   4. BONUS         → BonusResolver (free goods as quantity, never as discount)
 *   5. MARGIN GUARD  → floor_price = cost ÷ (1 − min_margin_pct) — hard stop
 *   6. TAX           → TaxResolver, line level, on net after discount
 *   7. ROUNDING      → product round_to, applied last, floor re-checked
 *
 * Every quote is written to price_quote_logs (immutable) and carries an
 * explain[] trail. The client never calculates the final price: checkout
 * re-validates the quote_id and refuses to post if anything moved.
 */
class PriceQuoteService
{
    private const ROUND_STEPS = [
        'NONE' => '0.01',
        'FIVE_CENTS' => '0.05',
        'TEN_CENTS' => '0.10',
        'FIFTY_CENTS' => '0.50',
        'WHOLE' => '1.00',
    ];

    private const INSTITUTIONAL_CUSTOMER_TYPES = ['HOSPITAL', 'CLINIC', 'NGO', 'GOVERNMENT', 'TENDER', 'INSTITUTION'];

    public function __construct(
        private readonly PricingEngine $engine,
        private readonly CostBasis $costBasis,
        private readonly DiscountAuthority $authority,
        private readonly TaxResolver $tax,
        private readonly BonusResolver $bonus,
    ) {}

    /**
     * @param  array{
     *     organisation_id: string, branch_id: string, store_id: string, sale_mode: string,
     *     customer_id?: ?string, user_id: int, quote_date?: ?string,
     *     header_discount?: ?string, header_discount_reason?: ?string,
     *     lines: list<array{
     *         line_ref?: string, product_id: string, uom_id: string, quantity: string,
     *         requested_discount_pct?: ?string, requested_discount_amount?: ?string, requested_discount_reason?: ?string,
     *         batch_id?: ?string, override_reason?: ?string,
     *     }>,
     * }  $request
     * @return array<string, mixed>
     */
    public function quote(array $request, bool $persist = true): array
    {
        $asOf = isset($request['quote_date']) ? Carbon::parse($request['quote_date']) : now();
        $customer = ! empty($request['customer_id']) ? Customer::with('tier')->findOrFail($request['customer_id']) : null;
        $user = User::findOrFail($request['user_id']);
        $authority = $this->authority->for($user, $request['branch_id']);

        $lines = [];
        foreach ($request['lines'] as $i => $lineRequest) {
            $lines[] = $this->quoteLine($request, $lineRequest, $i, $customer, $authority, $asOf);
        }

        $headerNotes = [];
        $headerApproval = false;
        $lines = $this->applyHeaderDiscount($request, $lines, $authority, $headerNotes, $headerApproval);

        $totals = $this->totals($lines);
        $ttl = (int) Setting::resolve($request['organisation_id'], $request['branch_id'], 'pricing', 'quote_ttl_minutes', 15);

        $response = [
            'quote_id' => null,
            'expires_at' => null,
            'currency' => 'KES',
            'sale_mode' => $request['sale_mode'],
            'customer_id' => $customer?->id,
            'min_shelf_life_days' => $this->minShelfLifeDays($request['organisation_id'], $request['branch_id'], $customer),
            'lines' => $lines,
            'header_discount' => $headerNotes,
            'totals' => $totals,
            'approval_required' => $headerApproval || collect($lines)->contains(fn ($l) => $l['approval_required']),
            'floor_breached' => collect($lines)->contains(fn ($l) => $l['floor_breached']),
            'quoted_at' => now()->toIso8601String(),
        ];

        if ($persist) {
            $log = PriceQuoteLog::create([
                'quote_id' => (string) Str::uuid(),
                'user_id' => $request['user_id'],
                'customer_id' => $customer?->id,
                'branch_id' => $request['branch_id'],
                'sale_mode' => $request['sale_mode'],
                'payload_json' => $request,
                'response_json' => $response,
                'expires_at' => now()->addMinutes($ttl),
                'created_at' => now(),
            ]);

            $response['quote_id'] = $log->quote_id;
            $response['expires_at'] = $log->expires_at->toIso8601String();
        }

        return $response;
    }

    /**
     * Re-runs the quote from its stored payload and refuses if anything
     * moved (Part 4.12: "A sale never posts at a price the server did not
     * just verify.").
     *
     * @return array{log: PriceQuoteLog, quote: array<string, mixed>}
     */
    public function revalidate(string $quoteId): array
    {
        $log = PriceQuoteLog::where('quote_id', $quoteId)->first();
        if (! $log) {
            throw new QuoteNotFoundException("Quote {$quoteId} does not exist.");
        }

        if ($log->expires_at->isPast()) {
            throw new QuoteExpiredException($quoteId, $log->expires_at);
        }

        $payload = $log->payload_json;
        unset($payload['quote_date']); // always re-validate against today's prices and costs
        $fresh = $this->quote($payload, persist: false);
        $original = $log->response_json;

        $changed = [];
        foreach ($original['lines'] as $i => $line) {
            $now = $fresh['lines'][$i] ?? null;
            foreach (['unit_price', 'bonus_qty', 'tax_amount', 'line_total'] as $field) {
                if ($now === null || bccomp((string) $line[$field], (string) $now[$field], 4) !== 0) {
                    $changed[] = ['line_ref' => $line['line_ref'], 'field' => $field, 'old' => $line[$field], 'new' => $now[$field] ?? null];
                }
            }
        }

        if ($changed !== [] || bccomp((string) $original['totals']['grand_total'], (string) $fresh['totals']['grand_total'], 4) !== 0) {
            throw new PriceChangedException($quoteId, (string) $original['totals']['grand_total'], (string) $fresh['totals']['grand_total'], $changed, $fresh);
        }

        return ['log' => $log, 'quote' => $original];
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function quoteLine(array $request, array $line, int $index, ?Customer $customer, Authority $authority, Carbon $asOf): array
    {
        $product = Product::findOrFail($line['product_id']);
        $uom = ProductUom::where('product_id', $product->id)->where('uom_id', $line['uom_id'])->first();
        if (! $uom) {
            throw new \InvalidArgumentException("Product {$product->code} cannot be sold in that unit of measure.");
        }
        if (! $uom->is_sales) {
            throw new \InvalidArgumentException("{$uom->uom->code} is not a sales unit for {$product->code}.");
        }

        $qty = bcadd((string) $line['quantity'], '0', 4);
        if (bccomp($qty, '0', 4) <= 0) {
            throw new \InvalidArgumentException("Quantity must be positive for {$product->code}.");
        }
        if ($product->is_discrete && bccomp($qty, bcdiv($qty, '1', 0), 4) !== 0) {
            throw new \InvalidArgumentException("{$product->code} is sold in whole {$uom->uom->code} only (Part 5.5); {$qty} is not a whole number.");
        }

        $explain = [];
        $factor = (string) $uom->factor_to_base;
        $costBase = $this->costBasis->weightedAverageCost($product->id, $request['store_id']);
        $costPerUom = $costBase !== null ? bcmul($costBase, $factor, 4) : null;

        // Steps 1 & 2 — base price and quantity break.
        $engine = $this->engine->quote($product, $uom, $qty, $customer, $request['sale_mode'], $request['branch_id'], $costBase, $asOf);
        foreach ($engine['explain'] as $e) {
            $explain[] = $e;
        }

        // Step 6 (resolution only) — the rate is needed now to net a tax-inclusive list.
        $tax = $this->tax->resolve($product, $customer, $asOf);
        $rateFraction = bcdiv($tax['rate_pct'], '100', 6);

        $basePrice = Money::round($engine['unit_price'], 2);
        if ($engine['prices_include_tax'] && bccomp($rateFraction, '0', 6) > 0) {
            $gross = $basePrice;
            $basePrice = Money::round(bcdiv($gross, bcadd('1', $rateFraction, 6), 6), 2);
            $explain[] = "Price list is tax-inclusive: {$gross} gross → {$basePrice} net of {$tax['rate_pct']}% {$tax['tax_code']}";
        }
        $explain[] = "Base price → {$basePrice}".($engine['rank_price'] !== $engine['list_price'] ? " (list {$engine['rank_price']} before quantity break)" : '');

        // Step 3 — discount controls: most restrictive of product, tier, user.
        $policy = ProductDiscountPolicy::where('product_id', $product->id)->first();
        $org = $request['organisation_id'];
        $branch = $request['branch_id'];
        $discountAllowed = $policy ? (bool) $policy->discount_allowed : true;
        $productMax = (string) ($policy->max_discount_pct ?? Setting::resolve($org, $branch, 'pricing', 'default_max_discount_pct', '0'));
        $minMargin = (string) ($policy->min_margin_pct ?? Setting::resolve($org, $branch, 'pricing', 'default_min_margin_pct', '0'));
        $approvalPct = $policy->discount_approval_pct ?? Setting::resolve($org, $branch, 'pricing', 'default_discount_approval_pct', null);
        $roundStep = self::ROUND_STEPS[$policy->round_to ?? Setting::resolve($org, $branch, 'pricing', 'round_to', 'NONE')] ?? '0.01';
        $tierMax = $customer?->tier?->max_discount_pct;

        $requestedPct = '0.0000';
        if (! empty($line['requested_discount_pct'])) {
            $requestedPct = bcadd((string) $line['requested_discount_pct'], '0', 4);
        } elseif (! empty($line['requested_discount_amount']) && bccomp($basePrice, '0', 4) > 0) {
            $requestedPct = Money::pct((string) $line['requested_discount_amount'], $basePrice);
        }

        $discountSource = 'NONE';
        $appliedPct = '0.0000';
        $cappedBy = null;

        if (bccomp($requestedPct, '0', 4) > 0) {
            $limits = ['product policy' => $productMax, 'your authority' => $authority->maxLineDiscountPct];
            if ($tierMax !== null) {
                $limits['customer tier'] = (string) $tierMax;
            }
            if (! $discountAllowed) {
                $limits = ['product policy (discounts not allowed)' => '0'];
            }
            asort($limits, SORT_NUMERIC);
            $effectiveLimit = bcadd((string) reset($limits), '0', 4);
            $limitName = (string) array_key_first($limits);

            if (bccomp($requestedPct, $effectiveLimit, 4) > 0) {
                $appliedPct = $effectiveLimit;
                $cappedBy = $limitName;
                $discountSource = 'MANUAL_CAPPED_BY_AUTHORITY';
                $explain[] = "Manual discount requested {$requestedPct}% → capped at {$effectiveLimit}% by {$limitName}";
            } else {
                $appliedPct = $requestedPct;
                $discountSource = 'MANUAL';
                $explain[] = "Manual discount requested {$requestedPct}% (limit {$effectiveLimit}% from {$limitName})";
            }
        }

        $discountPerUnit = Money::round(bcmul($basePrice, bcdiv($appliedPct, '100', 6), 6), 2);
        if ($policy?->max_discount_amount !== null && bccomp($discountPerUnit, (string) $policy->max_discount_amount, 4) > 0) {
            $discountPerUnit = Money::round((string) $policy->max_discount_amount, 2);
            $cappedBy = 'product absolute ceiling';
            $discountSource = 'MANUAL_CAPPED_BY_AUTHORITY';
            $explain[] = "Discount capped at KES {$discountPerUnit}/unit by product absolute ceiling";
        }
        $unitPrice = bcsub($basePrice, $discountPerUnit, 4);

        // Step 5 — the margin floor, the guard that actually protects the business.
        $floorPrice = null;
        $floorBreached = false;
        if ($costPerUom !== null && bccomp($minMargin, '0', 3) > 0) {
            $floorPrice = Money::round(bcdiv($costPerUom, bcsub('1', bcdiv($minMargin, '100', 6), 6), 6), 2);
        }
        $overrideReason = $line['requested_discount_reason'] ?? null;
        if ($floorPrice !== null && bccomp($unitPrice, $floorPrice, 4) < 0) {
            if ($authority->mayOverrideFloor && $overrideReason) {
                $floorBreached = true;
                $discountSource = 'MANUAL_FLOOR_OVERRIDE';
                $explain[] = "⚠ Below margin floor {$minMargin}% = {$floorPrice}: overridden by authority — \"{$overrideReason}\"";
            } else {
                $explain[] = "⚠ Margin floor {$minMargin}% requires minimum {$floorPrice} → discount capped at ".Money::pct(bcsub($basePrice, $floorPrice, 4), $basePrice).'%';
                $unitPrice = $floorPrice;
                $discountSource = 'MANUAL_CAPPED_BY_FLOOR';
                $cappedBy = 'margin floor';
            }
        }

        // Step 7 — rounding, applied last; the floor is re-checked after it.
        if (bccomp($unitPrice, '0', 4) > 0) {
            $rounded = Money::roundToStep($unitPrice, $roundStep);
            if ($floorPrice !== null && ! $floorBreached && bccomp($rounded, $floorPrice, 4) < 0) {
                $rounded = Money::ceilToStep($floorPrice, $roundStep);
            }
            if (bccomp($rounded, $unitPrice, 4) !== 0) {
                $explain[] = "Rounded to {$roundStep} → {$rounded}";
            }
            $unitPrice = Money::round($rounded, 2);
        }

        $discountPerUnit = bcsub($basePrice, $unitPrice, 4);
        $appliedPct = bccomp($basePrice, '0', 4) > 0 ? Money::pct($discountPerUnit, $basePrice) : '0.0000';
        if (bccomp($discountPerUnit, '0', 4) <= 0 && $discountSource !== 'NONE') {
            $discountSource = 'NONE';
        }

        $approvalRequired = $floorBreached
            || ($approvalPct !== null && bccomp($appliedPct, (string) $approvalPct, 4) > 0);
        if ($approvalRequired && ! $floorBreached) {
            $explain[] = "Discount {$appliedPct}% exceeds approval threshold {$approvalPct}% → approval required";
        }

        // Step 4 — bonus (free goods).
        $bonus = ($policy === null || $policy->bonus_allowed)
            ? $this->bonus->resolve($product, $uom, $qty, $customer, $branch, $asOf)
            : ['bonus_qty' => '0.0000', 'bonus_product_id' => null, 'promotion_id' => null, 'promotion_code' => null, 'funded_by' => null, 'explain' => null];
        if ($bonus['explain']) {
            $explain[] = $bonus['explain'];
        }

        // Step 6 — tax at line level on the net after discount.
        $lineSubtotal = Money::round(bcmul($basePrice, $qty, 6), 2);
        $discountAmount = Money::round(bcmul($discountPerUnit, $qty, 6), 2);
        $netAmount = bcsub($lineSubtotal, $discountAmount, 4);
        $taxAmount = Money::round(bcmul($netAmount, $rateFraction, 6), 2);
        $lineTotal = bcadd($netAmount, $taxAmount, 4);
        $explain[] = "Tax: {$tax['reason']} → {$taxAmount}";

        // Margin — on WAC here; batch cost is what actually posts (Part 4.3).
        $lineCost = $costPerUom !== null ? bcmul($costPerUom, $qty, 4) : null;
        $bonusCost = $costPerUom !== null && $bonus['bonus_product_id'] === null ? bcmul($costPerUom, $bonus['bonus_qty'], 4) : '0.0000';
        $grossProfit = $lineCost !== null ? bcsub($netAmount, $lineCost, 4) : null;
        $marginPct = $grossProfit !== null && bccomp($netAmount, '0', 4) > 0 ? Money::pct($grossProfit, $netAmount) : null;
        $markupPct = $grossProfit !== null && bccomp($lineCost, '0', 4) > 0 ? Money::pct($grossProfit, $lineCost) : null;
        $effectiveMargin = $grossProfit !== null && bccomp($netAmount, '0', 4) > 0
            ? Money::pct(bcsub($grossProfit, $bonusCost, 4), $netAmount)
            : null;
        if ($marginPct !== null) {
            $explain[] = "Margin {$marginPct}% · Markup {$markupPct}% · Profit ".bcadd($grossProfit, '0', 2).($bonusCost !== '0.0000' ? " (effective margin after bonus {$effectiveMargin}%)" : '');
        }

        return [
            'line_ref' => $line['line_ref'] ?? 'L'.($index + 1),
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'uom_id' => $uom->uom_id,
            'uom_code' => $uom->uom->code,
            'factor_to_base' => (int) $uom->factor_to_base,
            'quantity' => $qty,
            'qty_base' => bcmul($qty, $factor, 4),
            'list_price' => $engine['rank_price'],
            'break_price' => $basePrice,
            'price_source' => $engine['source'],
            'requested_discount_pct' => $requestedPct,
            'unit_price' => $unitPrice,
            'discount_per_unit' => $discountPerUnit,
            'discount_pct' => $appliedPct,
            'discount_amount' => $discountAmount,
            'discount_source' => $discountSource,
            'discount_capped_by' => $cappedBy,
            'discount_reason' => $overrideReason,
            'line_subtotal' => $lineSubtotal,
            'net_amount' => $netAmount,
            'tax_code_id' => $tax['tax_code_id'],
            'tax_code' => $tax['tax_code'],
            'tax_rate' => $tax['rate_pct'],
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
            'bonus_qty' => $bonus['bonus_qty'],
            'bonus_product_id' => $bonus['bonus_product_id'] ?? ($bonus['bonus_qty'] !== '0.0000' ? $product->id : null),
            'bonus_promotion_id' => $bonus['promotion_id'],
            'bonus_funded_by' => $bonus['funded_by'],
            'unit_cost' => $costPerUom,
            'line_cost' => $lineCost,
            'bonus_cost' => $bonusCost,
            'gross_profit' => $grossProfit,
            'margin_pct' => $marginPct,
            'markup_pct' => $markupPct,
            'effective_margin_pct' => $effectiveMargin,
            'floor_price' => $floorPrice,
            'floor_breached' => $floorBreached,
            'approval_required' => $approvalRequired,
            'batch_id' => $line['batch_id'] ?? null,
            'override_reason' => $line['override_reason'] ?? null,
            'explain' => $explain,
        ];
    }

    /**
     * Part 4.5 header discount: allocated pro-rata by net line value to 2dp,
     * the last line absorbs the rounding difference, and every line is
     * re-checked against its own floor afterwards.
     *
     * @param  array<string, mixed>  $request
     * @param  list<array<string, mixed>>  $lines
     * @param  list<string>  $notes
     * @return list<array<string, mixed>>
     */
    private function applyHeaderDiscount(array $request, array $lines, Authority $authority, array &$notes, bool &$approvalRequired): array
    {
        $headerDiscount = isset($request['header_discount']) ? bcadd((string) $request['header_discount'], '0', 2) : '0.00';
        if (bccomp($headerDiscount, '0', 2) <= 0 || $lines === []) {
            return $lines;
        }

        $netTotal = array_reduce($lines, fn ($c, $l) => bcadd($c, $l['net_amount'], 4), '0.0000');
        if (bccomp($netTotal, '0', 4) <= 0) {
            return $lines;
        }

        $requestedPct = Money::pct($headerDiscount, $netTotal);
        if (bccomp($requestedPct, $authority->maxHeaderDiscountPct, 4) > 0) {
            $headerDiscount = Money::round(bcmul($netTotal, bcdiv($authority->maxHeaderDiscountPct, '100', 6), 6), 2);
            $notes[] = "Header discount requested {$requestedPct}% → capped at {$authority->maxHeaderDiscountPct}% (KES {$headerDiscount}) by your authority";
            $approvalRequired = true;
        } else {
            $notes[] = "Header discount KES {$headerDiscount} ({$requestedPct}%) allocated pro-rata by line value";
        }

        $allocated = '0.00';
        $last = count($lines) - 1;
        foreach ($lines as $i => &$line) {
            $share = $i === $last
                ? bcadd(bcsub($headerDiscount, $allocated, 2), '0', 4)
                : Money::round(bcmul($headerDiscount, bcdiv($line['net_amount'], $netTotal, 8), 6), 2);
            $allocated = bcadd($allocated, $share, 2);

            $line['header_discount_allocated'] = $share;
            $line['discount_amount'] = bcadd($line['discount_amount'], $share, 4);
            $line['net_amount'] = bcsub($line['line_subtotal'], $line['discount_amount'], 4);
            $line['unit_price'] = bcdiv($line['net_amount'], $line['quantity'], 4);
            $line['discount_per_unit'] = bcsub($line['break_price'], $line['unit_price'], 4);
            $line['discount_pct'] = Money::pct($line['discount_per_unit'], $line['break_price']);
            $line['tax_amount'] = Money::round(bcmul($line['net_amount'], bcdiv($line['tax_rate'], '100', 6), 6), 2);
            $line['line_total'] = bcadd($line['net_amount'], $line['tax_amount'], 4);
            $line['discount_source'] = $line['discount_source'] === 'NONE' ? 'HEADER' : $line['discount_source'].'+HEADER';
            $line['explain'][] = "Header discount allocated KES {$share} → unit {$line['unit_price']}";

            if ($line['line_cost'] !== null) {
                $line['gross_profit'] = bcsub($line['net_amount'], $line['line_cost'], 4);
                $line['margin_pct'] = Money::pct($line['gross_profit'], $line['net_amount']);
                $line['markup_pct'] = bccomp($line['line_cost'], '0', 4) > 0 ? Money::pct($line['gross_profit'], $line['line_cost']) : null;
                $line['effective_margin_pct'] = Money::pct(bcsub($line['gross_profit'], $line['bonus_cost'], 4), $line['net_amount']);
            }

            if ($line['floor_price'] !== null && ! $line['floor_breached'] && bccomp($line['unit_price'], $line['floor_price'], 4) < 0) {
                $line['floor_breached'] = true;
                $line['approval_required'] = true;
                $line['explain'][] = "⚠ Header discount pushes this line below its floor {$line['floor_price']} — approval required";
            }
        }
        unset($line);

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function totals(array $lines): array
    {
        $sum = fn (string $key) => array_reduce($lines, fn ($c, $l) => bcadd($c, (string) ($l[$key] ?? '0'), 4), '0.0000');

        $subtotal = $sum('line_subtotal');
        $discount = $sum('discount_amount');
        $tax = $sum('tax_amount');
        $net = bcsub($subtotal, $discount, 4);
        $grand = bcadd($net, $tax, 4);
        $costKnown = ! collect($lines)->contains(fn ($l) => $l['line_cost'] === null);
        $cost = $costKnown ? bcadd($sum('line_cost'), $sum('bonus_cost'), 4) : null;
        $profit = $cost !== null ? bcsub($net, $cost, 4) : null;

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'net' => $net,
            'tax' => $tax,
            'grand_total' => $grand,
            'total_cost' => $cost,
            'gross_profit' => $profit,
            'margin_pct' => $profit !== null && bccomp($net, '0', 4) > 0 ? Money::pct($profit, $net) : null,
        ];
    }

    /**
     * Part 8.3 / V6 16.2 — institutional customers reject short-dated stock;
     * the shelf-life rule is per customer type, configurable in settings.
     */
    public function minShelfLifeDays(string $organisationId, ?string $branchId, ?Customer $customer): int
    {
        $default = (int) Setting::resolve($organisationId, $branchId, 'sales', 'min_shelf_life_days', 90);

        if ($customer && in_array($customer->customer_type, self::INSTITUTIONAL_CUSTOMER_TYPES, true)) {
            return (int) Setting::resolve($organisationId, $branchId, 'sales', 'min_shelf_life_days_institutional', 180);
        }

        return $default;
    }
}
