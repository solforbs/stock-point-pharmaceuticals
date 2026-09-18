<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Part 12.5 — receipts reconciliation. Every customer receipt recorded in
 * the branch is matched against the bank or M-PESA statement line it
 * appeared on; unmatched receipts are the exceptions to chase.
 */
class ReconciliationController extends ApiController
{
    private const METHODS = ['CASH', 'MPESA', 'BANK', 'CARD', 'CHEQUE'];

    /** GET /api/finance/reconciliation?method=&from=&to=&status=reconciled|unreconciled */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'finance.ar.view');

        $request->validate([
            'method' => ['nullable', 'in:'.implode(',', self::METHODS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'in:reconciled,unreconciled'],
        ]);

        $from = $request->input('from') ? Carbon::parse((string) $request->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->input('to') ? Carbon::parse((string) $request->input('to'))->endOfDay() : now()->endOfDay();

        $window = fn (): Builder => Payment::query()
            ->where('branch_id', $this->branchId($request))
            ->whereBetween('received_at', [$from, $to])
            ->when($request->input('method'), fn ($q, $v) => $q->where('method', $v));

        $payments = $window()
            ->when($request->input('status') === 'reconciled', fn ($q) => $q->whereNotNull('reconciled_at'))
            ->when($request->input('status') === 'unreconciled', fn ($q) => $q->whereNull('reconciled_at')->where('status', 'CLEARED'))
            ->with(['customer:id,code,name', 'receiver:id,name', 'reconciler:id,name'])
            ->orderByDesc('received_at')
            ->paginate($request->integer('per_page', 50));

        $totals = [];
        $rows = $window()->where('status', 'CLEARED')->groupBy('method')
            ->selectRaw('method, COUNT(*) as n, SUM(amount) as amount, SUM(CASE WHEN reconciled_at IS NOT NULL THEN amount ELSE 0 END) as reconciled, SUM(CASE WHEN reconciled_at IS NULL THEN 1 ELSE 0 END) as unreconciled_count')
            ->toBase()->get();
        foreach ($rows as $r) {
            $amount = bcadd((string) $r->amount, '0', 4);
            $reconciled = bcadd((string) $r->reconciled, '0', 4);
            $totals[] = [
                'method' => $r->method, 'count' => (int) $r->n, 'unreconciled_count' => (int) $r->unreconciled_count,
                'amount' => $amount, 'reconciled' => $reconciled, 'unreconciled' => bcsub($amount, $reconciled, 4),
            ];
        }

        return response()->json(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'totals' => $totals] + $payments->toArray());
    }

    /** POST /api/finance/reconciliation/reconcile {payment_ids, reconciliation_ref, statement_date, statement_amounts?} */
    public function reconcile(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'payment.reconcile');

        $data = $request->validate([
            'payment_ids' => ['required', 'array', 'min:1', 'max:500'],
            'payment_ids.*' => ['required', 'uuid', 'distinct'],
            'reconciliation_ref' => ['required', 'string', 'min:2', 'max:100'],
            'statement_date' => ['required', 'date', 'before_or_equal:today'],
            'statement_amounts' => ['nullable', 'array'],
            'statement_amounts.*' => ['numeric', 'gt:0'],
        ]);

        $payments = Payment::where('branch_id', $this->branchId($request))->whereIn('id', $data['payment_ids'])->get();
        if ($payments->count() !== count($data['payment_ids'])) {
            return $this->error('NOT_FOUND', 'One or more payments were not found in this branch.', 404, ['missing' => array_values(array_diff($data['payment_ids'], $payments->pluck('id')->all()))]);
        }
        $reversed = $payments->where('status', 'REVERSED')->pluck('id')->values()->all();
        if ($reversed) {
            return $this->error('PAYMENT_REVERSED', 'A reversed payment cannot be reconciled.', 422, ['payment_ids' => $reversed]);
        }
        $already = $payments->whereNotNull('reconciled_at')->pluck('id')->values()->all();
        if ($already) {
            return $this->error('ALREADY_RECONCILED', 'One or more payments are already reconciled; unreconcile them first to change the match.', 422, ['payment_ids' => $already]);
        }

        $statementAmounts = $data['statement_amounts'] ?? [];
        DB::transaction(function () use ($payments, $data, $statementAmounts, $request) {
            foreach ($payments as $payment) {
                $payment->update([
                    'reconciled_at' => now(),
                    'reconciled_by' => $request->user()->id,
                    'reconciliation_ref' => $data['reconciliation_ref'],
                    'statement_date' => $data['statement_date'],
                    'statement_amount' => isset($statementAmounts[$payment->id]) ? (string) $statementAmounts[$payment->id] : (string) $payment->amount,
                ]);
                AuditLog::record('PAYMENT_RECONCILED', 'payment', (string) $payment->id, [
                    'reference' => $data['reconciliation_ref'],
                    'after_json' => $payment->only(['method', 'reference', 'amount', 'reconciliation_ref', 'statement_date', 'statement_amount']),
                ]);
            }
        });

        return response()->json(['reconciled' => $payments->count(), 'data' => $payments->map->fresh(['customer:id,code,name', 'receiver:id,name', 'reconciler:id,name'])->values()]);
    }

    /** POST /api/finance/reconciliation/{payment}/unreconcile {reason} */
    public function unreconcile(Request $request, string $payment): JsonResponse
    {
        $this->requirePermission($request, 'payment.reconcile');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        $payment = Payment::where('branch_id', $this->branchId($request))->findOrFail($payment);
        if ($payment->reconciled_at === null) {
            return $this->error('NOT_RECONCILED', 'This payment is not reconciled.', 422);
        }

        $before = $payment->only(['reconciled_at', 'reconciled_by', 'reconciliation_ref', 'statement_date', 'statement_amount']);
        $payment->update(['reconciled_at' => null, 'reconciled_by' => null, 'reconciliation_ref' => null, 'statement_date' => null, 'statement_amount' => null]);

        AuditLog::record('PAYMENT_UNRECONCILED', 'payment', (string) $payment->id, ['reference' => $before['reconciliation_ref'], 'before_json' => $before, 'reason' => $data['reason']]);

        return response()->json($payment->fresh(['customer:id,code,name', 'receiver:id,name', 'reconciler:id,name']));
    }
}
