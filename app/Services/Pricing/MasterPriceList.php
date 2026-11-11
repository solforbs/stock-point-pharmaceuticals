<?php

namespace App\Services\Pricing;

use App\Models\AuditLog;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUom;
use App\Models\Store;
use App\Services\Sales\OfflineSaleService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The master price list: one price for every product the institution has in
 * stock, set from Stock on Hand. It is a price list for every sale mode
 * (sale_mode BOTH) whose prices include VAT, so what is typed is what the
 * counter charges; the pricing engine reads it ahead of the per-mode lists.
 *
 * A price is entered per the product's default selling unit and spread to
 * its other selling units by their factor. Rows are effective-dated through
 * PriceListWriter, so every earlier quote can still be explained.
 */
class MasterPriceList
{
    public const CODE = 'MASTER';

    public const SALE_MODE = 'BOTH';

    /** Batches whose stock can be offered: released, or in for QC; never expired, recalled or rejected. */
    private const OFFERABLE_BATCH_STATUSES = ['RELEASED', 'PENDING_QC'];

    public function __construct(private readonly PriceListWriter $writer) {}

    public function find(string $organisationId): ?PriceList
    {
        return PriceList::where('organisation_id', $organisationId)->where('code', self::CODE)->first();
    }

    /**
     * Every product with sellable stock, its selling unit, what it cost and
     * its price now: the master price if one is saved, else the catalogue price.
     *
     * @return list<array{product_id: string, code: string, name: string, brand_name: ?string, strength: ?string, uom_id: string, uom_code: ?string, factor_to_base: string, on_hand: string, unit_cost: ?string, master_price: ?string, catalogue_price: ?string, price: ?string}>
     */
    public function sheet(string $organisationId, bool $withCost): array
    {
        $stock = $this->stockOnHand($organisationId);
        if ($stock->isEmpty()) {
            return [];
        }

        $products = Product::where('organisation_id', $organisationId)->whereIn('id', $stock->keys())
            ->with('uoms.uom:id,code')->orderBy('name')->orderBy('code')->get();
        $current = $this->currentPrices($organisationId, $stock->keys()->all());

        return $products->map(function (Product $product) use ($stock, $current, $withCost) {
            $unit = $this->sellingUnit($product);
            $factor = (string) ($unit?->factor_to_base ?? 1);
            $onHand = (string) $stock[$product->id]->on_hand;
            $costPerBase = bccomp($onHand, '0', 4) > 0 ? bcdiv((string) $stock[$product->id]->cost_value, $onHand, 6) : null;
            $master = $unit ? $current->get($product->id)?->get($unit->uom_id)?->unit_price : null;
            $catalogue = $product->default_price !== null && bccomp((string) $product->default_price, '0', 4) > 0
                ? Money::round(bcmul((string) $product->default_price, $factor, 6), 2) : null;

            return [
                'product_id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'brand_name' => $product->brand_name,
                'strength' => $product->strength,
                'uom_id' => (string) ($unit?->uom_id ?? $product->base_uom_id),
                'uom_code' => $unit?->uom?->code,
                'factor_to_base' => $factor,
                'on_hand' => $onHand,
                'unit_cost' => $withCost && $costPerBase !== null ? Money::round(bcmul($costPerBase, $factor, 6), 2) : null,
                'master_price' => $master !== null ? Money::round((string) $master, 2) : null,
                'catalogue_price' => $catalogue,
                'price' => $master !== null ? Money::round((string) $master, 2) : $catalogue,
            ];
        })->values()->all();
    }

    /**
     * Saves the prices: each is per the product's selling unit, VAT included.
     * Unchanged prices are left alone; the catalogue price follows the list.
     *
     * @param  list<array{product_id: string, price: string|float|int}>  $items
     * @return array{price_list_id: string, products: int, prices_changed: int}
     */
    public function save(string $organisationId, int $userId, array $items): array
    {
        $result = DB::transaction(function () use ($organisationId, $userId, $items) {
            $list = $this->find($organisationId) ?? PriceList::create([
                'organisation_id' => $organisationId,
                'code' => self::CODE,
                'name' => 'Master price list',
                'sale_mode' => self::SALE_MODE,
                'currency' => 'KES',
                'prices_include_tax' => true,
                'effective_from' => now()->toDateString(),
                'is_active' => true,
                'priority' => 100,
            ]);
            if (! $list->is_active) {
                $list->update(['is_active' => true]);
            }

            $byProduct = collect($items)->keyBy('product_id');
            $products = Product::where('organisation_id', $organisationId)->whereIn('id', $byProduct->keys())->with('uoms')->get();
            $current = $this->currentPrices($organisationId, $products->modelKeys());
            $changed = 0;

            foreach ($products as $product) {
                $unit = $this->sellingUnit($product);
                if (! $unit) {
                    continue;
                }
                $price = Money::round((string) $byProduct[$product->id]['price'], 2);
                $perBase = bcdiv($price, (string) $unit->factor_to_base, 6);

                foreach ($product->uoms->filter(fn (ProductUom $u) => $u->is_sales || $u->is_base) as $uom) {
                    $unitPrice = $uom->uom_id === $unit->uom_id ? $price : Money::round(bcmul($perBase, (string) $uom->factor_to_base, 6), 2);
                    $existing = $current->get($product->id)?->get($uom->uom_id);
                    if ($existing && bccomp((string) $existing->unit_price, $unitPrice, 4) === 0) {
                        continue;
                    }
                    $this->writer->write($list, [
                        'product_id' => $product->id,
                        'uom_id' => $uom->uom_id,
                        'factor_type' => 'FIXED',
                        'unit_price' => $unitPrice,
                    ], ['source' => 'MASTER_PRICE_LIST']);
                    $changed++;
                }

                $catalogue = bcdiv($price, (string) $unit->factor_to_base, 4);
                if ($product->default_price === null || bccomp((string) $product->default_price, $catalogue, 4) !== 0) {
                    $product->update(['default_price' => $catalogue]);
                }
            }

            AuditLog::record('MASTER_PRICE_LIST_SAVED', 'price_list', $list->id, [
                'user_id' => $userId,
                'reference' => $list->code,
                'after_json' => ['products' => $products->count(), 'prices_changed' => $changed],
            ]);

            return ['price_list_id' => $list->id, 'products' => $products->count(), 'prices_changed' => $changed];
        });

        // The tills' offline packs cache prices per store; drop them so they re-price.
        OfflineSaleService::forgetPriceMaps(
            DB::table('stores as s')->join('branches as b', 'b.id', '=', 's.branch_id')
                ->where('b.organisation_id', $organisationId)->pluck('s.id')
        );

        return $result;
    }

    /**
     * The saved list for print: every product with a current master price
     * that still has stock, at its selling unit.
     *
     * @return list<array{code: string, name: string, brand_name: ?string, unit: ?string, price: string}>
     */
    public function printable(string $organisationId): array
    {
        return collect($this->sheet($organisationId, false))
            ->filter(fn (array $row) => $row['master_price'] !== null)
            ->map(fn (array $row) => [
                'code' => $row['code'],
                'name' => trim($row['name'].($row['strength'] && ! str_contains($row['name'], $row['strength']) ? ' '.$row['strength'] : '')),
                'brand_name' => $row['brand_name'],
                'unit' => $row['uom_code'],
                'price' => $row['master_price'],
            ])->values()->all();
    }

    /**
     * Sellable stock per product across the business's stores, with its value at cost.
     *
     * @return Collection<string, object{product_id: string, on_hand: string, cost_value: string}>
     */
    private function stockOnHand(string $organisationId): Collection
    {
        return DB::table('stock_balances as b')
            ->join('product_batches as pb', 'pb.id', '=', 'b.batch_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->where('p.organisation_id', $organisationId)
            ->where('p.is_active', true)
            ->whereNotIn('b.store_id', Store::outsideBusinessMode())
            ->where('b.qty_on_hand', '>', 0)
            ->whereIn('pb.status', self::OFFERABLE_BATCH_STATUSES)
            ->where('pb.expiry_date', '>', now()->toDateString())
            ->groupBy('b.product_id')
            ->selectRaw('b.product_id, SUM(b.qty_on_hand) as on_hand, SUM(b.qty_on_hand * b.wac) as cost_value')
            ->get()
            ->keyBy('product_id');
    }

    /**
     * Today's master-list row per product and unit.
     *
     * @param  list<string>  $productIds
     * @return Collection<string, Collection<string, ProductPrice>>
     */
    private function currentPrices(string $organisationId, array $productIds): Collection
    {
        $list = $this->find($organisationId);
        if (! $list || $productIds === []) {
            return collect();
        }
        $today = now()->toDateString();

        return ProductPrice::where('price_list_id', $list->id)->whereIn('product_id', $productIds)
            ->whereDate('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
            ->orderBy('effective_from')
            ->get()
            ->groupBy('product_id')
            ->map(fn (Collection $rows) => $rows->keyBy('uom_id'));
    }

    /** The unit a price is entered in: the default selling unit, else any selling unit, else the base unit. */
    private function sellingUnit(Product $product): ?ProductUom
    {
        return $product->uoms->firstWhere('is_default_sales', true)
            ?? $product->uoms->firstWhere('is_sales', true)
            ?? $product->uoms->firstWhere('is_base', true);
    }
}
