<?php

namespace App\Http\Controllers\Api;

use App\Models\Sale;
use App\Services\Finance\ReceiptService;
use App\Services\Sales\CheckoutService;
use App\Services\Sales\VoidSaleService;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');

        $filters = $request->validate([
            'sale_mode' => ['nullable', 'in:RETAIL,WHOLESALE,DISPENSING'],
            'status' => ['nullable', 'in:DRAFT,POSTED,VOIDED'],
            'customer_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $sales = Sale::query()
            ->where('branch_id', $this->branchId($request))
            ->when($filters['sale_mode'] ?? null, fn ($q, $v) => $q->where('sale_mode', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['customer_id'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('posted_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('posted_at', '<=', $v))
            ->with('customer:id,code,name')
            ->orderByDesc('posted_at')
            ->paginate($filters['per_page'] ?? 25);

        // What each invoice still owes (Part 21.13): receipts and credit notes
        // already applied come off the grand total.
        $receipts = app(ReceiptService::class);
        $sales->getCollection()->transform(fn (Sale $sale) => $sale->toArray() + [
            'balance_due' => $sale->status === 'POSTED' && $sale->isCreditSale() ? $receipts->outstandingBalance($sale->id) : '0.0000',
        ]);

        return response()->json($sales);
    }

    public function show(Request $request, string $sale): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');

        $sale = Sale::where('branch_id', $this->branchId($request))
            ->with(['customer:id,code,name,customer_type', 'lines.product:id,code,name,generic_name,strength,description,base_uom_id', 'lines.product.baseUom:id,code', 'lines.uom:id,code', 'lines.batchAllocations.batch:id,batch_number,expiry_date'])
            ->findOrFail($sale);

        $payload = $sale->toArray();
        if (! $request->user()->can('product.cost.view')) {
            // Part 18.2 — cost and margin are gated fields, not hidden buttons.
            unset($payload['cost_total']);
            foreach ($payload['lines'] as &$line) {
                unset($line['unit_cost'], $line['line_cost']);
                foreach ($line['batch_allocations'] as &$alloc) {
                    unset($alloc['unit_cost']);
                }
            }
        }

        return response()->json($payload);
    }

    /**
     * POST /api/sales/checkout — Part 10.3. Atomic; bound to a quote_id;
     * idempotent on the Idempotency-Key header (DUPLICATE_REQUEST → 200).
     */
    public function checkout(Request $request, CheckoutService $checkout): JsonResponse
    {
        $this->requirePermission($request, 'sale.create');

        $data = $request->validate([
            'quote_id' => ['required', 'uuid'],
            'store_id' => ['required', 'uuid', TenantRules::exists('stores')],
            'terminal_id' => ['nullable', 'string', 'max:50'],
            'sub_type' => ['nullable', 'string', 'max:30'],
            'approve' => ['nullable', 'boolean'],
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required', 'in:CASH,MPESA,BANK,CARD,CHEQUE'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ]);

        $key = $this->idempotencyKey($request);
        if ($existing = Sale::where('idempotency_key', $key)->first()) {
            return response()->json($existing->load('lines.batchAllocations'))->header('X-Idempotent-Replay', 'true');
        }

        $approvedBy = null;
        if ($request->boolean('approve')) {
            $this->requirePermission($request, 'sale.discount.approve');
            $approvedBy = $request->user()->id;
        }

        $sale = $checkout->checkoutFromQuote($data['quote_id'], [
            'store_id' => $data['store_id'],
            'user_id' => $request->user()->id,
            'terminal_id' => $data['terminal_id'] ?? null,
            'sub_type' => $data['sub_type'] ?? null,
            'idempotency_key' => $key,
            'payments' => array_map(fn ($p) => ['method' => $p['method'], 'amount' => (string) $p['amount'], 'reference' => $p['reference'] ?? null], $data['payments'] ?? []),
            'approved_by' => $approvedBy,
        ]);

        return response()->json($sale, 201);
    }

    /**
     * POST /api/sales/{id}/void — Part 6.8. Reason mandatory; never a delete.
     */
    public function void(Request $request, string $sale, VoidSaleService $voids): JsonResponse
    {
        $this->requirePermission($request, 'sale.void');

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $sale = Sale::where('branch_id', $this->branchId($request))->findOrFail($sale);

        return response()->json($voids->void($sale, $data['reason'], $request->user()->id));
    }
}
