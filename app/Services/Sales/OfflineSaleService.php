<?php

namespace App\Services\Sales;

use App\Exceptions\ApiErrorMap;
use App\Models\AuditLog;
use App\Models\OfflineSale;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\Pricing\Money;
use App\Services\Pricing\PriceQuoteService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Part 16.7 / 17.5 — selling through an internet outage.
 *
 * While it can reach the server a terminal downloads a price pack: every
 * product with free stock in its store, priced by the real quote engine for
 * a walk-in retail customer. When the server is unreachable the counter
 * sells from that pack into a local outbox, and replays the outbox here on
 * reconnect.
 *
 * A replayed sale is a fact, not a request: the customer has paid and left
 * with the goods. It is therefore posted at the prices actually charged, and
 * any difference from what the server would have charged at that moment is
 * recorded as a price variance for review rather than silently corrected.
 * A sale that cannot be posted at all (the stock has gone, the store was
 * closed to sales) is kept as a CONFLICT for a supervisor, never dropped.
 */
class OfflineSaleService
{
    /** How long a price pack may be sold from before the terminal must refresh it. */
    public const PackValidHours = 24;

    /** A backstop only: the scheduler re-prices every fifteen minutes. */
    private const PriceMapTtlMinutes = 60;

    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly PriceQuoteService $quotes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function pricePack(Store $store, User $user): array
    {
        $stock = $this->freeStock($store);
        $products = $this->stockedProducts($stock);

        $priceMap = $this->priceMap($store, $user, $products);

        $items = [];
        foreach ($products as $product) {
            $saleUoms = $product->uoms->filter(fn ($u) => $u->is_sales)->values();
            $prices = $priceMap[$product->id] ?? [];
            if ($saleUoms->isEmpty() || $prices === []) {
                continue;
            }

            $items[] = [
                'id' => $product->id,
                'code' => $product->code,
                'sku' => $product->sku,
                'gtin' => $product->gtin,
                'name' => $product->name,
                'generic_name' => $product->generic_name,
                'strength' => $product->strength,
                'is_discrete' => (bool) $product->is_discrete,
                'pack_integrity' => (bool) $product->pack_integrity,
                'base_uom_id' => $product->base_uom_id,
                'base_uom' => $product->baseUom,
                'uoms' => $saleUoms->filter(fn ($u) => isset($prices[$u->uom_id]))->values(),
                'prices' => $prices,
                'free_to_sell' => bcadd((string) $stock[$product->id], '0', 4),
            ];
        }

        $generatedAt = now();

        return [
            'store' => $store->only(['id', 'code', 'name']),
            'generated_at' => $generatedAt->toIso8601String(),
            'valid_until' => $generatedAt->copy()->addHours(self::PackValidHours)->toIso8601String(),
            'products' => $items,
        ];
    }

    /**
     * Re-prices everything the store has in stock and replaces its cached
     * price map. Run on a schedule so a till's pack request never waits on
     * the quote engine, and so a price change reaches the tills promptly.
     */
    public function warmPriceMap(Store $store, User $user): int
    {
        Cache::forget($this->priceMapKey($store));

        return count($this->priceMap($store, $user, $this->stockedProducts($this->freeStock($store))));
    }

    /**
     * @param  array{
     *     id: string, store_id: string, terminal_id?: ?string, sold_at: string, sold_by?: ?string, total: string,
     *     lines: list<array{product_id: string, uom_id: string, qty: string, unit_price: string, tax_rate: string}>,
     *     payments: list<array{method: string, amount: string, reference?: ?string}>,
     * }  $data
     */
    public function submit(array $data, Store $store, User $user): OfflineSale
    {
        if ($existing = OfflineSale::where('idempotency_key', $data['id'])->first()) {
            return $existing;
        }

        // One transaction: if the attempt dies outright (a timeout, a fault)
        // nothing is kept and the terminal simply sends the sale again,
        // rather than finding a conflict with no reason already recorded.
        return DB::transaction(function () use ($data, $store, $user) {
            $offline = OfflineSale::create([
                'organisation_id' => $store->branch->organisation_id,
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
                'terminal_id' => $data['terminal_id'] ?? null,
                'user_id' => $user->id,
                'idempotency_key' => $data['id'],
                'sold_at' => Carbon::parse($data['sold_at']),
                'payload_json' => $data,
                'offline_total' => $data['total'],
                'status' => OfflineSale::Conflict,
            ]);

            return $this->attempt($offline, $user);
        });
    }

    /** A supervisor tries a CONFLICT again, typically after correcting the stock. */
    public function retry(OfflineSale $offline, User $user): OfflineSale
    {
        if ($offline->status !== OfflineSale::Conflict) {
            throw new \DomainException("Offline sale {$offline->idempotency_key} is {$offline->status}; only a conflict can be retried.");
        }

        return DB::transaction(fn () => $this->attempt($offline, $user));
    }

    /**
     * The supervisor decides a conflict will not be posted. The money is
     * still in the till, so the reason has to say what happened to it.
     */
    public function dismiss(OfflineSale $offline, User $user, string $reason): OfflineSale
    {
        if ($offline->status !== OfflineSale::Conflict) {
            throw new \DomainException("Offline sale {$offline->idempotency_key} is {$offline->status}; only a conflict can be dismissed.");
        }

        $offline->update([
            'status' => OfflineSale::Dismissed,
            'resolved_by' => $user->id,
            'resolved_at' => now(),
            'resolution_note' => $reason,
        ]);

        AuditLog::record('OFFLINE_SALE_DISMISSED', 'offline_sale', $offline->id, [
            'branch_id' => $offline->branch_id,
            'reference' => $offline->idempotency_key,
            'reason' => $reason,
            'after_json' => ['offline_total' => (string) $offline->offline_total],
        ]);

        return $offline;
    }

    private function attempt(OfflineSale $offline, User $user): OfflineSale
    {
        $offline->increment('attempts');

        // The online checkout for this same attempt may have reached the
        // server before the connection dropped; the key is shared, so the
        // sale it created is the answer and nothing posts twice.
        $sale = Sale::where('idempotency_key', $offline->idempotency_key)->first();
        $serverTotal = $this->serverTotal($offline);

        if (! $sale) {
            try {
                $sale = $this->post($offline, $user);
            } catch (\Throwable $e) {
                // A record that has since vanished (a unit of measure removed
                // from the product) is a conflict too; anything else unknown
                // is a fault, and the terminal will simply send it again.
                $code = $e instanceof ModelNotFoundException ? 'NOT_FOUND' : ApiErrorMap::toResponse($e)?->getData(true)['error']['code'];
                if ($code === null) {
                    throw $e;
                }

                $offline->update([
                    'status' => OfflineSale::Conflict,
                    'server_total' => $serverTotal,
                    'price_variance' => $this->variance($offline, $serverTotal),
                    'error_code' => $code,
                    'error_message' => $e->getMessage(),
                ]);

                AuditLog::record('OFFLINE_SALE_CONFLICT', 'offline_sale', $offline->id, [
                    'branch_id' => $offline->branch_id,
                    'terminal_id' => $offline->terminal_id,
                    'reference' => $offline->idempotency_key,
                    'reason' => $e->getMessage(),
                ]);

                return $offline->fresh();
            }
        }

        $offline->update([
            'status' => OfflineSale::Posted,
            'sale_id' => $sale->id,
            'server_total' => $serverTotal,
            'price_variance' => $this->variance($offline, $serverTotal),
            'error_code' => null,
            'error_message' => null,
            'resolved_by' => $offline->attempts > 1 ? $user->id : null,
            'resolved_at' => $offline->attempts > 1 ? now() : null,
        ]);

        AuditLog::record('OFFLINE_SALE_POSTED', 'offline_sale', $offline->id, [
            'branch_id' => $offline->branch_id,
            'terminal_id' => $offline->terminal_id,
            'reference' => $sale->doc_number,
            'after_json' => [
                'sale_id' => $sale->id,
                'sold_at' => $offline->sold_at->toIso8601String(),
                'offline_total' => (string) $offline->offline_total,
                'server_total' => $serverTotal,
            ],
        ]);

        return $offline->fresh();
    }

    /** Posts the sale at the prices the customer was actually charged. */
    private function post(OfflineSale $offline, User $user): Sale
    {
        $data = $offline->payload_json;

        $lines = [];
        $total = '0.0000';
        foreach ($data['lines'] as $line) {
            $qty = bcadd((string) $line['qty'], '0', 4);
            $unitPrice = bcadd((string) $line['unit_price'], '0', 4);
            $net = Money::round(bcmul($unitPrice, $qty, 6), 2);
            $tax = Money::round(bcmul($net, bcdiv((string) $line['tax_rate'], '100', 6), 6), 2);
            $total = bcadd($total, bcadd($net, $tax, 4), 4);

            $lines[] = [
                'product_id' => $line['product_id'],
                'uom_id' => $line['uom_id'],
                'qty' => $qty,
                'list_price' => $unitPrice,
                'unit_price' => $unitPrice,
                'discount_amount' => '0.0000',
                'tax_code_id' => Product::where('id', $line['product_id'])->value('tax_code_id'),
                'tax_rate' => (string) $line['tax_rate'],
                'tax_amount' => $tax,
                // The goods have already left the shop: any batch that is
                // still in date will do, not the usual shelf-life margin.
                'min_shelf_life_days' => 0,
            ];
        }

        if (bccomp($total, (string) $offline->offline_total, 4) !== 0) {
            throw new \DomainException("The terminal's total ({$offline->offline_total}) does not match its own lines ({$total}).");
        }

        return $this->checkout->checkout([
            'organisation_id' => $offline->organisation_id,
            'branch_id' => $offline->branch_id,
            'store_id' => $offline->store_id,
            'sale_mode' => 'RETAIL',
            'user_id' => $user->id,
            'terminal_id' => $offline->terminal_id,
            'idempotency_key' => $offline->idempotency_key,
            'lines' => $lines,
            'payments' => array_map(fn ($p) => [
                'method' => $p['method'],
                'amount' => (string) $p['amount'],
                'reference' => $p['reference'] ?? null,
            ], $data['payments']),
        ]);
    }

    /**
     * What the server would have charged for the same lines at the moment
     * of sale. Null when it cannot say (a product no longer sold in that
     * unit): the sale still posts, and the missing figure is itself a flag.
     */
    private function serverTotal(OfflineSale $offline): ?string
    {
        $data = $offline->payload_json;

        try {
            $quote = $this->quotes->quote([
                'organisation_id' => $offline->organisation_id,
                'branch_id' => $offline->branch_id,
                'store_id' => $offline->store_id,
                'sale_mode' => 'RETAIL',
                'user_id' => $offline->user_id,
                'quote_date' => $offline->sold_at->toDateTimeString(),
                'lines' => array_map(fn ($l) => [
                    'product_id' => $l['product_id'],
                    'uom_id' => $l['uom_id'],
                    'quantity' => (string) $l['qty'],
                ], $data['lines']),
            ], persist: false);
        } catch (\RuntimeException|\LogicException) {
            return null;
        }

        return (string) $quote['totals']['grand_total'];
    }

    private function variance(OfflineSale $offline, ?string $serverTotal): string
    {
        return $serverTotal === null ? '0.0000' : bcsub((string) $offline->offline_total, $serverTotal, 4);
    }

    /**
     * Free-to-sell per product in the store, in the base unit: released,
     * in-date batches only, as the checkout's FEFO allocator sees them.
     *
     * @return Collection<string, string>
     */
    private function freeStock(Store $store): Collection
    {
        return DB::table('stock_balances as sb')
            ->join('product_batches as pb', 'pb.id', '=', 'sb.batch_id')
            ->where('sb.store_id', $store->id)
            ->where('pb.status', 'RELEASED')
            ->where('pb.expiry_date', '>', now()->toDateString())
            ->groupBy('sb.product_id')
            ->havingRaw('SUM(sb.qty_on_hand - sb.qty_reserved - sb.qty_quarantined) > 0')
            ->selectRaw('sb.product_id, SUM(sb.qty_on_hand - sb.qty_reserved - sb.qty_quarantined) as free_to_sell')
            ->pluck('free_to_sell', 'product_id');
    }

    /**
     * @param  Collection<string, string>  $stock
     * @return EloquentCollection<int, Product>
     */
    private function stockedProducts(Collection $stock): EloquentCollection
    {
        return Product::whereIn('id', $stock->keys())
            ->where('is_active', true)
            ->with(['baseUom:id,code,name', 'uoms.uom:id,code,name'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Walk-in prices for these products, from the store's cache where it
     * has them. Pricing runs the full quote engine (about 25 queries a
     * product), so only products the cache has not seen are priced now.
     *
     * @param  EloquentCollection<int, Product>  $products
     * @return array<string, array<string, array{unit_price: string, tax_rate: string, tax_code: ?string}>>
     */
    private function priceMap(Store $store, User $user, EloquentCollection $products): array
    {
        $key = $this->priceMapKey($store);
        $map = Cache::get($key, []);

        $unpriced = $products->reject(fn (Product $p) => array_key_exists($p->id, $map));
        if ($unpriced->isEmpty()) {
            return $map;
        }

        foreach ($unpriced as $product) {
            $uomIds = $product->uoms->filter(fn ($u) => $u->is_sales)->pluck('uom_id')->all();
            $map[$product->id] = $uomIds === [] ? [] : $this->walkInPrices($store, $user, $product, $uomIds);
        }
        Cache::put($key, $map, now()->addMinutes(self::PriceMapTtlMinutes));

        return $map;
    }

    private function priceMapKey(Store $store): string
    {
        return "pos:offline-price-map:{$store->id}";
    }

    /**
     * The walk-in retail price of one of each sales unit, keyed by uom_id.
     * A unit the engine will not price without an approval is left out, so
     * the counter can never sell offline what it could not sell online.
     *
     * @param  list<string>  $uomIds
     * @return array<string, array{unit_price: string, tax_rate: string, tax_code: ?string}>
     */
    private function walkInPrices(Store $store, User $user, Product $product, array $uomIds): array
    {
        try {
            $quote = $this->quotes->quote([
                'organisation_id' => $product->organisation_id,
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
                'sale_mode' => 'RETAIL',
                'user_id' => $user->id,
                'lines' => array_map(fn ($uomId) => ['product_id' => $product->id, 'uom_id' => $uomId, 'quantity' => '1'], $uomIds),
            ], persist: false);
        } catch (\RuntimeException|\LogicException) {
            return [];
        }

        $prices = [];
        foreach ($quote['lines'] as $line) {
            if ($line['approval_required'] || $line['floor_breached'] || bccomp((string) $line['unit_price'], '0', 4) <= 0) {
                continue;
            }
            $prices[$line['uom_id']] = [
                'unit_price' => bcadd((string) $line['unit_price'], '0', 4),
                'tax_rate' => bcadd((string) $line['tax_rate'], '0', 4),
                'tax_code' => $line['tax_code'],
            ];
        }

        return $prices;
    }
}
