<?php

namespace App\Services\Pricing;

use App\Models\BankAccount;
use App\Models\Organisation;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The master price list: one organisation-wide selling price per product,
 * per base unit and exclusive of VAT, set from the stock on hand. The POS
 * uses it ahead of every tier, mode and branch list (PricingEngine), and
 * each product's default price is kept equal to it, so search results and
 * the offline estimate show the same figure the quote will charge.
 *
 * Rows go through PriceListWriter, so every change is effective-dated and
 * audited rather than overwritten.
 */
class MasterPriceList
{
    /** Batches whose stock can be offered: released, or in for QC; never expired, recalled or rejected. */
    public const OFFERABLE_BATCH_STATUSES = ['RELEASED', 'PENDING_QC'];

    public function __construct(private PriceListWriter $writer) {}

    /** The institution's master list, if one has been saved. */
    public function find(string $organisationId): ?PriceList
    {
        return PriceList::where('organisation_id', $organisationId)->where('is_master', true)->first();
    }

    /**
     * The active products the institution actually has in stock, with the
     * offerable quantity across all its stores.
     *
     * @return Builder<Product>
     */
    public function inStockProducts(string $organisationId): Builder
    {
        return Product::where('products.organisation_id', $organisationId)->where('products.is_active', true)
            ->whereExists(fn ($q) => $this->offerableStock($q->selectRaw('1')))
            ->orderBy('products.name')->orderBy('products.code');
    }

    /**
     * The in-stock products with their stock and their prices: the saved
     * master price (null until one is saved) and the default price.
     *
     * @return list<array{id: string, code: string, name: string, brand_name: ?string, base_uom: ?string, qty_on_hand: string, default_price: ?string, master_price: ?string}>
     */
    public function rows(string $organisationId): array
    {
        $masterPrices = $this->currentPrices($this->find($organisationId));

        return $this->inStockProducts($organisationId)
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'products.base_uom_id')
            ->select('products.id', 'products.code', 'products.name', 'products.brand_name', 'products.default_price', 'u.code as base_uom')
            ->selectSub(fn ($q) => $this->offerableStock($q->selectRaw('COALESCE(SUM(sb.qty_on_hand), 0)')), 'qty_on_hand')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'brand_name' => $p->brand_name,
                'base_uom' => $p->getAttribute('base_uom'),
                'qty_on_hand' => (string) $p->getAttribute('qty_on_hand'),
                'default_price' => $p->default_price === null ? null : (string) $p->default_price,
                'master_price' => $masterPrices[$p->id] ?? null,
            ])->values()->all();
    }

    /**
     * Saves the typed prices. Only a price that differs from the current
     * master price is written, so saving the same list twice changes nothing.
     *
     * @param  array<string, string>  $prices  product id => price per base unit
     * @return array{list: PriceList, changed: int, unchanged: int}
     */
    public function save(string $organisationId, array $prices): array
    {
        return DB::transaction(function () use ($organisationId, $prices) {
            // Two people saving at once must not create two master lists.
            Organisation::whereKey($organisationId)->lockForUpdate()->first();
            $list = $this->find($organisationId) ?? $this->create($organisationId);
            $current = $this->currentPrices($list);
            $products = Product::whereKey(array_keys($prices))->get(['id', 'base_uom_id', 'default_price'])->keyBy('id');

            $changed = 0;
            foreach ($prices as $productId => $price) {
                $product = $products->get($productId);
                $price = bcadd((string) $price, '0', 4);
                if ($product === null || (isset($current[$productId]) && bccomp($current[$productId], $price, 4) === 0)) {
                    continue;
                }

                $this->writer->write($list, [
                    'product_id' => $product->id,
                    'uom_id' => (string) $product->base_uom_id,
                    'factor_type' => 'FIXED',
                    'unit_price' => $price,
                    'effective_from' => now()->toDateString(),
                ], ['source' => 'MASTER_PRICE_LIST', 'default_price_before' => $product->default_price === null ? null : (string) $product->default_price]);
                $product->update(['default_price' => $price]);
                $changed++;
            }
            $list->touch();

            return ['list' => $list->fresh(), 'changed' => $changed, 'unchanged' => count($prices) - $changed];
        });
    }

    /**
     * Today's master price per product, per base unit.
     *
     * @return array<string, string>
     */
    public function currentPrices(?PriceList $list): array
    {
        if ($list === null) {
            return [];
        }
        $today = now()->toDateString();

        return ProductPrice::where('price_list_id', $list->id)
            ->join('products as p', 'p.id', '=', 'product_prices.product_id')
            ->whereColumn('product_prices.uom_id', 'p.base_uom_id')
            ->whereDate('product_prices.effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('product_prices.effective_to')->orWhereDate('product_prices.effective_to', '>=', $today))
            ->orderBy('product_prices.effective_from')
            ->pluck('product_prices.unit_price', 'product_prices.product_id')
            ->map(fn ($price) => (string) $price)
            ->all();
    }

    /**
     * How customers pay, for the foot of a price list: M-PESA from the
     * company profile, then the active bank accounts.
     *
     * @return array{mpesa_paybill: ?string, mpesa_account: ?string, mpesa_till: ?string, note: ?string, account_name: string, banks: list<array{bank_name: ?string, account_name: ?string, account_number: ?string, branch_name: ?string}>}
     */
    public function paymentDetails(string $organisationId): array
    {
        $organisation = Organisation::find($organisationId);

        return [
            'mpesa_paybill' => $organisation?->mpesa_paybill,
            'mpesa_account' => $organisation?->mpesa_account,
            'mpesa_till' => $organisation?->mpesa_till,
            'note' => $organisation?->payment_note,
            'account_name' => $organisation?->legal_name ?: ($organisation->name ?? ''),
            'banks' => BankAccount::where('organisation_id', $organisationId)->where('is_active', true)->orderBy('name')
                ->get(['bank_name', 'account_name', 'account_number', 'branch_name'])->toArray(),
        ];
    }

    public function title(string $organisationId): string
    {
        return (Organisation::whereKey($organisationId)->value('name') ?? 'Price list').' - Price List';
    }

    /** Stock that can be offered for one product (the outer query's row). */
    private function offerableStock(QueryBuilder $query): QueryBuilder
    {
        return $query->from('stock_balances as sb')
            ->join('product_batches as pb', 'pb.id', '=', 'sb.batch_id')
            ->whereColumn('sb.product_id', 'products.id')
            ->where('sb.qty_on_hand', '>', 0)
            ->whereIn('pb.status', self::OFFERABLE_BATCH_STATUSES)
            ->where('pb.expiry_date', '>', now()->toDateString());
    }

    /** A code the institution has not used yet: MASTER, or MASTER-2, MASTER-3… */
    private function create(string $organisationId): PriceList
    {
        $code = 'MASTER';
        for ($n = 2; PriceList::where('organisation_id', $organisationId)->where('code', $code)->exists(); $n++) {
            $code = 'MASTER-'.$n;
        }

        return PriceList::create([
            'organisation_id' => $organisationId,
            'code' => $code,
            'name' => 'Master price list',
            'currency' => 'KES',
            'prices_include_tax' => false,
            'effective_from' => now()->toDateString(),
            'priority' => 0,
            'is_active' => true,
            'is_master' => true,
        ]);
    }
}
