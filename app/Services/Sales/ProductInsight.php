<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUom;
use App\Services\Pricing\CostBasis;
use App\Services\Pricing\PriceQuoteService;
use App\Services\Pricing\TaxResolver;
use Illuminate\Support\Facades\DB;

/**
 * Everything a cashier needs beside one cart line: what it cost us, the
 * trade and retail price per unit and per pack, its VAT treatment, the
 * price it must never go below, what this customer usually pays, and what
 * else on our shelves could stand in for it.
 *
 * Prices come from the same seven-step quote the checkout uses (with
 * persist off), so the numbers shown here are the numbers that will post.
 */
class ProductInsight
{
    /** How far back "usual price" looks. */
    private const HISTORY_DAYS = 180;

    private const MAX_ALTERNATIVES = 5;

    public function __construct(
        private readonly PriceQuoteService $quotes,
        private readonly CostBasis $costBasis,
        private readonly TaxResolver $tax,
    ) {}

    /**
     * @param  array{organisation_id: string, branch_id: string, store_id: string, user_id: int, customer_id?: ?string}  $context
     * @return array<string, mixed>
     */
    public function for(Product $product, array $context, bool $canSeeCost): array
    {
        $product->loadMissing(['uoms.uom', 'taxCode', 'discountPolicy']);
        $customer = ! empty($context['customer_id']) ? Customer::find($context['customer_id']) : null;

        $salesUoms = $product->uoms->where('is_sales', true)->sortBy('factor_to_base')->values();
        $retail = $this->quoteEachUom($product, $salesUoms->all(), 'RETAIL', $context);
        $trade = $this->quoteEachUom($product, $salesUoms->all(), 'WHOLESALE', $context);
        $wacPerBase = $this->costBasis->weightedAverageCost($product->id, $context['store_id']);

        $uoms = $salesUoms->map(function ($uom) use ($retail, $trade, $wacPerBase, $canSeeCost) {
            $unitCost = $wacPerBase !== null ? bcmul($wacPerBase, (string) $uom->factor_to_base, 4) : null;
            $retailPrice = $retail[$uom->uom_id] ?? null;

            return [
                'uom_id' => $uom->uom_id,
                'uom_code' => $uom->uom?->code,
                'factor_to_base' => (int) $uom->factor_to_base,
                'is_default_sales' => (bool) $uom->is_default_sales,
                'retail' => $retailPrice,
                'trade' => $trade[$uom->uom_id] ?? null,
                'unit_cost' => $canSeeCost ? $unitCost : null,
                'retail_markup_pct' => $canSeeCost && $unitCost !== null && $retailPrice !== null && bccomp($unitCost, '0', 4) > 0
                    ? bcmul(bcdiv(bcsub($retailPrice['unit_price'], $unitCost, 6), $unitCost, 6), '100', 2)
                    : null,
            ];
        })->all();

        $lastPurchase = $canSeeCost ? ($this->lastPurchases([$product->id], $context['branch_id'])[$product->id] ?? null) : null;

        return [
            'product' => $product->only(['id', 'code', 'name', 'generic_name', 'strength']),
            'tax' => $this->taxTreatment($product, $customer),
            'min_margin_pct' => $product->discountPolicy?->min_margin_pct,
            'uoms' => $uoms,
            'buying' => $canSeeCost ? [
                'wac_per_base' => $wacPerBase,
                'last_purchase' => $lastPurchase,
            ] : null,
            'usual_price' => [
                'customer' => $customer ? $this->usualPrice($product->id, $context['branch_id'], $customer->id) : null,
                'everyone' => $this->usualPrice($product->id, $context['branch_id'], null),
            ],
            'alternatives' => $this->alternatives($product, $context['organisation_id'], $context['branch_id'], $context['store_id']),
        ];
    }

    /**
     * The most recent posted goods receipt per product in the branch — the
     * price the distributor last charged us. The supplier's quoted trade
     * price and purchase discount come with it when they were captured on
     * the receipt (null otherwise).
     *
     * @param  list<string>  $productIds
     * @return array<string, array{unit_cost_per_base: string, unit_cost: string, trade_price: ?string, discount_pct: ?string, uom_code: ?string, supplier: ?string, received_at: ?string, grn_number: string}>
     */
    public function lastPurchases(array $productIds, string $branchId): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = DB::table('goods_receipt_lines as l')
            ->join('goods_receipts as g', 'g.id', '=', 'l.goods_receipt_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'g.supplier_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'l.uom_id')
            ->leftJoin('product_uoms as pu', fn ($join) => $join->on('pu.product_id', '=', 'l.product_id')->on('pu.uom_id', '=', 'l.uom_id'))
            ->where('g.branch_id', $branchId)
            ->where('g.status', 'POSTED')
            ->where('l.qty_accepted', '>', 0)
            ->whereIn('l.product_id', $productIds)
            ->orderByDesc('g.received_at')
            ->orderByDesc('g.created_at')
            ->get(['l.product_id', 'l.unit_cost', 'l.trade_price', 'l.discount_pct', 'pu.factor_to_base', 'u.code as uom_code', 's.name as supplier', 'g.received_at', 'g.doc_number']);

        $latest = [];
        foreach ($rows as $row) {
            if (isset($latest[$row->product_id])) {
                continue;
            }
            $factor = (string) ($row->factor_to_base ?: 1);
            $latest[$row->product_id] = [
                'unit_cost_per_base' => bcdiv((string) $row->unit_cost, $factor, 4),
                'unit_cost' => (string) $row->unit_cost,
                'trade_price' => $row->trade_price !== null ? (string) $row->trade_price : null,
                'discount_pct' => $row->discount_pct !== null ? (string) $row->discount_pct : null,
                'uom_code' => $row->uom_code,
                'supplier' => $row->supplier,
                'received_at' => $row->received_at,
                'grn_number' => $row->doc_number,
            ];
        }

        return $latest;
    }

    /**
     * One no-discount, quantity-one quote per sales unit in a sale mode.
     *
     * @param  list<ProductUom>  $uoms
     * @param  array{organisation_id: string, branch_id: string, store_id: string, user_id: int}  $context
     * @return array<string, array{unit_price: string, gross_price: string, floor_price: ?string, source: string}>
     */
    private function quoteEachUom(Product $product, array $uoms, string $saleMode, array $context): array
    {
        if ($uoms === []) {
            return [];
        }

        try {
            $quote = $this->quotes->quote([
                'organisation_id' => $context['organisation_id'],
                'branch_id' => $context['branch_id'],
                'store_id' => $context['store_id'],
                'sale_mode' => $saleMode,
                'user_id' => $context['user_id'],
                'lines' => array_map(fn ($uom) => ['product_id' => $product->id, 'uom_id' => $uom->uom_id, 'quantity' => '1'], $uoms),
            ], persist: false);
        } catch (\RuntimeException|\InvalidArgumentException) {
            // A cost-plus list with no cost yet, say: show no price rather than a wrong one.
            return [];
        }

        $byUom = [];
        foreach ($quote['lines'] as $line) {
            $byUom[$line['uom_id']] = [
                'unit_price' => (string) $line['unit_price'],
                'gross_price' => bcadd((string) $line['unit_price'], (string) $line['tax_amount'], 4),
                'floor_price' => $line['floor_price'] !== null ? (string) $line['floor_price'] : null,
                'source' => (string) $line['price_source'],
            ];
        }

        return $byUom;
    }

    /**
     * @return array{code: ?string, name: ?string, rate_pct: string, treatment: string}
     */
    private function taxTreatment(Product $product, ?Customer $customer): array
    {
        $resolved = $this->tax->resolve($product, $customer);
        $code = strtoupper((string) $resolved['tax_code']);

        $treatment = match (true) {
            str_contains($code, 'ZERO') => 'ZERO_RATED',
            str_contains($code, 'EX') => 'EXEMPT',
            bccomp($resolved['rate_pct'], '0', 3) > 0 => 'STANDARD',
            default => 'NOT_SET',
        };

        return [
            'code' => $resolved['tax_code'],
            'name' => $product->taxCode?->name,
            'rate_pct' => $resolved['rate_pct'],
            'treatment' => $treatment,
        ];
    }

    /**
     * The price this item most often actually sold at (per unit of measure),
     * for one customer or for everyone.
     *
     * @return array{unit_price: string, uom_id: string, uom_code: ?string, times: int, last_price: string, last_sold_at: string}|null
     */
    private function usualPrice(string $productId, string $branchId, ?string $customerId): ?array
    {
        $base = DB::table('sale_lines as l')
            ->join('sales as s', 's.id', '=', 'l.sale_id')
            ->where('s.branch_id', $branchId)
            ->where('s.status', 'POSTED')
            ->where('s.posted_at', '>=', now()->subDays(self::HISTORY_DAYS))
            ->where('l.product_id', $productId)
            ->where('l.is_bonus', false)
            ->when($customerId, fn ($q) => $q->where('s.customer_id', $customerId));

        $mostFrequent = (clone $base)
            ->groupBy('l.uom_id', 'l.unit_price')
            ->selectRaw('l.uom_id, l.unit_price, COUNT(*) as times, MAX(s.posted_at) as last_at')
            ->orderByDesc('times')
            ->orderByDesc('last_at')
            ->first();

        if (! $mostFrequent) {
            return null;
        }

        $last = (clone $base)->orderByDesc('s.posted_at')->orderByDesc('s.doc_number')->first(['l.unit_price', 's.posted_at']);

        return [
            'unit_price' => (string) $mostFrequent->unit_price,
            'uom_id' => $mostFrequent->uom_id,
            'uom_code' => DB::table('units_of_measure')->where('id', $mostFrequent->uom_id)->value('code'),
            'times' => (int) $mostFrequent->times,
            'last_price' => (string) $last->unit_price,
            'last_sold_at' => (string) $last->posted_at,
        ];
    }

    /**
     * In-stock substitutes: the same molecule first, then the same category
     * and dosage form. Rule-based on the catalogue, so it is only as good as
     * the generic names and categories recorded on each product.
     *
     * @return list<array<string, mixed>>
     */
    private function alternatives(Product $product, string $organisationId, string $branchId, string $storeId): array
    {
        $candidates = Product::query()
            ->where('organisation_id', $organisationId)
            ->where('is_active', true)
            ->whereKeyNot($product->id)
            ->where(function ($q) use ($product) {
                if ($product->generic_name) {
                    $q->orWhere('generic_name', $product->generic_name);
                }
                if ($product->category_id) {
                    $q->orWhere(fn ($c) => $c->where('category_id', $product->category_id)
                        ->when($product->dosage_form_id, fn ($d) => $d->where('dosage_form_id', $product->dosage_form_id)));
                }
                if (! $product->generic_name && ! $product->category_id) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->with(['baseUom', 'uoms.uom'])
            ->limit(40)
            ->get();

        if ($candidates->isEmpty()) {
            return [];
        }

        $free = DB::table('stock_balances as sb')
            ->join('product_batches as pb', 'pb.id', '=', 'sb.batch_id')
            ->join('stores as st', 'st.id', '=', 'sb.store_id')
            ->where('st.branch_id', $branchId)
            ->where('pb.status', 'RELEASED')
            ->where('pb.expiry_date', '>=', now()->toDateString())
            ->whereIn('sb.product_id', $candidates->pluck('id'))
            ->groupBy('sb.product_id', 'sb.store_id')
            ->selectRaw('sb.product_id, sb.store_id, SUM(sb.qty_on_hand - sb.qty_reserved - sb.qty_quarantined) as free')
            ->get();

        $inStore = [];
        $inBranch = [];
        foreach ($free as $row) {
            $qty = max(0, (float) $row->free);
            $inBranch[$row->product_id] = ($inBranch[$row->product_id] ?? 0) + $qty;
            if ($row->store_id === $storeId) {
                $inStore[$row->product_id] = $qty;
            }
        }

        return $candidates
            ->filter(fn (Product $p) => ($inBranch[$p->id] ?? 0) > 0)
            ->map(fn (Product $p) => [
                'match' => $product->generic_name && $p->generic_name === $product->generic_name ? 'SAME_GENERIC' : 'SAME_CATEGORY',
                'free_in_store' => number_format($inStore[$p->id] ?? 0, 4, '.', ''),
                'free_in_branch' => number_format($inBranch[$p->id], 4, '.', ''),
                'product' => $p->toArray(),
            ])
            ->sortBy([
                fn ($a, $b) => ($a['match'] === 'SAME_GENERIC' ? 0 : 1) <=> ($b['match'] === 'SAME_GENERIC' ? 0 : 1),
                fn ($a, $b) => (float) $b['free_in_store'] <=> (float) $a['free_in_store'],
            ])
            ->take(self::MAX_ALTERNATIVES)
            ->values()
            ->all();
    }
}
