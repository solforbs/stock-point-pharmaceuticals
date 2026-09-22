<?php

namespace App\Http\Controllers\Api;

use App\Mail\PurchaseOrderSentMail;
use App\Models\AccountsPayable;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\NumberSequence;
use App\Models\Organisation;
use App\Models\PriceList;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceLine;
use App\Services\Notifications\Notifier;
use App\Services\Pricing\PriceListWriter;
use App\Services\Procurement\GoodsReceiptService;
use App\Services\Procurement\SupplierPaymentService;
use App\Services\Procurement\ThreeWayMatchService;
use App\Services\Procurement\TradeTerms;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProcurementController extends ApiController
{
    public function suppliers(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'supplier.view');

        $allowedSorts = ['name', 'code', 'licence_expiry', 'payment_terms_days', 'created_at'];
        $sortBy = in_array($request->query('sort_by'), $allowedSorts, true) ? $request->query('sort_by') : 'name';
        $sortDir = strtolower((string) $request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $suppliers = Supplier::where('organisation_id', $this->organisationId($request))
            ->when($request->string('q')->trim()->isNotEmpty(), fn ($q) => $q->where('name', 'like', '%'.$request->string('q')->trim().'%'))
            ->orderBy($sortBy, $sortDir)
            ->paginate($request->integer('per_page', 25));

        $suppliers->getCollection()->transform(fn (Supplier $s) => $s->toArray() + ['payable_balance' => AccountsPayable::balanceFor($s->id)]);

        return response()->json($suppliers);
    }

    public function supplier(Request $request, string $supplier): JsonResponse
    {
        $this->requirePermission($request, 'supplier.view');

        $supplier = Supplier::where('organisation_id', $this->organisationId($request))->findOrFail($supplier);

        return response()->json($supplier->toArray() + ['payable_balance' => AccountsPayable::balanceFor($supplier->id)]);
    }

    /** POST /api/suppliers — Part 9.6 supplier master. */
    public function storeSupplier(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'supplier.manage');
        $organisationId = $this->organisationId($request);

        $data = $request->validate($this->supplierRules($organisationId, null));
        $supplier = Supplier::create($data + [
            'organisation_id' => $organisationId,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        AuditLog::record('SUPPLIER_CREATED', 'supplier', $supplier->id, ['reference' => $supplier->code]);

        return response()->json($supplier->fresh(), 201);
    }

    /** PATCH /api/suppliers/{id} — status, licence, terms and bank details; bank changes are audited by field, not value (Part 19.2). */
    public function updateSupplier(Request $request, string $supplier): JsonResponse
    {
        $this->requirePermission($request, 'supplier.manage');
        $organisationId = $this->organisationId($request);
        $supplier = Supplier::where('organisation_id', $organisationId)->findOrFail($supplier);

        $data = $request->validate($this->supplierRules($organisationId, $supplier));
        $audited = ['status', 'is_active', 'licence_number', 'licence_expiry', 'payment_terms_days', 'lead_time_days'];
        $before = $supplier->only($audited);
        $supplier->update($data + ['updated_by' => $request->user()->id]);

        AuditLog::record('SUPPLIER_UPDATED', 'supplier', $supplier->id, [
            'reference' => $supplier->code,
            'before_json' => $before,
            'after_json' => $supplier->fresh()->only($audited) + ['changed_fields' => array_keys($data)],
        ]);

        return response()->json($supplier->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierRules(string $organisationId, ?Supplier $existing): array
    {
        $required = $existing ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:30', Rule::unique('suppliers', 'code')->where('organisation_id', $organisationId)->ignore($existing?->id)],
            'name' => [$required, 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'licence_number' => ['nullable', 'string', 'max:100'],
            'licence_expiry' => ['nullable', 'date'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'currency' => ['nullable', 'string', 'size:3'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'in:ACTIVE,SUSPENDED,BLACKLISTED'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** POST /api/purchase-orders — supplier must be active and licensed (Part 9.6). */
    public function storePurchaseOrder(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'po.create');

        $data = $request->validate([
            'supplier_id' => ['required', 'uuid', TenantRules::exists('suppliers')],
            'expected_date' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', TenantRules::exists('products')],
            'lines.*.uom_id' => ['required', 'uuid', TenantRules::exists('units_of_measure')],
            'lines.*.qty_ordered' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['nullable', 'required_without:lines.*.trade_price', 'numeric', 'min:0'],
            'lines.*.trade_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_code_id' => ['nullable', 'uuid', TenantRules::exists('tax_codes')],
        ]);
        $data['lines'] = array_map(fn (array $line) => TradeTerms::applyTo($line, 'unit_price'), $data['lines']);

        $supplier = Supplier::findOrFail($data['supplier_id']);
        if ($supplier->status !== 'ACTIVE' || ! $supplier->is_active) {
            return $this->error('SUPPLIER_BLOCKED', "Supplier {$supplier->name} is {$supplier->status}; no purchase order may be raised.", 422);
        }
        if ($supplier->licence_expiry && $supplier->licence_expiry->isPast()) {
            return $this->error('SUPPLIER_LICENCE_EXPIRED', "Supplier {$supplier->name}'s licence expired on {$supplier->licence_expiry->toDateString()}.", 422, ['expiry_date' => $supplier->licence_expiry->toDateString()]);
        }

        $branchId = $this->branchId($request);
        $po = DB::transaction(function () use ($data, $branchId, $request) {
            $po = PurchaseOrder::create([
                'doc_number' => NumberSequence::next($this->organisationId($request), 'PO', $branchId, 'PO'),
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $branchId,
                'status' => 'DRAFT',
                'created_by' => $request->user()->id,
                'expected_date' => $data['expected_date'] ?? null,
            ]);
            foreach ($data['lines'] as $line) {
                PurchaseOrderLine::create(['purchase_order_id' => $po->id] + collect($line)->only(['product_id', 'uom_id', 'qty_ordered', 'unit_price', 'trade_price', 'discount_pct', 'tax_code_id'])->all());
            }
            AuditLog::record('PO_CREATED', 'purchase_order', $po->id, ['reference' => $po->doc_number]);

            return $po;
        });

        return response()->json($po->load('lines'), 201);
    }

    public function approvePurchaseOrder(Request $request, string $po): JsonResponse
    {
        $this->requirePermission($request, 'po.approve');
        $po = $this->findPo($request, $po);
        if (! in_array($po->status, ['DRAFT', 'PENDING_APPROVAL'], true)) {
            return $this->error('INVALID_STATE', "Purchase order {$po->doc_number} is {$po->status}.", 409);
        }

        $po->update(['status' => 'APPROVED', 'approved_by' => $request->user()->id]);
        AuditLog::record('PO_APPROVED', 'purchase_order', $po->id, ['reference' => $po->doc_number]);

        return response()->json($po->fresh('lines'));
    }

    public function sendPurchaseOrder(Request $request, string $po, Notifier $notifier): JsonResponse
    {
        $this->requirePermission($request, 'po.create');
        $po = $this->findPo($request, $po);
        if ($po->status !== 'APPROVED') {
            return $this->error('INVALID_STATE', "Purchase order {$po->doc_number} must be APPROVED before it is sent; it is {$po->status}.", 409);
        }

        $po->update(['status' => 'SENT', 'sent_at' => now()]);
        $po->load(['lines.product:id,code,name', 'lines.uom:id,code', 'supplier']);

        // Sending the order is what starts the supplier's dispatch, so this
        // is the moment they are told. A supplier is not a user of this
        // system, so the only way to reach them is the address on their
        // record; a missing one is reported rather than silently ignored.
        $delivered = $this->emailSupplier($request, $po);

        $notifier->toPermission(
            'grn.create',
            $this->branchId($request),
            "Purchase order {$po->doc_number} sent to {$po->supplier?->name}",
            $delivered
                ? "{$po->supplier?->name} has been emailed and can dispatch. Expect delivery".($po->expected_date ? ' by '.$po->expected_date->format('j M Y') : '').'.'
                : "The order is marked sent, but {$po->supplier?->name} has no email address on file — send it to them by hand.",
            category: 'PURCHASE_ORDER',
            link: '/buy/purchase-orders?po='.$po->id,
            priority: $delivered ? 'NORMAL' : 'HIGH',
            exceptUserId: null,
        );

        return response()->json($po->fresh('lines')->toArray() + ['supplier_notified' => $delivered]);
    }

    /**
     * Emails the order to the supplier. Returns false when there is nobody
     * to send it to, or the mail could not be handed over.
     */
    private function emailSupplier(Request $request, PurchaseOrder $po): bool
    {
        $address = trim((string) ($po->supplier->email ?? ''));
        if ($address === '') {
            return false;
        }

        $branch = Branch::find($this->branchId($request));
        $organisationName = (string) (Organisation::where('id', $branch?->organisation_id)->value('name') ?? config('app.name'));

        try {
            Mail::to($address, $po->supplier->contact_name ?: $po->supplier->name)->send(
                new PurchaseOrderSentMail($po, $organisationName, (string) $branch?->name)
            );

            AuditLog::record('PURCHASE_ORDER_SENT_TO_SUPPLIER', 'purchase_order', $po->id, [
                'reference' => $po->doc_number,
                'after_json' => ['to' => $address, 'supplier' => $po->supplier->code],
            ]);

            return true;
        } catch (\Throwable $e) {
            // The order is already SENT in the books; failing the request now
            // would leave the two disagreeing. Report it instead.
            Log::error('Purchase order email failed', ['po' => $po->doc_number, 'to' => $address, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function purchaseOrders(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'po.create');

        return response()->json(
            PurchaseOrder::where('branch_id', $this->branchId($request))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->with('supplier:id,code,name')->withCount('lines')
                ->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 25))
        );
    }

    /** POST /api/goods-receipts — lines mandatory; batch + expiry mandatory (Part 9.2). */
    public function storeGoodsReceipt(Request $request, GoodsReceiptService $receipts, PriceListWriter $prices): JsonResponse
    {
        $this->requirePermission($request, 'grn.create');

        $data = $request->validate([
            'purchase_order_id' => ['nullable', 'uuid', TenantRules::exists('purchase_orders')],
            'supplier_id' => ['required', 'uuid', TenantRules::exists('suppliers')],
            'store_id' => ['required', 'uuid', TenantRules::exists('stores')],
            'is_emergency' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['nullable', 'uuid', TenantRules::exists('purchase_order_lines')],
            'lines.*.product_id' => ['required', 'uuid', TenantRules::exists('products')],
            'lines.*.uom_id' => ['required', 'uuid', TenantRules::exists('units_of_measure')],
            'lines.*.qty_delivered' => ['required', 'numeric', 'min:0'],
            'lines.*.qty_accepted' => ['required', 'numeric', 'min:0'],
            'lines.*.qty_rejected' => ['nullable', 'numeric', 'min:0'],
            'lines.*.rejection_reason' => ['nullable', 'string', 'max:255'],
            'lines.*.batch_number' => ['required', 'string', 'max:100'],
            'lines.*.expiry_date' => ['required', 'date'],
            'lines.*.manufacture_date' => ['nullable', 'date'],
            'lines.*.unit_cost' => ['nullable', 'required_without:lines.*.trade_price', 'numeric', 'min:0'],
            'lines.*.trade_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.temperature_on_arrival' => ['nullable', 'numeric'],
            'lines.*.coa_received' => ['nullable', 'boolean'],
            'lines.*.selling_prices' => ['nullable', 'array'],
            'lines.*.selling_prices.*.price_list_id' => ['required', 'uuid', 'distinct', TenantRules::exists('price_lists')],
            'lines.*.selling_prices.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
        $data['lines'] = array_map(fn (array $line) => TradeTerms::applyTo($line, 'unit_cost'), $data['lines']);

        if (empty($data['purchase_order_id']) && ! ($data['is_emergency'] ?? false)) {
            return $this->error('PO_REQUIRED', 'A goods receipt must reference a purchase order unless it is flagged as an emergency receipt (Part 9.2).', 422);
        }

        // Selling prices set on arrival are a price change like any other.
        $setsPrices = collect($data['lines'])->contains(fn (array $line) => ! empty($line['selling_prices']));
        if ($setsPrices && ! $request->user()->can('price.manage')) {
            return $this->error('FORBIDDEN', "Setting selling prices needs the 'price.manage' permission. Post the receipt without them, or ask a manager.", 403);
        }

        foreach ($data['lines'] as $i => $line) {
            $rejected = (string) ($line['qty_rejected'] ?? '0');
            if (bccomp(bcadd((string) $line['qty_accepted'], $rejected, 4), (string) $line['qty_delivered'], 4) > 0) {
                return $this->error('INVALID_INPUT', 'Line '.($i + 1).': accepted + rejected exceeds delivered.', 422);
            }
            if (bccomp($rejected, '0', 4) > 0 && empty($line['rejection_reason'])) {
                return $this->error('INVALID_INPUT', 'Line '.($i + 1).': a rejection reason is mandatory when quantity is rejected.', 422);
            }
            if ($line['expiry_date'] <= now()->toDateString()) {
                return $this->error('BATCH_EXPIRED', 'Line '.($i + 1).": expiry {$line['expiry_date']} is not in the future.", 422);
            }
            if (! empty($data['purchase_order_id']) && empty($line['purchase_order_line_id'])) {
                return $this->error('INVALID_INPUT', 'Line '.($i + 1).': every line of a PO-backed receipt must reference its PO line.', 422);
            }
        }

        $branchId = $this->branchId($request);
        $receipt = DB::transaction(function () use ($data, $branchId, $request, $receipts, $prices) {
            $receipt = GoodsReceipt::create([
                'doc_number' => $receipts->newDocNumber($branchId),
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $branchId,
                'store_id' => $data['store_id'],
                'status' => 'DRAFT',
                'is_emergency' => (bool) ($data['is_emergency'] ?? false),
                'received_by' => $request->user()->id,
            ]);

            foreach ($data['lines'] as $line) {
                $poLine = ! empty($line['purchase_order_line_id']) ? PurchaseOrderLine::find($line['purchase_order_line_id']) : null;
                GoodsReceiptLine::create([
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_line_id' => $poLine?->id,
                    'product_id' => $line['product_id'],
                    'uom_id' => $line['uom_id'],
                    'qty_ordered' => $poLine->qty_ordered ?? 0,
                    'qty_delivered' => $line['qty_delivered'],
                    'qty_accepted' => $line['qty_accepted'],
                    'qty_rejected' => $line['qty_rejected'] ?? 0,
                    'rejection_reason' => $line['rejection_reason'] ?? null,
                    'batch_number' => $line['batch_number'],
                    'expiry_date' => $line['expiry_date'],
                    'manufacture_date' => $line['manufacture_date'] ?? null,
                    'unit_cost' => $line['unit_cost'],
                    'trade_price' => $line['trade_price'],
                    'discount_pct' => $line['discount_pct'],
                    'temperature_on_arrival' => $line['temperature_on_arrival'] ?? null,
                    'coa_received' => (bool) ($line['coa_received'] ?? false),
                ]);
            }

            $posted = $receipts->post($receipt->fresh(['lines']));
            $this->applySellingPrices($request, $data['lines'], $posted, $prices);

            return $posted;
        });

        AuditLog::record('GRN_POSTED', 'goods_receipt', $receipt->id, ['reference' => $receipt->doc_number]);

        return response()->json($receipt->load('lines.batch'), 201);
    }

    /**
     * Writes the selling prices keyed on the receipt as fixed prices in the
     * received unit, one effective-dated row per price list, inside the
     * receipt's own transaction — the stock and its new price land together.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function applySellingPrices(Request $request, array $lines, GoodsReceipt $receipt, PriceListWriter $prices): void
    {
        $organisationId = $this->organisationId($request);

        foreach ($lines as $line) {
            foreach ($line['selling_prices'] ?? [] as $entry) {
                $list = PriceList::where('organisation_id', $organisationId)->where('is_active', true)->find($entry['price_list_id']);
                if (! $list) {
                    throw ValidationException::withMessages(['selling_prices' => 'A price list chosen for the new selling prices is no longer active.']);
                }

                $prices->write($list, [
                    'product_id' => $line['product_id'],
                    'uom_id' => $line['uom_id'],
                    'factor_type' => 'FIXED',
                    'unit_price' => (string) $entry['unit_price'],
                ], ['source' => 'goods_receipt', 'goods_receipt' => $receipt->doc_number, 'buying_cost' => (string) $line['unit_cost']]);
            }
        }
    }

    public function goodsReceipt(Request $request, string $receipt): JsonResponse
    {
        $this->requirePermission($request, 'grn.create');

        return response()->json(GoodsReceipt::where('branch_id', $this->branchId($request))->with(['lines.batch', 'purchaseOrder:id,doc_number,status'])->findOrFail($receipt));
    }

    /** POST /api/supplier-invoices — the invoice as the supplier sent it; matching is a separate step. */
    public function storeSupplierInvoice(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'invoice.match');

        $data = $request->validate([
            'supplier_id' => ['required', 'uuid', TenantRules::exists('suppliers')],
            'invoice_number' => ['required', 'string', 'max:100'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'uuid', TenantRules::exists('purchase_order_lines')],
            'lines.*.product_id' => ['required', 'uuid', TenantRules::exists('products')],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $branchId = $this->branchId($request);
        $invoice = DB::transaction(function () use ($data, $branchId, $request) {
            $subtotal = '0.0000';
            $invoice = SupplierInvoice::create([
                'doc_number' => NumberSequence::next($this->organisationId($request), 'SUPPLIER_INVOICE', $branchId, 'SINV'),
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $branchId,
                'invoice_number' => $data['invoice_number'],
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'tax_total' => (string) ($data['tax_total'] ?? '0'),
            ]);
            foreach ($data['lines'] as $line) {
                $lineTotal = bcmul((string) $line['qty'], (string) $line['unit_price'], 4);
                SupplierInvoiceLine::create(['supplier_invoice_id' => $invoice->id, 'line_total' => $lineTotal] + collect($line)->only(['purchase_order_line_id', 'product_id', 'qty', 'unit_price'])->all());
                $subtotal = bcadd($subtotal, $lineTotal, 4);
            }
            $invoice->update(['subtotal' => $subtotal, 'grand_total' => bcadd($subtotal, (string) ($data['tax_total'] ?? '0'), 4)]);

            return $invoice;
        });

        return response()->json($invoice->fresh('lines'), 201);
    }

    /**
     * GET /api/supplier-invoices/{id}/comparison — the PO, GRN and invoice
     * figures side by side, line by line, with the checks the match applies.
     */
    public function supplierInvoiceComparison(Request $request, string $invoice, ThreeWayMatchService $matcher): JsonResponse
    {
        $this->requirePermission($request, 'invoice.match');

        $invoice = SupplierInvoice::where('branch_id', $this->branchId($request))->with('supplier:id,code,name')->findOrFail($invoice);
        $lines = $matcher->compare($invoice);

        return response()->json([
            'invoice' => $invoice->only(['id', 'doc_number', 'invoice_number', 'invoice_date', 'due_date', 'match_status', 'matched_at', 'subtotal', 'tax_total', 'grand_total', 'supplier']),
            'tolerances' => $matcher->tolerances(),
            'lines' => $lines,
            'would_match' => collect($lines)->every(fn (array $line) => $line['failures'] === []),
        ]);
    }

    /** POST /api/supplier-invoices/{id}/match — Part 9.4. Only a match creates a payable. */
    public function matchSupplierInvoice(Request $request, string $invoice, ThreeWayMatchService $matcher): JsonResponse
    {
        $this->requirePermission($request, 'invoice.match');

        $invoice = SupplierInvoice::where('branch_id', $this->branchId($request))->with('lines')->findOrFail($invoice);
        $result = $matcher->match($invoice, $request->user()->id);

        return response()->json([
            'matched' => $result->matched,
            'failures' => $result->failures,
            'invoice' => $invoice->fresh('lines'),
        ]);
    }

    /** POST /api/supplier-payments — Dr AP / Cr Bank, never above what is owed. */
    public function storeSupplierPayment(Request $request, SupplierPaymentService $payments): JsonResponse
    {
        $this->requirePermission($request, 'payment.record');

        $data = $request->validate([
            'supplier_id' => ['required', 'uuid', TenantRules::exists('suppliers')],
            'method' => ['required', 'in:CASH,MPESA,BANK,CHEQUE'],
            'reference' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $payment = $payments->pay($data + [
            'organisation_id' => $this->organisationId($request),
            'branch_id' => $this->branchId($request),
            'amount' => (string) $data['amount'],
            'paid_by' => $request->user()->id,
        ]);

        return response()->json($payment->toArray() + ['payable_balance' => AccountsPayable::balanceFor($payment->supplier_id)], 201);
    }

    private function findPo(Request $request, string $id): PurchaseOrder
    {
        return PurchaseOrder::where('branch_id', $this->branchId($request))->findOrFail($id);
    }
}
