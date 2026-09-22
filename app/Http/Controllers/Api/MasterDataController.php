<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\CustomerTier;
use App\Models\DosageForm;
use App\Models\StorageCondition;
use App\Models\Store;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MasterDataController extends ApiController
{
    public function customers(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'sale.create');

        $allowedSorts = ['name', 'code', 'customer_type', 'created_at'];
        $sortBy = in_array($request->query('sort_by'), $allowedSorts, true) ? $request->query('sort_by') : 'name';
        $sortDir = strtolower((string) $request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $customers = Customer::where('organisation_id', $this->organisationId($request))
            ->when($request->string('q')->trim()->isNotEmpty(), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('code', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->with(['tier:id,code,name', 'credit'])
            ->orderBy($sortBy, $sortDir)
            ->paginate($request->integer('per_page', 25));

        $customers->getCollection()->transform(fn (Customer $c) => $c->toArray() + [
            'available_credit' => $c->credit?->availableCredit() ?? '0.0000',
        ]);

        return response()->json($customers);
    }

    public function customer(Request $request, string $customer): JsonResponse
    {
        $this->requirePermission($request, 'sale.create');

        $customer = Customer::where('organisation_id', $this->organisationId($request))->with(['tier', 'credit', 'contacts'])->findOrFail($customer);

        return response()->json($customer->toArray() + [
            'available_credit' => $customer->credit?->availableCredit() ?? '0.0000',
            'open_order_exposure' => $customer->credit?->openOrderExposure() ?? '0.0000',
        ]);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'customer.manage');
        $organisationId = $this->organisationId($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('customers', 'code')->where('organisation_id', $organisationId)],
            'name' => ['required', 'string', 'max:150'],
            'customer_type' => ['required', 'in:WALK_IN,RETAIL_PHARMACY,HOSPITAL,CLINIC,NGO,GOVERNMENT,TENDER,INSTITUTION'],
            'tier_id' => ['nullable', 'uuid', TenantRules::exists('customer_tiers')],
            'tax_status' => ['nullable', 'in:STANDARD,EXEMPT,ZERO_RATED,WITHHOLDING_AGENT'],
            'exemption_ref' => ['nullable', 'string', 'max:100'],
            'exemption_expiry' => ['nullable', 'date'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'fulfilment_policy' => ['nullable', 'in:PARTIAL,COMPLETE,CANCEL_SHORTFALL'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $customer = DB::transaction(function () use ($data, $organisationId, $request) {
            $customer = Customer::create(collect($data)->except('credit_limit')->all() + [
                'organisation_id' => $organisationId,
                'created_by' => $request->user()->id,
            ]);
            CustomerCredit::create(['customer_id' => $customer->id, 'credit_limit' => (string) ($data['credit_limit'] ?? '0')]);

            return $customer;
        });

        return response()->json($customer->load('credit'), 201);
    }

    public function updateCustomer(Request $request, string $customer): JsonResponse
    {
        $this->requirePermission($request, 'customer.manage');
        $organisationId = $this->organisationId($request);

        $customer = Customer::where('organisation_id', $organisationId)->findOrFail($customer);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('customers', 'code')->where('organisation_id', $organisationId)->ignore($customer->id)],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'customer_type' => ['sometimes', 'required', 'in:WALK_IN,RETAIL_PHARMACY,HOSPITAL,CLINIC,NGO,GOVERNMENT,TENDER,INSTITUTION'],
            'tier_id' => ['nullable', 'uuid', TenantRules::exists('customer_tiers')],
            'tax_status' => ['sometimes', 'required', 'in:STANDARD,EXEMPT,ZERO_RATED,WITHHOLDING_AGENT'],
            'exemption_ref' => ['nullable', 'string', 'max:100'],
            'exemption_expiry' => ['nullable', 'date'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'fulfilment_policy' => ['sometimes', 'required', 'in:PARTIAL,COMPLETE,CANCEL_SHORTFALL'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $before = $customer->only([
            'code', 'name', 'customer_type', 'tier_id', 'tax_status',
            'exemption_ref', 'exemption_expiry', 'payment_terms_days',
            'fulfilment_policy', 'phone', 'email', 'address', 'is_active',
        ]);

        $updateData = $data;
        if (empty($updateData['code'])) {
            unset($updateData['code']);
        }
        $updateData['updated_by'] = $request->user()->id;

        $customer->update($updateData);

        AuditLog::record('CUSTOMER_UPDATED', 'customer', $customer->id, [
            'reference' => $customer->code,
            'before_json' => $before,
            'after_json' => $customer->fresh()->only(array_keys($before)),
        ]);

        $customer->load(['tier', 'credit', 'contacts']);

        return response()->json($customer->toArray() + [
            'available_credit' => $customer->credit?->availableCredit() ?? '0.0000',
            'open_order_exposure' => $customer->credit?->openOrderExposure() ?? '0.0000',
        ]);
    }

    public function updateCredit(Request $request, string $customer): JsonResponse
    {
        $this->requirePermission($request, 'customer.credit.override');

        $data = $request->validate([
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'on_hold' => ['nullable', 'boolean'],
            'hold_reason' => ['nullable', 'string', 'max:255', 'required_if:on_hold,true'],
        ]);

        $customer = Customer::where('organisation_id', $this->organisationId($request))->findOrFail($customer);
        $credit = CustomerCredit::firstOrCreate(['customer_id' => $customer->id], ['credit_limit' => '0']);
        $before = $credit->only(['credit_limit', 'on_hold', 'hold_reason']);

        $credit->update(array_filter([
            'credit_limit' => isset($data['credit_limit']) ? (string) $data['credit_limit'] : null,
            'on_hold' => $data['on_hold'] ?? null,
            'hold_reason' => array_key_exists('on_hold', $data) ? (($data['on_hold'] ?? false) ? $data['hold_reason'] : null) : null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ], fn ($v) => $v !== null) + (array_key_exists('on_hold', $data) ? ['hold_reason' => ($data['on_hold'] ?? false) ? ($data['hold_reason'] ?? null) : null] : []));

        AuditLog::record('CREDIT_LIMIT_CHANGED', 'customer', $customer->id, [
            'reference' => $customer->code,
            'before_json' => $before,
            'after_json' => $credit->fresh()->only(['credit_limit', 'on_hold', 'hold_reason']),
        ]);

        return response()->json($credit->fresh());
    }

    public function tiers(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'sale.create');

        return response()->json(CustomerTier::where('organisation_id', $this->organisationId($request))->orderBy('code')->get());
    }

    public function stores(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        return response()->json(Store::where('branch_id', $this->branchId($request))->orderBy('code')->get());
    }

    public function uoms(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'product.view');

        return response()->json(UnitOfMeasure::orderBy('code')->get());
    }

    /** GET /api/dosage-forms — the forms the product form offers (Part 5.2). */
    public function dosageForms(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'product.view');

        return response()->json(DosageForm::orderBy('name')->get());
    }

    /**
     * GET /api/storage-conditions — the temperature ranges a store or product
     * is held to, and what cold-chain monitoring measures against (Part 8.5).
     */
    public function storageConditions(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'product.view');

        return response()->json(
            // The shared standard conditions plus this institution's own.
            StorageCondition::orderBy('code')->get()
        );
    }

    /** GET /api/tax-codes — every active code with the rate in force today, for the product form (Part 13). */
    public function taxCodes(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'product.view');

        $codes = TaxCode::where('organisation_id', $this->organisationId($request))->where('is_active', true)->orderBy('code')->get();

        return response()->json($codes->map(fn (TaxCode $code) => $code->only(['id', 'code', 'name', 'tax_type', 'is_recoverable']) + [
            'rate_pct' => $code->currentRate()?->rate_pct,
        ])->values());
    }
}
