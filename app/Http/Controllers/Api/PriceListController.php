<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\CustomerTier;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Part 6.2 — price lists and their rows. Rows are effective-dated: a new
 * price closes the row it replaces rather than overwriting it, so every
 * historical quote can still be explained.
 */
class PriceListController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');

        return response()->json(
            PriceList::where('organisation_id', $this->organisationId($request))
                ->with(['tier:id,code,name', 'branch:id,code,name'])->withCount('productPrices')
                ->orderByDesc('priority')->orderBy('code')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $organisationId = $this->organisationId($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('price_lists', 'code')->where('organisation_id', $organisationId)],
            'name' => ['required', 'string', 'max:100'],
            'sale_mode' => ['nullable', 'in:RETAIL,WHOLESALE,DISPENSING'],
            'tier_id' => ['nullable', 'uuid', TenantRules::exists('customer_tiers')],
            'branch_id' => ['nullable', 'uuid', TenantRules::exists('branches')],
            'currency' => ['nullable', 'string', 'size:3'],
            'prices_include_tax' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $list = PriceList::create($data + [
            'organisation_id' => $organisationId,
            'currency' => $data['currency'] ?? 'KES',
            'prices_include_tax' => $data['prices_include_tax'] ?? false,
            'effective_from' => $data['effective_from'] ?? now()->toDateString(),
            'priority' => $data['priority'] ?? 0,
            'is_active' => true,
        ]);
        AuditLog::record('PRICE_LIST_CREATED', 'price_list', $list->id, ['reference' => $list->code]);

        return response()->json($list->load(['tier:id,code,name', 'branch:id,code,name']), 201);
    }

    public function update(Request $request, string $list): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $list = PriceList::where('organisation_id', $this->organisationId($request))->findOrFail($list);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'effective_to' => ['nullable', 'date'],
        ]);
        $before = $list->only(array_keys($data));
        $list->update($data);
        AuditLog::record('PRICE_LIST_UPDATED', 'price_list', $list->id, ['reference' => $list->code, 'before_json' => $before, 'after_json' => $list->fresh()->only(array_keys($data)), 'changed_fields' => array_keys($data)]);

        return response()->json($list->fresh()->load(['tier:id,code,name', 'branch:id,code,name']));
    }

    /** GET /api/price-lists/{id}/items — current rows by default; include_history=1 returns closed rows too. */
    public function items(Request $request, string $list): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');
        $list = PriceList::where('organisation_id', $this->organisationId($request))->findOrFail($list);
        $today = now()->toDateString();

        return response()->json(
            ProductPrice::where('price_list_id', $list->id)
                ->when(! $request->boolean('include_history'), fn ($q) => $q->whereDate('effective_from', '<=', $today)->where(fn ($w) => $w->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today)))
                ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->string('product_id')))
                ->when($request->filled('q'), fn ($q) => $q->whereHas('product', fn ($p) => $p->where('name', 'like', '%'.$request->string('q')->trim().'%')->orWhere('code', 'like', '%'.$request->string('q')->trim().'%')))
                ->with(['product:id,code,name,default_price', 'uom:id,code,name'])
                ->orderByDesc('effective_from')->orderBy('created_at')
                ->paginate($request->integer('per_page', 50))
        );
    }

    /** POST /api/price-lists/{id}/items — a new effective-dated row; the row it supersedes is closed the day before. */
    public function storeItem(Request $request, string $list): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $list = PriceList::where('organisation_id', $this->organisationId($request))->findOrFail($list);

        $data = $request->validate([
            'product_id' => ['required', 'uuid', TenantRules::exists('products')],
            'uom_id' => ['nullable', 'uuid', TenantRules::exists('units_of_measure')],
            'factor_type' => ['required', 'in:FIXED,COST_PLUS_MARKUP,TARGET_MARGIN,LIST_RELATIVE'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'required_if:factor_type,FIXED'],
            'factor_value' => ['nullable', 'numeric', 'min:0', 'required_unless:factor_type,FIXED'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
        $from = $data['effective_from'] ?? now()->toDateString();
        // A row always names its unit; the base unit is the default (Part 5.3).
        $data['uom_id'] = $data['uom_id'] ?? (string) Product::whereKey($data['product_id'])->value('base_uom_id');

        $row = DB::transaction(function () use ($list, $data, $from) {
            $previous = ProductPrice::where('price_list_id', $list->id)->where('product_id', $data['product_id'])
                ->where('uom_id', $data['uom_id'])
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
                ->whereDate('effective_from', '<', $from)
                ->get();
            foreach ($previous as $old) {
                $old->update(['effective_to' => Carbon::parse($from)->subDay()->toDateString()]);
            }
            // A second change on the same day replaces the same-day row outright.
            ProductPrice::where('price_list_id', $list->id)->where('product_id', $data['product_id'])
                ->where('uom_id', $data['uom_id'])->whereDate('effective_from', $from)->delete();

            $row = ProductPrice::create([
                'price_list_id' => $list->id,
                'product_id' => $data['product_id'],
                'uom_id' => $data['uom_id'],
                'factor_type' => $data['factor_type'],
                'unit_price' => (string) ($data['unit_price'] ?? '0'),
                'factor_value' => (string) ($data['factor_value'] ?? '0'),
                'effective_from' => $from,
                'effective_to' => $data['effective_to'] ?? null,
            ]);

            AuditLog::record('PRICE_CHANGED', 'product_price', $row->id, [
                'reference' => $list->code,
                'before_json' => $previous->map(fn (ProductPrice $p) => $p->only(['factor_type', 'unit_price', 'factor_value', 'effective_from']))->values()->all(),
                'after_json' => $row->only(['product_id', 'uom_id', 'factor_type', 'unit_price', 'factor_value', 'effective_from', 'effective_to']),
            ]);

            return $row;
        });

        return response()->json($row->load(['product:id,code,name,default_price', 'uom:id,code,name']), 201);
    }

    public function storeTier(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $organisationId = $this->organisationId($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('customer_tiers', 'code')->where('organisation_id', $organisationId)],
            'name' => ['required', 'string', 'max:100'],
            'default_price_list_id' => ['nullable', 'uuid', TenantRules::exists('price_lists')],
            'default_discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'credit_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        $tier = CustomerTier::create($data + ['organisation_id' => $organisationId]);
        AuditLog::record('CUSTOMER_TIER_CREATED', 'customer_tier', $tier->id, ['reference' => $tier->code]);

        return response()->json($tier, 201);
    }
}
