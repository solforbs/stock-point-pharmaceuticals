<?php

namespace App\Http\Controllers\Api;

use App\Models\Requisition;
use App\Services\Procurement\ReorderAdvisor;
use App\Services\Procurement\RequisitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Part 9.1 — requisition to purchase order, plus the reorder advisor. */
class RequisitionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'requisition.view');

        return response()->json(
            Requisition::where('branch_id', $this->branchId($request))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->withCount('lines')->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $requisition): JsonResponse
    {
        $this->requirePermission($request, 'requisition.view');

        return response()->json($this->find($request, $requisition)->load(['lines.product:id,code,name,base_uom_id', 'lines.product.baseUom:id,code']));
    }

    public function store(Request $request, RequisitionService $requisitions): JsonResponse
    {
        $this->requirePermission($request, 'requisition.create');

        $data = $request->validate([
            'needed_by' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'lines.*.qty_base' => ['required', 'numeric', 'gt:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($requisitions->create($data + ['branch_id' => $this->branchId($request), 'user_id' => $request->user()->id]), 201);
    }

    public function submit(Request $request, string $requisition, RequisitionService $requisitions): JsonResponse
    {
        $this->requirePermission($request, 'requisition.create');

        return response()->json($requisitions->submit($this->find($request, $requisition), $request->user()->id));
    }

    public function approve(Request $request, string $requisition, RequisitionService $requisitions): JsonResponse
    {
        $this->requirePermission($request, 'requisition.approve');

        return response()->json($requisitions->approve($this->find($request, $requisition), $request->user()->id));
    }

    public function reject(Request $request, string $requisition, RequisitionService $requisitions): JsonResponse
    {
        $this->requirePermission($request, 'requisition.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        return response()->json($requisitions->reject($this->find($request, $requisition), $request->user()->id, $data['reason']));
    }

    /** POST /api/requisitions/{id}/convert — supplier selection; the PO still needs its own approval. */
    public function convert(Request $request, string $requisition, RequisitionService $requisitions): JsonResponse
    {
        $this->requirePermission($request, 'po.create');

        $data = $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:suppliers,id'],
            'expected_date' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.requisition_line_id' => ['required', 'uuid'],
            'lines.*.uom_id' => ['required', 'uuid', 'exists:units_of_measure,id'],
            'lines.*.qty_ordered' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_code_id' => ['nullable', 'uuid', 'exists:tax_codes,id'],
        ]);

        return response()->json($requisitions->convertToPurchaseOrder($this->find($request, $requisition), $data + ['user_id' => $request->user()->id]), 201);
    }

    /** GET /api/procurement/reorder-suggestions — the preserved v5 arithmetic (Part 22.1). */
    public function reorderSuggestions(Request $request, ReorderAdvisor $advisor): JsonResponse
    {
        $this->requirePermission($request, 'requisition.view');

        $rows = $advisor->suggestions($this->organisationId($request), $this->branchId($request));

        return response()->json([
            'data' => $rows,
            'formula' => 'order_qty = max(round(reorder_point × 3 − free_to_sell − on_order), 10); required_by = today + lead_time_days + 2',
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    private function find(Request $request, string $id): Requisition
    {
        return Requisition::where('branch_id', $this->branchId($request))->findOrFail($id);
    }
}
