<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\Payment;
use App\Services\Finance\ReceiptService;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends ApiController
{
    /** POST /api/payments — a customer receipt against AR (Part 21.10). */
    public function store(Request $request, ReceiptService $receipts): JsonResponse
    {
        $this->requirePermission($request, 'payment.record');

        $data = $request->validate([
            'customer_id' => ['required', 'uuid', TenantRules::exists('customers')],
            'method' => ['required', 'in:CASH,MPESA,BANK,CARD,CHEQUE'],
            'reference' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.sale_id' => ['required', 'uuid', TenantRules::exists('sales')],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        if ($data['method'] === 'MPESA' && empty($data['reference'])) {
            return $this->error('REFERENCE_REQUIRED', 'An M-PESA receipt must carry the transaction reference (Part 6.7).', 422);
        }
        if (! empty($data['reference']) && Payment::where('customer_id', $data['customer_id'])->where('method', $data['method'])->where('reference', $data['reference'])->where('status', 'CLEARED')->exists()) {
            $existing = Payment::where('customer_id', $data['customer_id'])->where('method', $data['method'])->where('reference', $data['reference'])->first();

            return response()->json($existing->load('allocations'))->header('X-Idempotent-Replay', 'true');
        }

        $payment = $receipts->record([
            'organisation_id' => $this->organisationId($request),
            'branch_id' => $this->branchId($request),
            'customer_id' => $data['customer_id'],
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'amount' => (string) $data['amount'],
            'received_by' => $request->user()->id,
            'allocations' => isset($data['allocations']) ? array_map(fn ($a) => ['sale_id' => $a['sale_id'], 'amount' => (string) $a['amount']], $data['allocations']) : null,
        ]);

        return response()->json($payment, 201);
    }

    public function void(Request $request, string $payment, ReceiptService $receipts): JsonResponse
    {
        $this->requirePermission($request, 'payment.record');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        $payment = Payment::where('branch_id', $this->branchId($request))->findOrFail($payment);

        return response()->json($receipts->void($payment, $data['reason'], $request->user()->id));
    }

    /** GET /api/finance/ar-ageing — current / 1-30 / 31-60 / 61-90 / 90+ per customer. */
    public function arAgeing(Request $request, ReceiptService $receipts): JsonResponse
    {
        $this->requirePermission($request, 'finance.ar.view');

        $customers = Customer::where('organisation_id', $this->organisationId($request))
            ->when($request->input('customer_id'), fn ($q, $v) => $q->where('id', $v))
            ->with('credit')
            ->get();

        $rows = [];
        $totals = ['current' => '0.0000', 'd1_30' => '0.0000', 'd31_60' => '0.0000', 'd61_90' => '0.0000', 'd90_plus' => '0.0000', 'total' => '0.0000'];
        foreach ($customers as $customer) {
            $aging = $receipts->agingReport($customer->id);
            if (bccomp($aging['total'], '0', 4) <= 0 && ! $request->input('customer_id')) {
                continue;
            }
            $credit = $customer->credit ?? new CustomerCredit(['credit_limit' => '0', 'current_balance' => '0']);
            $rows[] = [
                'customer_id' => $customer->id, 'code' => $customer->code, 'name' => $customer->name, 'customer_type' => $customer->customer_type,
                'payment_terms_days' => $customer->payment_terms_days,
                'credit_limit' => (string) $credit->credit_limit,
                'exposure' => $customer->credit ? $credit->exposure() : '0.0000',
                'on_hold' => (bool) $credit->on_hold,
            ] + $aging;
            foreach ($totals as $k => $v) {
                $totals[$k] = bcadd($v, $aging[$k], 4);
            }
        }

        return response()->json(['data' => $rows, 'totals' => $totals, 'as_of' => now()->toDateString()]);
    }
}
