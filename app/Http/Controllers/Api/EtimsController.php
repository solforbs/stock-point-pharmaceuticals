<?php

namespace App\Http\Controllers\Api;

use App\Models\CustomerReturn;
use App\Models\Sale;
use App\Services\Tax\EtimsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Part 13.6 — the eTIMS queue screen: pending / transmitted / failed, with manual retry. */
class EtimsController extends ApiController
{
    public function queue(Request $request, EtimsService $etims): JsonResponse
    {
        $this->requirePermission($request, 'tax.etims.manage');
        $branchId = $this->branchId($request);

        $filters = $request->validate(['status' => ['nullable', 'in:PENDING,SUBMITTED,FAILED,NOT_CONFIGURED'], 'per_page' => ['nullable', 'integer', 'between:1,200']]);
        $status = $filters['status'] ?? 'FAILED';

        $sales = Sale::where('branch_id', $branchId)->where('etims_status', $status)
            ->with('customer:id,code,name')
            ->orderByDesc('posted_at')->limit($filters['per_page'] ?? 50)
            ->get(['id', 'doc_number', 'sale_mode', 'customer_id', 'grand_total', 'posted_at', 'etims_status', 'etims_control_code', 'etims_invoice_number', 'etims_submitted_at', 'etims_error'])
            ->map(fn (Sale $s) => ['type' => 'sale'] + $s->toArray());
        $notes = CustomerReturn::where('branch_id', $branchId)->where('etims_status', $status)
            ->with('customer:id,code,name')
            ->orderByDesc('posted_at')->limit($filters['per_page'] ?? 50)
            ->get(['id', 'doc_number', 'credit_note_number', 'customer_id', 'grand_total', 'posted_at', 'etims_status', 'etims_control_code', 'etims_invoice_number', 'etims_submitted_at', 'etims_error'])
            ->map(fn (CustomerReturn $r) => ['type' => 'credit_note'] + $r->toArray());

        return response()->json([
            'summary' => $etims->queueSummary($branchId),
            'status' => $status,
            'data' => $sales->concat($notes)->sortByDesc('posted_at')->values(),
        ]);
    }

    public function retrySale(Request $request, string $sale, EtimsService $etims): JsonResponse
    {
        $this->requirePermission($request, 'tax.etims.manage');
        $sale = Sale::where('branch_id', $this->branchId($request))->findOrFail($sale);
        if ($sale->status !== 'POSTED') {
            return $this->error('INVALID_STATE', "Sale {$sale->doc_number} is {$sale->status}; only posted sales are fiscalised.", 409);
        }
        if (! config('etims.enabled')) {
            return $this->error('ETIMS_NOT_CONFIGURED', 'eTIMS transmission is not enabled on this server.', 422);
        }

        try {
            return response()->json($etims->submitSale($sale));
        } catch (\Throwable $e) {
            return $this->error('ETIMS_TRANSMISSION_FAILED', $e->getMessage(), 502, ['sale' => $sale->fresh()->only(['id', 'doc_number', 'etims_status', 'etims_error'])]);
        }
    }

    public function retryCreditNote(Request $request, string $return, EtimsService $etims): JsonResponse
    {
        $this->requirePermission($request, 'tax.etims.manage');
        $return = CustomerReturn::where('branch_id', $this->branchId($request))->findOrFail($return);
        if ($return->status !== 'POSTED') {
            return $this->error('INVALID_STATE', "Return {$return->doc_number} is {$return->status}.", 409);
        }
        if (! config('etims.enabled')) {
            return $this->error('ETIMS_NOT_CONFIGURED', 'eTIMS transmission is not enabled on this server.', 422);
        }

        try {
            return response()->json($etims->submitCreditNote($return));
        } catch (\Throwable $e) {
            return $this->error('ETIMS_TRANSMISSION_FAILED', $e->getMessage(), 502);
        }
    }
}
