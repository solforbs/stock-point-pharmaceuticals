<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\DeliveryNote;
use App\Models\PickingList;
use App\Models\PickingListLine;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Services\Pricing\PriceQuoteService;
use App\Services\Sales\DispatchService;
use App\Services\Sales\PickingService;
use App\Services\Sales\QuotationService;
use App\Services\Sales\SaleModes;
use App\Services\Sales\SalesOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Part 10.2 / V6 21.7 — the wholesale document chain. Every price on a
 * quotation or order comes from the seven-step quote; the client never
 * sends a price.
 */
class OrderController extends ApiController
{
    public function storeQuotation(Request $request, QuotationService $quotations): JsonResponse
    {
        $this->requirePermission($request, 'sale.create');
        if ($refused = $this->requireWholesaleBranch($request)) {
            return $refused;
        }

        $data = $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'valid_until' => ['required', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'header_discount' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'lines.*.uom_id' => ['required', 'uuid', 'exists:units_of_measure,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.requested_discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.requested_discount_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $quotation = $quotations->create($data + [
            'organisation_id' => $this->organisationId($request),
            'branch_id' => $this->branchId($request),
            'sale_mode' => 'WHOLESALE',
            'user_id' => $request->user()->id,
        ]);

        return response()->json($quotation, 201);
    }

    public function quotation(Request $request, string $quotation): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');

        return response()->json(Quotation::where('branch_id', $this->branchId($request))->with(['lines.product:id,code,name', 'customer:id,code,name'])->findOrFail($quotation));
    }

    /** POST /api/quotations/{id}/accept — creates the order AND reserves stock (credit check inside). */
    public function acceptQuotation(Request $request, string $quotation, QuotationService $quotations, SalesOrderService $orders): JsonResponse
    {
        $this->requirePermission($request, 'sale.create');
        $key = $this->idempotencyKey($request);
        $quotation = Quotation::where('branch_id', $this->branchId($request))->findOrFail($quotation);

        if ($existing = SalesOrder::where('idempotency_key', $key)->first()) {
            return response()->json($existing->load('lines'))->header('X-Idempotent-Replay', 'true');
        }

        $terms = $request->validate([
            'payment_terms' => ['nullable', 'in:ACCOUNT,CASH_ON_DELIVERY'],
            'credit_override_reason' => ['nullable', 'string', 'min:5', 'max:255'],
        ]);
        $override = $this->creditOverrideReason($request, $terms['credit_override_reason'] ?? null);

        // One unit of work: if the credit check or reservation fails, no
        // DRAFT order is left behind under this idempotency key.
        $order = DB::transaction(function () use ($quotations, $orders, $quotation, $request, $key, $terms, $override) {
            $order = $quotations->convertToSalesOrder($quotation, [
                'user_id' => $request->user()->id,
                'idempotency_key' => $key,
                'required_date' => $request->input('required_date'),
                'payment_terms' => $terms['payment_terms'] ?? null,
            ]);

            return $orders->confirm($order, $request->user()->id, $override);
        });

        return response()->json($order, 201);
    }

    public function storeSalesOrder(Request $request, PriceQuoteService $quotes, SalesOrderService $orders): JsonResponse
    {
        $this->requirePermission($request, 'sale.create');
        if ($refused = $this->requireWholesaleBranch($request)) {
            return $refused;
        }
        $key = $this->idempotencyKey($request);

        $data = $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'required_date' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'in:ACCOUNT,CASH_ON_DELIVERY'],
            'header_discount' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'lines.*.uom_id' => ['required', 'uuid', 'exists:units_of_measure,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.requested_discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.requested_discount_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($existing = SalesOrder::where('idempotency_key', $key)->first()) {
            return response()->json($existing->load('lines'))->header('X-Idempotent-Replay', 'true');
        }

        $organisationId = $this->organisationId($request);
        $branchId = $this->branchId($request);

        $quote = $quotes->quote([
            'organisation_id' => $organisationId, 'branch_id' => $branchId, 'store_id' => $data['store_id'],
            'sale_mode' => 'WHOLESALE', 'customer_id' => $data['customer_id'], 'user_id' => $request->user()->id,
            'header_discount' => $data['header_discount'] ?? null, 'lines' => $data['lines'],
        ]);

        $order = $orders->create([
            'organisation_id' => $organisationId, 'branch_id' => $branchId, 'store_id' => $data['store_id'],
            'sale_mode' => 'WHOLESALE', 'customer_id' => $data['customer_id'], 'user_id' => $request->user()->id,
            'required_date' => $data['required_date'] ?? null, 'idempotency_key' => $key,
            'payment_terms' => $data['payment_terms'] ?? null,
            'lines' => array_map(fn ($l) => [
                'product_id' => $l['product_id'], 'uom_id' => $l['uom_id'], 'qty' => $l['quantity'],
                'list_price' => $l['break_price'], 'unit_price' => $l['unit_price'],
                'discount_amount' => $l['discount_amount'], 'discount_pct' => $l['discount_pct'],
                'discount_source' => $l['discount_source'] === 'NONE' ? $l['price_source'] : $l['discount_source'],
                'tax_code_id' => $l['tax_code_id'], 'tax_rate' => $l['tax_rate'],
            ], $quote['lines']),
        ]);

        return response()->json($order->toArray() + ['quote_id' => $quote['quote_id'], 'approval_required' => $quote['approval_required']], 201);
    }

    public function salesOrders(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');

        return response()->json(
            SalesOrder::where('branch_id', $this->branchId($request))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->input('customer_id'), fn ($q, $v) => $q->where('customer_id', $v))
                ->with('customer:id,code,name')->withCount('lines')
                ->orderByDesc('created_at')->paginate($request->integer('per_page', 25))
        );
    }

    public function salesOrder(Request $request, string $order): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');

        return response()->json($this->findOrder($request, $order)->load(['lines.product:id,code,name', 'customer:id,code,name']));
    }

    public function confirmSalesOrder(Request $request, string $order, SalesOrderService $orders): JsonResponse
    {
        $this->requirePermission($request, 'sale.create');
        $data = $request->validate([
            'payment_terms' => ['nullable', 'in:ACCOUNT,CASH_ON_DELIVERY'],
            'credit_override_reason' => ['nullable', 'string', 'min:5', 'max:255'],
        ]);
        $order = $this->findOrder($request, $order);
        if (! empty($data['payment_terms']) && $order->status === 'DRAFT') {
            $order->update(['payment_terms' => $data['payment_terms']]);
        }

        return response()->json($orders->confirm($order, $request->user()->id, $this->creditOverrideReason($request, $data['credit_override_reason'] ?? null)));
    }

    /** Part 10.4 — only a holder of customer.credit.override may take an order past the limit. */
    private function creditOverrideReason(Request $request, ?string $reason): ?string
    {
        if ($reason === null || trim($reason) === '') {
            return null;
        }
        $this->requirePermission($request, 'customer.credit.override');

        return $reason;
    }

    public function cancelSalesOrder(Request $request, string $order, SalesOrderService $orders): JsonResponse
    {
        $this->requirePermission($request, 'sale.create');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        return response()->json($orders->cancel($this->findOrder($request, $order), $data['reason'], $request->user()->id));
    }

    /** POST /api/sales-orders/{id}/pick — generates the location-sorted pick list. */
    public function pick(Request $request, string $order, PickingService $picking): JsonResponse
    {
        $this->requirePermission($request, 'warehouse.pick');

        $list = $picking->generate($this->findOrder($request, $order));
        $picking->start($list, $request->user()->id);

        return response()->json($list->fresh('lines.batch:id,batch_number,expiry_date'), 201);
    }

    public function pickLine(Request $request, string $list, string $line, PickingService $picking): JsonResponse
    {
        $this->requirePermission($request, 'warehouse.pick');
        $data = $request->validate(['qty_picked_base' => ['required', 'numeric', 'min:0']]);

        $pickingList = PickingList::where('branch_id', $this->branchId($request))->findOrFail($list);
        $pickLine = PickingListLine::where('picking_list_id', $pickingList->id)->findOrFail($line);

        return response()->json($picking->completeLine($pickLine, (string) $data['qty_picked_base']));
    }

    public function completePicking(Request $request, string $list, PickingService $picking): JsonResponse
    {
        $this->requirePermission($request, 'warehouse.pick');

        return response()->json($picking->complete(PickingList::where('branch_id', $this->branchId($request))->findOrFail($list)));
    }

    /** POST /api/sales-orders/{id}/dispatch — posts stock OUT, revenue and AR (Part 10.2). */
    public function dispatch(Request $request, string $order, DispatchService $dispatch): JsonResponse
    {
        $this->requirePermission($request, 'warehouse.dispatch');
        $key = $this->idempotencyKey($request);

        $data = $request->validate([
            'vehicle_reg' => ['nullable', 'string', 'max:20'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'driver_phone' => ['nullable', 'string', 'max:30'],
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required', 'in:CASH,MPESA,BANK,CARD,CHEQUE'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ]);

        $order = $this->findOrder($request, $order);
        $pickingList = PickingList::where('sales_order_id', $order->id)->where('status', 'COMPLETED')
            ->whereDoesntHave('deliveryNotes')->orderByDesc('completed_at')->first();
        if (! $pickingList) {
            return $this->error('INVALID_STATE', "Sales order {$order->doc_number} has no completed pick list awaiting dispatch.", 409);
        }

        $note = $dispatch->createFromPickingList($pickingList, ['idempotency_key' => $key]);
        if ($note->status === 'DRAFT') {
            $note = $dispatch->dispatch($note, [
                'user_id' => $request->user()->id,
                'vehicle_reg' => $data['vehicle_reg'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
                'driver_phone' => $data['driver_phone'] ?? null,
                'payments' => array_map(fn ($p) => ['method' => $p['method'], 'amount' => (string) $p['amount'], 'reference' => $p['reference'] ?? null], $data['payments'] ?? []),
            ]);
        }

        return response()->json($note->load('lines.batchAllocations'), 201);
    }

    /** POST /api/delivery-notes/{id}/pod — proof of delivery; touches nothing financial. */
    public function proofOfDelivery(Request $request, string $note, DispatchService $dispatch): JsonResponse
    {
        $this->requirePermission($request, 'warehouse.dispatch');
        $data = $request->validate(['received_by_name' => ['required', 'string', 'max:150']]);

        $note = DeliveryNote::where('branch_id', $this->branchId($request))->findOrFail($note);

        return response()->json($dispatch->confirmDelivery($note, $data['received_by_name']));
    }

    private function findOrder(Request $request, string $id): SalesOrder
    {
        return SalesOrder::where('branch_id', $this->branchId($request))->findOrFail($id);
    }

    /** The wholesale document chain only exists in branches that trade wholesale (V6 Part 10.1). */
    private function requireWholesaleBranch(Request $request): ?JsonResponse
    {
        $branch = Branch::findOrFail($this->branchId($request));
        if (! in_array(SaleModes::WHOLESALE, SaleModes::enabledFor($branch), true)) {
            return $this->error('MODE_DISABLED', "Branch {$branch->code} does not trade in WHOLESALE mode.", 422, ['enabled_modes' => SaleModes::enabledFor($branch)]);
        }

        return null;
    }
}
