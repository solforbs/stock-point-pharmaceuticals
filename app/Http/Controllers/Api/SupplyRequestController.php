<?php

namespace App\Http\Controllers\Api;

use App\Models\SupplyRequest;
use App\Services\Finance\SupplyRequestService;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Non-pharmaceutical supplies: request, approve, then buy and expense. */
class SupplyRequestController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'requisition.view');

        return response()->json(
            SupplyRequest::where('branch_id', $this->branchId($request))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->with('requester:id,name')->withCount('lines')
                ->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $supplyRequest): JsonResponse
    {
        $this->requirePermission($request, 'requisition.view');

        return response()->json($this->find($request, $supplyRequest)->load(['lines', 'requester:id,name', 'supplier:id,code,name']));
    }

    public function store(Request $request, SupplyRequestService $supplies): JsonResponse
    {
        $this->requirePermission($request, 'requisition.create');

        $data = $request->validate([
            'needed_by' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.item' => ['required', 'string', 'max:150'],
            'lines.*.category' => ['nullable', 'in:'.implode(',', SupplyRequestService::CATEGORIES)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['nullable', 'string', 'max:30'],
            'lines.*.est_unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json($supplies->create($this->organisationId($request), $this->branchId($request), $request->user()->id, $data), 201);
    }

    public function submit(Request $request, string $supplyRequest, SupplyRequestService $supplies): JsonResponse
    {
        $this->requirePermission($request, 'requisition.create');

        return response()->json($supplies->submit($this->find($request, $supplyRequest)));
    }

    public function approve(Request $request, string $supplyRequest, SupplyRequestService $supplies): JsonResponse
    {
        $this->requirePermission($request, 'requisition.approve');

        return response()->json($supplies->approve($this->find($request, $supplyRequest), $request->user()->id));
    }

    public function reject(Request $request, string $supplyRequest, SupplyRequestService $supplies): JsonResponse
    {
        $this->requirePermission($request, 'requisition.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        return response()->json($supplies->reject($this->find($request, $supplyRequest), $request->user()->id, $data['reason']));
    }

    public function purchase(Request $request, string $supplyRequest, SupplyRequestService $supplies): JsonResponse
    {
        $this->requirePermission($request, 'po.create');

        $data = $request->validate([
            'paid_from' => ['required', 'in:'.implode(',', SupplyRequestService::PAID_FROM)],
            'supplier_id' => ['nullable', 'uuid', TenantRules::exists('suppliers')],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*' => ['numeric', 'min:0'],
        ]);

        return response()->json($supplies->purchase($this->find($request, $supplyRequest), $request->user()->id, $data));
    }

    private function find(Request $request, string $id): SupplyRequest
    {
        return SupplyRequest::where('branch_id', $this->branchId($request))->findOrFail($id);
    }
}
