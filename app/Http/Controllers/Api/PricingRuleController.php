<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPrice;
use App\Models\PriceBreak;
use App\Models\Product;
use App\Models\ProductDiscountPolicy;
use App\Models\ProductPrice;
use App\Models\ProductUom;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\RoleDiscountAuthority;
use App\Services\Pricing\PriceQuoteService;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Part 6 (V6 Part 4) — the pricing rules the engine reads: promotions
 * (rank 2 and bonus schemes), quantity breaks on price-list rows, product
 * discount policies, role discount authority and customer contract prices
 * (rank 1). Everything written here is audited; the rule tester runs the
 * real seven-step quote without saving it.
 */
class PricingRuleController extends ApiController
{
    private const POLICY_FIELDS = ['product_id', 'discount_allowed', 'max_discount_pct', 'max_discount_amount', 'min_margin_pct', 'bonus_allowed', 'promo_stackable', 'discount_approval_pct', 'round_to'];

    private const CONTRACT_FIELDS = ['customer_id', 'product_id', 'uom_id', 'unit_price', 'contract_ref', 'effective_from', 'effective_to'];

    // ---------------------------------------------------------------- Promotions

    public function promotions(Request $request): JsonResponse
    {
        $this->requireRead($request);
        $today = now()->toDateString();

        return response()->json(
            Promotion::where('organisation_id', $this->organisationId($request))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', '%'.$request->string('q')->trim().'%')->orWhere('name', 'like', '%'.$request->string('q')->trim().'%')))
                ->when($request->filled('promo_type'), fn ($q) => $q->where('promo_type', $request->string('promo_type')))
                ->when($request->input('status') === 'CURRENT', fn ($q) => $q->where('is_active', true)->whereDate('effective_from', '<=', $today)->whereDate('effective_to', '>=', $today))
                ->when($request->input('status') === 'SCHEDULED', fn ($q) => $q->where('is_active', true)->whereDate('effective_from', '>', $today))
                ->when($request->input('status') === 'EXPIRED', fn ($q) => $q->whereDate('effective_to', '<', $today))
                ->when($request->input('status') === 'INACTIVE', fn ($q) => $q->where('is_active', false))
                ->with('supplier:id,code,name')->withCount('lines')
                ->orderByDesc('effective_from')->orderBy('code')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function promotion(Request $request, string $promotion): JsonResponse
    {
        $this->requireRead($request);

        return response()->json($this->loadPromotion($this->findPromotion($request, $promotion)));
    }

    public function storePromotion(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $organisationId = $this->organisationId($request);
        $data = $request->validate($this->promotionRules($organisationId, null) + $this->promotionLineRules(true));
        $this->validatePromotionLines($organisationId, $data['promo_type'], $data['lines']);

        $promotion = DB::transaction(function () use ($data, $organisationId) {
            $promotion = Promotion::create(collect($data)->except('lines')->all() + ['organisation_id' => $organisationId, 'is_active' => $data['is_active'] ?? true, 'funded_by' => $data['funded_by'] ?? 'US']);
            $this->replaceLines($promotion, $data['lines']);
            AuditLog::record('PROMOTION_CREATED', 'promotion', $promotion->id, ['reference' => $promotion->code, 'after_json' => $this->promotionSnapshot($promotion)]);

            return $promotion;
        });

        return response()->json($this->loadPromotion($promotion), 201);
    }

    public function updatePromotion(Request $request, string $promotion): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $organisationId = $this->organisationId($request);
        $promotion = $this->findPromotion($request, $promotion);
        $data = $request->validate($this->promotionRules($organisationId, $promotion) + $this->promotionLineRules(false));

        $type = $data['promo_type'] ?? $promotion->promo_type;
        $from = $data['effective_from'] ?? $promotion->effective_from->toDateString();
        $to = $data['effective_to'] ?? $promotion->effective_to->toDateString();
        if ($to < $from) {
            throw ValidationException::withMessages(['effective_to' => 'The end date must be on or after the start date.']);
        }
        $lines = $data['lines'] ?? $promotion->lines()->get()->map(fn ($l) => $l->only(['product_id', 'uom_id', 'buy_qty', 'free_qty', 'bonus_product_id', 'promo_price', 'discount_pct', 'max_free_per_order', 'repeat']))->all();
        $this->validatePromotionLines($organisationId, $type, $lines);
        if (($data['funded_by'] ?? $promotion->funded_by) === 'SUPPLIER' && empty($data['supplier_id'] ?? $promotion->supplier_id)) {
            throw ValidationException::withMessages(['supplier_id' => 'A supplier-funded promotion must name the supplier.']);
        }

        DB::transaction(function () use ($promotion, $data) {
            $before = $this->promotionSnapshot($promotion);
            $promotion->update(collect($data)->except('lines')->all());
            if (array_key_exists('lines', $data)) {
                $this->replaceLines($promotion, $data['lines']);
            }
            AuditLog::record('PROMOTION_UPDATED', 'promotion', $promotion->id, ['reference' => $promotion->code, 'before_json' => $before, 'after_json' => $this->promotionSnapshot($promotion->fresh() ?? $promotion), 'changed_fields' => array_keys($data)]);
        });

        return response()->json($this->loadPromotion($promotion->fresh() ?? $promotion));
    }

    public function activatePromotion(Request $request, string $promotion): JsonResponse
    {
        return $this->setPromotionActive($request, $promotion, true);
    }

    public function deactivatePromotion(Request $request, string $promotion): JsonResponse
    {
        return $this->setPromotionActive($request, $promotion, false);
    }

    // ---------------------------------------------------------------- Price breaks

    /** GET /api/pricing-rules/price-breaks — by product_price_id, or by price_list_id and/or product_id. */
    public function priceBreaks(Request $request): JsonResponse
    {
        $this->requireRead($request);
        $organisationId = $this->organisationId($request);

        return response()->json(['data' => PriceBreak::query()
            ->whereHas('productPrice.priceList', fn ($q) => $q->where('organisation_id', $organisationId))
            ->when($request->filled('product_price_id'), fn ($q) => $q->where('product_price_id', $request->string('product_price_id')))
            ->when($request->filled('price_list_id'), fn ($q) => $q->whereHas('productPrice', fn ($p) => $p->where('price_list_id', $request->string('price_list_id'))))
            ->when($request->filled('product_id'), fn ($q) => $q->whereHas('productPrice', fn ($p) => $p->where('product_id', $request->string('product_id'))))
            ->with(['productPrice:id,price_list_id,product_id,uom_id,factor_type,unit_price,effective_from,effective_to', 'productPrice.priceList:id,code,name', 'productPrice.product:id,code,name', 'productPrice.uom:id,code,name'])
            ->orderBy('product_price_id')->orderBy('min_qty')
            ->limit(500)->get()]);
    }

    public function storePriceBreak(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $data = $request->validate($this->priceBreakRules(true));
        $row = $this->findProductPrice($request, $data['product_price_id']);
        $this->assertBreakFits($row, $data, null);

        $break = PriceBreak::create($data + ['break_type' => $data['break_type'] ?? 'STEP']);
        AuditLog::record('PRICE_BREAK_CREATED', 'price_break', $break->id, ['reference' => $row->priceList?->code, 'after_json' => $break->only(['product_price_id', 'min_qty', 'max_qty', 'unit_price', 'break_type'])]);

        return response()->json($break->load(['productPrice.priceList:id,code,name', 'productPrice.product:id,code,name', 'productPrice.uom:id,code,name']), 201);
    }

    public function updatePriceBreak(Request $request, string $break): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $break = $this->findPriceBreak($request, $break);
        $data = $request->validate($this->priceBreakRules(false));
        $row = $this->findProductPrice($request, $break->product_price_id);
        $merged = $data + $break->only(['min_qty', 'max_qty', 'unit_price', 'break_type']);
        $this->assertBreakFits($row, $merged, $break->id);

        $before = $break->only(['min_qty', 'max_qty', 'unit_price', 'break_type']);
        $break->update($data);
        AuditLog::record('PRICE_BREAK_UPDATED', 'price_break', $break->id, ['reference' => $row->priceList?->code, 'before_json' => $before, 'after_json' => $break->only(['min_qty', 'max_qty', 'unit_price', 'break_type']), 'changed_fields' => array_keys($data)]);

        return response()->json($break->load(['productPrice.priceList:id,code,name', 'productPrice.product:id,code,name', 'productPrice.uom:id,code,name']));
    }

    public function destroyPriceBreak(Request $request, string $break): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $break = $this->findPriceBreak($request, $break);
        AuditLog::record('PRICE_BREAK_DELETED', 'price_break', $break->id, ['before_json' => $break->only(['product_price_id', 'min_qty', 'max_qty', 'unit_price', 'break_type'])]);
        $break->delete();

        return response()->json(['deleted' => true]);
    }

    // ---------------------------------------------------------------- Product discount policies

    public function discountPolicies(Request $request): JsonResponse
    {
        $this->requireRead($request);
        $organisationId = $this->organisationId($request);
        $term = $request->string('q')->trim()->value();

        return response()->json(
            ProductDiscountPolicy::query()
                ->whereHas('product', fn ($q) => $q->where('organisation_id', $organisationId)
                    ->when($term !== '', fn ($p) => $p->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"))))
                ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->string('product_id')))
                ->with('product:id,code,name')
                ->orderByDesc('updated_at')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function storeDiscountPolicy(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $data = $request->validate(['product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('organisation_id', $this->organisationId($request)), 'unique:product_discount_policies,product_id']] + $this->discountPolicyRules('required'));

        $policy = ProductDiscountPolicy::create($data);
        AuditLog::record('DISCOUNT_POLICY_CREATED', 'product_discount_policy', $policy->id, ['reference' => Product::whereKey($policy->product_id)->value('code'), 'after_json' => $policy->only(self::POLICY_FIELDS)]);

        return response()->json($policy->load('product:id,code,name'), 201);
    }

    public function updateDiscountPolicy(Request $request, string $policy): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $policy = $this->findPolicy($request, $policy);
        $data = $request->validate($this->discountPolicyRules('sometimes'));

        $before = $policy->only(self::POLICY_FIELDS);
        $policy->update($data);
        AuditLog::record('DISCOUNT_POLICY_UPDATED', 'product_discount_policy', $policy->id, ['reference' => $policy->product?->code, 'before_json' => $before, 'after_json' => $policy->only(self::POLICY_FIELDS), 'changed_fields' => array_keys($data)]);

        return response()->json($policy->load('product:id,code,name'));
    }

    public function destroyDiscountPolicy(Request $request, string $policy): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $policy = $this->findPolicy($request, $policy);
        AuditLog::record('DISCOUNT_POLICY_DELETED', 'product_discount_policy', $policy->id, ['reference' => $policy->product?->code, 'before_json' => $policy->only(self::POLICY_FIELDS)]);
        $policy->delete();

        return response()->json(['deleted' => true]);
    }

    // ---------------------------------------------------------------- Role discount authority

    /** Every role with its authority (null = the role may not discount at all). */
    public function discountAuthorities(Request $request): JsonResponse
    {
        $this->requireRead($request);
        $authorities = RoleDiscountAuthority::whereIn('role_id', Role::query()->select('id'))->get()->keyBy('role_id');

        return response()->json(['data' => Role::whereNull('branch_id')->orderBy('name')->get(['id', 'name'])
            ->map(fn (Role $role) => ['role_id' => $role->id, 'role' => $role->name, 'authority' => $authorities->get($role->id)])]);
    }

    public function upsertDiscountAuthority(Request $request, string $role): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $role = Role::whereNull('branch_id')->findOrFail((int) $role);
        $data = $request->validate([
            'max_line_discount_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_header_discount_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'may_override_floor' => ['required', 'boolean'],
        ]);

        $existing = RoleDiscountAuthority::where('role_id', $role->id)->first();
        $before = $existing?->only(['max_line_discount_pct', 'max_header_discount_pct', 'may_override_floor']);
        $authority = RoleDiscountAuthority::updateOrCreate(['role_id' => $role->id], $data);
        AuditLog::record('DISCOUNT_AUTHORITY_CHANGED', 'role_discount_authority', $authority->id, [
            'reference' => $role->name, 'before_json' => $before, 'after_json' => $authority->only(['max_line_discount_pct', 'max_header_discount_pct', 'may_override_floor']),
        ]);

        return response()->json(['role_id' => $role->id, 'role' => $role->name, 'authority' => $authority]);
    }

    public function destroyDiscountAuthority(Request $request, string $role): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $role = Role::whereNull('branch_id')->findOrFail((int) $role);
        $authority = RoleDiscountAuthority::where('role_id', $role->id)->firstOrFail();
        AuditLog::record('DISCOUNT_AUTHORITY_REMOVED', 'role_discount_authority', $authority->id, ['reference' => $role->name, 'before_json' => $authority->only(['max_line_discount_pct', 'max_header_discount_pct', 'may_override_floor'])]);
        $authority->delete();

        return response()->json(['role_id' => $role->id, 'role' => $role->name, 'authority' => null]);
    }

    // ---------------------------------------------------------------- Customer contract prices

    public function customerPrices(Request $request): JsonResponse
    {
        $this->requireRead($request);
        $today = now()->toDateString();

        return response()->json(
            CustomerPrice::where('organisation_id', $this->organisationId($request))
                ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->string('customer_id')))
                ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->string('product_id')))
                ->when($request->input('status') === 'CURRENT', fn ($q) => $q->whereDate('effective_from', '<=', $today)->whereDate('effective_to', '>=', $today))
                ->when($request->input('status') === 'FUTURE', fn ($q) => $q->whereDate('effective_from', '>', $today))
                ->when($request->input('status') === 'EXPIRED', fn ($q) => $q->whereDate('effective_to', '<', $today))
                ->with(['customer:id,code,name', 'product:id,code,name', 'uom:id,code,name', 'approver:id,name'])
                ->orderByDesc('effective_from')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function storeCustomerPrice(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $organisationId = $this->organisationId($request);
        $data = $request->validate([
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->where('organisation_id', $organisationId)],
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('organisation_id', $organisationId)],
            'uom_id' => ['required', 'uuid', TenantRules::exists('units_of_measure')],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'contract_ref' => ['required', 'string', 'max:100'],
            'effective_from' => ['required', 'date'],
            // Part 4.2 — an undated contract price is the classic silent margin leak; the end date is mandatory.
            'effective_to' => ['required', 'date', 'after_or_equal:effective_from'],
        ]);
        $this->assertSalesUom($data['product_id'], $data['uom_id'], 'uom_id');
        $this->assertNoContractOverlap($data, null);

        $price = CustomerPrice::create($data + ['organisation_id' => $organisationId, 'approved_by' => $request->user()->id]);
        AuditLog::record('CUSTOMER_PRICE_CREATED', 'customer_price', $price->id, ['reference' => $price->contract_ref, 'after_json' => $price->only(self::CONTRACT_FIELDS)]);

        return response()->json($price->load(['customer:id,code,name', 'product:id,code,name', 'uom:id,code,name', 'approver:id,name']), 201);
    }

    public function updateCustomerPrice(Request $request, string $price): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $price = CustomerPrice::where('organisation_id', $this->organisationId($request))->findOrFail($price);
        $data = $request->validate([
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'contract_ref' => ['sometimes', 'string', 'max:100'],
            'effective_from' => ['sometimes', 'date'],
            'effective_to' => ['sometimes', 'date'],
        ]);
        $merged = $data + ['customer_id' => $price->customer_id, 'product_id' => $price->product_id, 'uom_id' => $price->uom_id,
            'effective_from' => $price->effective_from->toDateString(), 'effective_to' => $price->effective_to->toDateString()];
        if ($merged['effective_to'] < $merged['effective_from']) {
            throw ValidationException::withMessages(['effective_to' => 'The end date must be on or after the start date.']);
        }
        $this->assertNoContractOverlap($merged, $price->id);

        $before = $price->only(self::CONTRACT_FIELDS);
        $price->update($data + ['approved_by' => $request->user()->id]);
        AuditLog::record('CUSTOMER_PRICE_UPDATED', 'customer_price', $price->id, ['reference' => $price->contract_ref, 'before_json' => $before, 'after_json' => $price->only(self::CONTRACT_FIELDS), 'changed_fields' => array_keys($data)]);

        return response()->json($price->load(['customer:id,code,name', 'product:id,code,name', 'uom:id,code,name', 'approver:id,name']));
    }

    /** Only a contract that has not started yet may be deleted; one in force is ended by moving its end date. */
    public function destroyCustomerPrice(Request $request, string $price): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $price = CustomerPrice::where('organisation_id', $this->organisationId($request))->findOrFail($price);
        if ($price->effective_from->lte(now()->startOfDay())) {
            return $this->error('CONTRACT_IN_EFFECT', 'This contract price has already applied to quotes; end it by setting its end date instead of deleting it.', 422);
        }

        AuditLog::record('CUSTOMER_PRICE_DELETED', 'customer_price', $price->id, ['reference' => $price->contract_ref, 'before_json' => $price->only(self::CONTRACT_FIELDS)]);
        $price->delete();

        return response()->json(['deleted' => true]);
    }

    // ---------------------------------------------------------------- Rule tester

    /**
     * POST /api/pricing-rules/test — the full seven-step quote for one line
     * exactly as the POS would see it, with its explain trail. Nothing is
     * saved and no quote_id is issued.
     */
    public function test(Request $request, PriceQuoteService $quotes): JsonResponse
    {
        $this->requirePermission($request, 'price.simulate');
        $organisationId = $this->organisationId($request);
        $branchId = $this->branchId($request);
        $data = $request->validate([
            'sale_mode' => ['required', 'in:RETAIL,WHOLESALE,DISPENSING'],
            'store_id' => ['required', 'uuid', Rule::exists('stores', 'id')->where('branch_id', $branchId)],
            'customer_id' => ['nullable', 'uuid', Rule::exists('customers', 'id')->where('organisation_id', $organisationId)],
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('organisation_id', $organisationId)],
            'uom_id' => ['required', 'uuid', TenantRules::exists('units_of_measure')],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'requested_discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'quote_date' => ['nullable', 'date'],
        ]);

        try {
            $quote = $quotes->quote([
                'organisation_id' => $organisationId,
                'branch_id' => $branchId,
                'store_id' => $data['store_id'],
                'sale_mode' => $data['sale_mode'],
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $request->user()->id,
                'quote_date' => $data['quote_date'] ?? null,
                'lines' => [[
                    'line_ref' => 'L1',
                    'product_id' => $data['product_id'],
                    'uom_id' => $data['uom_id'],
                    'quantity' => (string) $data['quantity'],
                    'requested_discount_pct' => isset($data['requested_discount_pct']) ? (string) $data['requested_discount_pct'] : null,
                    'requested_discount_reason' => isset($data['requested_discount_pct']) ? 'Pricing rule test' : null,
                ]],
            ], persist: false);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->error('QUOTE_FAILED', $e->getMessage(), 422);
        }

        return response()->json($quote);
    }

    // ---------------------------------------------------------------- Helpers

    /** Reading the rules is open to anyone who prices or sells. */
    private function requireRead(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! ($user->can('price.simulate') || $user->can('price.manage') || $user->can('sale.view'))) {
            throw new HttpException(403, "You do not have the 'price.simulate' permission.");
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function promotionRules(string $organisationId, ?Promotion $existing): array
    {
        $required = $existing ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:30', Rule::unique('promotions', 'code')->where('organisation_id', $organisationId)->ignore($existing?->id)],
            'name' => [$required, 'string', 'max:150'],
            'promo_type' => [$required, 'in:PRICE_OVERRIDE,PERCENT_OFF,BUY_X_GET_Y'],
            'effective_from' => [$required, 'date'],
            'effective_to' => [$required, 'date', ...($existing ? [] : ['after_or_equal:effective_from'])],
            'customer_scope' => ['nullable', 'uuid', Rule::exists('customers', 'id')->where('organisation_id', $organisationId)],
            'branch_scope' => ['nullable', 'uuid', Rule::exists('branches', 'id')->where('organisation_id', $organisationId)],
            'funded_by' => ['sometimes', 'in:SUPPLIER,US'],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->where('organisation_id', $organisationId), ...($existing ? [] : ['required_if:funded_by,SUPPLIER'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function promotionLineRules(bool $required): array
    {
        return [
            'lines' => [$required ? 'required' : 'sometimes', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['required', 'uuid', TenantRules::exists('products')],
            'lines.*.uom_id' => ['required', 'uuid', TenantRules::exists('units_of_measure')],
            'lines.*.buy_qty' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.free_qty' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.bonus_product_id' => ['nullable', 'uuid', TenantRules::exists('products')],
            'lines.*.promo_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_pct' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'lines.*.max_free_per_order' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.repeat' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Each line must carry the field its promotion type is priced by —
     * PricingEngine and BonusResolver silently skip a line that does not.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function validatePromotionLines(string $organisationId, string $type, array $lines): void
    {
        $errors = [];
        $seen = [];
        foreach ($lines as $i => $line) {
            if (! Product::whereKey($line['product_id'])->where('organisation_id', $organisationId)->exists()) {
                $errors["lines.{$i}.product_id"] = 'Unknown product.';

                continue;
            }
            if (! ProductUom::where('product_id', $line['product_id'])->where('uom_id', $line['uom_id'])->where('is_sales', true)->exists()) {
                $errors["lines.{$i}.uom_id"] = 'That unit is not a sales unit of the product.';
            }
            $key = $line['product_id'].'|'.$line['uom_id'];
            if (isset($seen[$key])) {
                $errors["lines.{$i}.product_id"] = 'The same product and unit appear twice.';
            }
            $seen[$key] = true;

            $missing = match ($type) {
                'PRICE_OVERRIDE' => ($line['promo_price'] ?? null) === null ? ['promo_price', 'A price-override promotion needs a promo price.'] : null,
                'PERCENT_OFF' => ($line['discount_pct'] ?? null) === null ? ['discount_pct', 'A percent-off promotion needs a discount %.'] : null,
                default => ($line['buy_qty'] ?? null) === null || ($line['free_qty'] ?? null) === null ? ['buy_qty', 'A buy-X-get-Y promotion needs both buy and free quantities.'] : null,
            };
            if ($missing) {
                $errors["lines.{$i}.{$missing[0]}"] = $missing[1];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function replaceLines(Promotion $promotion, array $lines): void
    {
        $promotion->lines()->delete();
        foreach ($lines as $line) {
            $promotion->lines()->create([
                'product_id' => $line['product_id'],
                'uom_id' => $line['uom_id'],
                'buy_qty' => $line['buy_qty'] ?? null,
                'free_qty' => $line['free_qty'] ?? null,
                'bonus_product_id' => $line['bonus_product_id'] ?? null,
                'promo_price' => $line['promo_price'] ?? null,
                'discount_pct' => $line['discount_pct'] ?? null,
                'max_free_per_order' => $line['max_free_per_order'] ?? null,
                'repeat' => (bool) ($line['repeat'] ?? true),
            ]);
        }
    }

    private function setPromotionActive(Request $request, string $promotion, bool $active): JsonResponse
    {
        $this->requirePermission($request, 'price.manage');
        $promotion = $this->findPromotion($request, $promotion);
        if ($active && $promotion->effective_to->lt(now()->startOfDay())) {
            return $this->error('PROMOTION_EXPIRED', "Promotion {$promotion->code} ended on {$promotion->effective_to->toDateString()}; extend its end date before activating it.", 422);
        }

        $promotion->update(['is_active' => $active]);
        AuditLog::record($active ? 'PROMOTION_ACTIVATED' : 'PROMOTION_DEACTIVATED', 'promotion', $promotion->id, ['reference' => $promotion->code, 'before_json' => ['is_active' => ! $active], 'after_json' => ['is_active' => $active]]);

        return response()->json($this->loadPromotion($promotion));
    }

    private function findPromotion(Request $request, string $id): Promotion
    {
        return Promotion::where('organisation_id', $this->organisationId($request))->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadPromotion(Promotion $promotion): array
    {
        $promotion->load(['supplier:id,code,name', 'lines.product:id,code,name', 'lines.bonusProduct:id,code,name'])->loadCount('lines');

        return $promotion->toArray() + [
            'customer' => $promotion->customer_scope ? Customer::whereKey($promotion->customer_scope)->first(['id', 'code', 'name']) : null,
            'branch' => $promotion->branch_scope ? Branch::whereKey($promotion->branch_scope)->first(['id', 'code', 'name']) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function promotionSnapshot(Promotion $promotion): array
    {
        return $promotion->only(['code', 'name', 'promo_type', 'effective_from', 'effective_to', 'customer_scope', 'branch_scope', 'funded_by', 'supplier_id', 'is_active'])
            + ['lines' => $promotion->lines()->get(['product_id', 'uom_id', 'buy_qty', 'free_qty', 'promo_price', 'discount_pct'])->toArray()];
    }

    /**
     * @return array<string, list<string>>
     */
    private function priceBreakRules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return array_filter([
            'product_price_id' => $creating ? ['required', 'uuid'] : null,
            'min_qty' => [$required, 'numeric', 'gt:0'],
            'max_qty' => ['nullable', 'numeric', 'gt:0'],
            'unit_price' => [$required, 'numeric', 'min:0'],
            'break_type' => ['sometimes', 'in:STEP,MARGINAL'],
        ]);
    }

    /**
     * Breaks on one price row may not overlap, and must all be the same
     * type — PricingEngine reads STEP from the matching break but walks
     * every break for MARGINAL.
     *
     * @param  array<string, mixed>  $candidate
     */
    private function assertBreakFits(ProductPrice $row, array $candidate, ?string $exceptId): void
    {
        $min = (string) $candidate['min_qty'];
        $max = isset($candidate['max_qty']) ? (string) $candidate['max_qty'] : null;
        if ($max !== null && bccomp($max, $min, 4) < 0) {
            throw ValidationException::withMessages(['max_qty' => 'The upper quantity must be at least the lower quantity.']);
        }
        $type = $candidate['break_type'] ?? 'STEP';

        $others = $row->priceBreaks()->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->get();
        foreach ($others as $other) {
            if ($other->break_type !== $type) {
                throw ValidationException::withMessages(['break_type' => "This price row's breaks are {$other->break_type}; all breaks on one row must be the same type."]);
            }
            $otherMax = $other->max_qty !== null ? (string) $other->max_qty : null;
            $startsBeforeOtherEnds = $otherMax === null || bccomp($min, $otherMax, 4) <= 0;
            $endsAfterOtherStarts = $max === null || bccomp($max, (string) $other->min_qty, 4) >= 0;
            if ($startsBeforeOtherEnds && $endsAfterOtherStarts) {
                throw ValidationException::withMessages(['min_qty' => "Overlaps the existing break {$other->min_qty}–".($otherMax ?? '∞').'.']);
            }
        }
    }

    private function findProductPrice(Request $request, string $id): ProductPrice
    {
        $organisationId = $this->organisationId($request);

        return ProductPrice::whereKey($id)->whereHas('priceList', fn ($q) => $q->where('organisation_id', $organisationId))->with('priceList')->firstOrFail();
    }

    private function findPriceBreak(Request $request, string $id): PriceBreak
    {
        $organisationId = $this->organisationId($request);

        return PriceBreak::whereKey($id)->whereHas('productPrice.priceList', fn ($q) => $q->where('organisation_id', $organisationId))->firstOrFail();
    }

    /**
     * @return array<string, list<string>>
     */
    private function discountPolicyRules(string $required): array
    {
        return [
            'discount_allowed' => ['sometimes', 'boolean'],
            'max_discount_pct' => [$required, 'numeric', 'min:0', 'max:100'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'min_margin_pct' => [$required, 'numeric', 'min:0', 'max:99'],
            'bonus_allowed' => ['sometimes', 'boolean'],
            'promo_stackable' => ['sometimes', 'boolean'],
            'discount_approval_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'round_to' => ['sometimes', 'in:NONE,FIVE_CENTS,TEN_CENTS,FIFTY_CENTS,WHOLE'],
        ];
    }

    private function findPolicy(Request $request, string $id): ProductDiscountPolicy
    {
        $organisationId = $this->organisationId($request);

        return ProductDiscountPolicy::whereKey($id)->whereHas('product', fn ($q) => $q->where('organisation_id', $organisationId))->firstOrFail();
    }

    private function assertSalesUom(string $productId, string $uomId, string $field): void
    {
        if (! ProductUom::where('product_id', $productId)->where('uom_id', $uomId)->where('is_sales', true)->exists()) {
            throw ValidationException::withMessages([$field => 'That unit is not a sales unit of the product.']);
        }
    }

    /**
     * Two contract prices for the same customer, product and unit may not
     * be in force on the same day — the engine would pick one arbitrarily.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertNoContractOverlap(array $data, ?string $exceptId): void
    {
        $clash = CustomerPrice::where('customer_id', $data['customer_id'])->where('product_id', $data['product_id'])->where('uom_id', $data['uom_id'])
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->whereDate('effective_from', '<=', $data['effective_to'])->whereDate('effective_to', '>=', $data['effective_from'])
            ->first();

        if ($clash) {
            throw ValidationException::withMessages(['effective_from' => "Overlaps contract {$clash->contract_ref} ({$clash->effective_from->toDateString()} to {$clash->effective_to->toDateString()})."]);
        }
    }
}
