<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\ProductUom;
use App\Services\Inventory\InventoryReport;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'product.view');
        $organisationId = $this->organisationId($request);

        $products = Product::query()
            ->where('organisation_id', $organisationId)
            ->when($request->string('q')->trim()->isNotEmpty(), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('sku', 'like', $term)
                        ->orWhere('generic_name', 'like', $term);
                });
            })
            ->when($request->filled('barcode'), fn ($q) => $q->whereHas('uoms', fn ($u) => $u->where('barcode', $request->string('barcode'))))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->string('category_id')))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('stock') && $request->user()->can('stock.view'), function ($q) use ($request) {
                $inBranch = $this->branchStockQuery($request)->select('sb.product_id');
                match ((string) $request->string('stock')) {
                    'in_stock' => $q->whereIn('id', (clone $inBranch)->where('sb.qty_on_hand', '>', 0)),
                    'out_of_stock' => $q->whereNotIn('id', (clone $inBranch)->where('sb.qty_on_hand', '>', 0)),
                    'below_reorder' => $q->where('reorder_point', '>', 0)->whereRaw(
                        'reorder_point > COALESCE(('.$this->freeToSellSql().' AND sb.product_id = products.id), 0)',
                        [now()->toDateString(), $this->branchId($request)]
                    ),
                    default => null,
                };
            })
            ->with(['dosageForm', 'category', 'manufacturer', 'baseUom', 'uoms.uom', 'taxCode:id,code,name'])
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        // Part 7.3 — every product row carries its stock in the active
        // branch, so the catalogue and the shelf are one screen.
        if ($request->user()->can('stock.view')) {
            $ids = $products->getCollection()->pluck('id')->all();
            $stock = $this->branchStockQuery($request)
                ->whereIn('sb.product_id', $ids)
                ->groupBy('sb.product_id')
                ->selectRaw('sb.product_id')
                ->selectRaw('SUM(sb.qty_on_hand) as on_hand')
                ->selectRaw('SUM(sb.qty_reserved) as reserved')
                ->selectRaw("SUM(CASE WHEN pb.status = 'RELEASED' AND pb.expiry_date >= ? THEN sb.qty_on_hand - sb.qty_reserved - sb.qty_quarantined ELSE 0 END) as free_to_sell", [now()->toDateString()])
                ->selectRaw("MIN(CASE WHEN pb.status = 'RELEASED' AND sb.qty_on_hand > 0 THEN pb.expiry_date END) as nearest_expiry")
                ->get()->keyBy('product_id');

            $products->getCollection()->transform(function (Product $p) use ($stock) {
                $row = $stock->get($p->id);

                return $p->toArray() + ['stock' => [
                    'on_hand' => number_format((float) ($row->on_hand ?? 0), 4, '.', ''),
                    'reserved' => number_format((float) ($row->reserved ?? 0), 4, '.', ''),
                    'free_to_sell' => number_format(max(0, (float) ($row->free_to_sell ?? 0)), 4, '.', ''),
                    'nearest_expiry' => $row->nearest_expiry ?? null,
                ]];
            });
        }

        return response()->json($products);
    }

    /** stock_balances for the active branch's stores, joined to their batch. */
    private function branchStockQuery(Request $request): Builder
    {
        return DB::table('stock_balances as sb')
            ->join('product_batches as pb', 'pb.id', '=', 'sb.batch_id')
            ->join('stores as st', 'st.id', '=', 'sb.store_id')
            ->where('st.branch_id', $this->branchId($request));
    }

    /** Correlated free-to-sell for products.id; bindings: today, branch id. */
    private function freeToSellSql(): string
    {
        return "SELECT SUM(CASE WHEN pb.status = 'RELEASED' AND pb.expiry_date >= ? THEN sb.qty_on_hand - sb.qty_reserved - sb.qty_quarantined ELSE 0 END)
            FROM stock_balances sb JOIN product_batches pb ON pb.id = sb.batch_id JOIN stores st ON st.id = sb.store_id WHERE st.branch_id = ?";
    }

    public function show(Request $request, string $product): JsonResponse
    {
        $this->requirePermission($request, 'product.view');
        $organisationId = $this->organisationId($request);

        $product = Product::with(['dosageForm', 'category', 'manufacturer', 'baseUom', 'taxCode', 'uoms.uom', 'prices.priceList', 'discountPolicy'])
            ->where('organisation_id', $organisationId)
            ->findOrFail($product);

        return response()->json($product);
    }

    /** GET /api/products/{id}/stock — eight quantity states across all stores (Part 21.3). */
    public function stock(Request $request, string $product, InventoryReport $report): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');
        $organisationId = $this->organisationId($request);
        $product = Product::where('organisation_id', $organisationId)->findOrFail($product);

        $rows = $report->stockStates($organisationId, $product->id);
        if (! $request->user()->can('product.cost.view')) {
            foreach ($rows as &$row) {
                unset($row['value_at_cost']);
                foreach ($row['batches'] as &$b) {
                    unset($b['wac']);
                }
            }
        }

        return response()->json(['product' => $product->only(['id', 'code', 'name']), 'stores' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'product.create');

        $organisationId = $this->organisationId($request);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('products', 'code')->where('organisation_id', $organisationId)],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')],
            'gtin' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:255'],
            'dosage_form_id' => ['nullable', 'uuid', 'exists:dosage_forms,id'],
            'category_id' => ['nullable', 'uuid', 'exists:product_categories,id'],
            'manufacturer_id' => ['nullable', 'uuid', 'exists:manufacturers,id'],
            'base_uom_id' => ['required', 'uuid', 'exists:units_of_measure,id'],
            'tax_code_id' => ['nullable', 'uuid', 'exists:tax_codes,id'],
            'storage_condition_id' => ['nullable', 'uuid', 'exists:storage_conditions,id'],
            'is_discrete' => ['boolean'],
            'pack_integrity' => ['boolean'],
            'requires_batch' => ['boolean'],
            'reorder_point' => ['numeric', 'min:0'],
            'safety_stock' => ['numeric', 'min:0'],
            'lead_time_days' => ['integer', 'min:0'],
            'default_price' => ['nullable', 'numeric', 'min:0'],
            'uoms' => ['nullable', 'array'],
            'uoms.*.uom_id' => ['required', 'uuid', 'exists:units_of_measure,id'],
            'uoms.*.factor_to_base' => ['required', 'integer', 'min:2'],
            'uoms.*.is_purchase' => ['nullable', 'boolean'],
            'uoms.*.is_sales' => ['nullable', 'boolean'],
            'uoms.*.is_default_sales' => ['nullable', 'boolean'],
            'uoms.*.barcode' => ['nullable', 'string', 'max:64', 'unique:product_uoms,barcode'],
        ]);

        $product = DB::transaction(function () use ($validated, $organisationId, $request) {
            $product = Product::create([
                ...collect($validated)->except('uoms')->all(),
                'organisation_id' => $organisationId,
                'is_active' => true,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            // A product cannot be sold or converted without at least its base
            // UOM row (Product::toBase()/fromBase() depend on it) — created
            // here so a freshly-created product is immediately usable.
            ProductUom::create([
                'product_id' => $product->id,
                'uom_id' => $validated['base_uom_id'],
                'factor_to_base' => 1,
                'is_base' => true,
                'is_purchase' => true,
                'is_sales' => true,
                'is_default_sales' => empty($validated['uoms']),
            ]);

            foreach ($validated['uoms'] ?? [] as $uom) {
                ProductUom::create([
                    'product_id' => $product->id,
                    'uom_id' => $uom['uom_id'],
                    'factor_to_base' => $uom['factor_to_base'],
                    'is_base' => false,
                    'is_purchase' => (bool) ($uom['is_purchase'] ?? false),
                    'is_sales' => (bool) ($uom['is_sales'] ?? true),
                    'is_default_sales' => (bool) ($uom['is_default_sales'] ?? false),
                    'barcode' => $uom['barcode'] ?? null,
                ]);
            }

            return $product;
        });

        return response()->json($product->load(['baseUom', 'uoms.uom']), 201);
    }

    /** PATCH /api/products/{id} — base UOM immutable once stock exists (Part 5.3). */
    public function update(Request $request, string $product): JsonResponse
    {
        $this->requirePermission($request, 'product.edit');
        $organisationId = $this->organisationId($request);
        $product = Product::where('organisation_id', $organisationId)->findOrFail($product);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'uuid', 'exists:product_categories,id'],
            'dosage_form_id' => ['nullable', 'uuid', 'exists:dosage_forms,id'],
            'manufacturer_id' => ['nullable', 'uuid', 'exists:manufacturers,id'],
            'tax_code_id' => ['nullable', 'uuid', 'exists:tax_codes,id'],
            'storage_condition_id' => ['nullable', 'uuid', 'exists:storage_conditions,id'],
            'base_uom_id' => ['sometimes', 'uuid', 'exists:units_of_measure,id'],
            'pack_integrity' => ['sometimes', 'boolean'],
            'reorder_point' => ['sometimes', 'numeric', 'min:0'],
            'safety_stock' => ['sometimes', 'numeric', 'min:0'],
            'lead_time_days' => ['sometimes', 'integer', 'min:0'],
            'default_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['base_uom_id']) && $validated['base_uom_id'] !== $product->base_uom_id
            && DB::table('stock_ledgers')->where('product_id', $product->id)->exists()) {
            return $this->error('BASE_UOM_LOCKED', 'The base unit cannot change once stock has moved (Part 5.3). Create a new product instead.', 422);
        }

        $product->update($validated + ['updated_by' => $request->user()->id]);

        return response()->json($product->fresh(['baseUom', 'uoms.uom']));
    }
}
