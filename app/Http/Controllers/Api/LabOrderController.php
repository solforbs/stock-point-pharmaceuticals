<?php

namespace App\Http\Controllers\Api;

use App\Models\LabOrder;
use App\Services\Laboratory\LabOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The laboratory bench: the order queue and the guarded status workflow.
 * Every transition needs its own permission and is audited with who and
 * when — no status ever changes "by hand".
 */
class LabOrderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'laboratory.view');

        return response()->json(
            LabOrder::query()
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->input('facility_id'), fn ($q, $v) => $q->where('facility_id', $v))
                ->with(['patient:id,patient_no,first_name,last_name,sex,date_of_birth', 'facility:id,name', 'orderedBy:id,name'])
                ->withCount('tests')
                ->orderByDesc('created_at')->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $order): JsonResponse
    {
        $this->requireAnyPermission($request, ['laboratory.view', 'laboratory.order.view']);

        return response()->json($this->find($order)->load([
            'patient:id,patient_no,first_name,last_name,sex,date_of_birth',
            'encounter:id,encounter_no,status', 'facility:id,name',
            'orderedBy:id,name', 'tests.resultEnteredBy:id,name', 'samples.collectedBy:id,name',
        ]));
    }

    public function accept(Request $request, string $order, LabOrderService $orders): JsonResponse
    {
        $this->requirePermission($request, 'laboratory.order.accept');

        return response()->json($orders->accept($this->find($order), $request->user()));
    }

    public function collectSample(Request $request, string $order, LabOrderService $orders): JsonResponse
    {
        $this->requirePermission($request, 'laboratory.sample.collect');

        $data = $request->validate([
            'sample_type' => ['required', 'string', 'max:50'],
            'condition_notes' => ['nullable', 'string', 'max:300'],
        ]);

        return response()->json($orders->collectSample($this->find($order), $data, $request->user()), 201);
    }

    public function startProcessing(Request $request, string $order, LabOrderService $orders): JsonResponse
    {
        $this->requirePermission($request, 'laboratory.result.enter');

        return response()->json($orders->startProcessing($this->find($order), $request->user()));
    }

    public function enterResults(Request $request, string $order, LabOrderService $orders): JsonResponse
    {
        $this->requirePermission($request, 'laboratory.result.enter');

        $data = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*.lab_order_test_id' => ['required', 'uuid'],
            'results.*.result_value' => ['required', 'string', 'max:300'],
            'results.*.result_notes' => ['nullable', 'string'],
            'results.*.is_abnormal' => ['nullable', 'boolean'],
        ]);

        return response()->json($orders->enterResults($this->find($order), $data['results'], $request->user()));
    }

    public function cancel(Request $request, string $order, LabOrderService $orders): JsonResponse
    {
        $this->requirePermission($request, 'laboratory.order.cancel');

        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);

        return response()->json($orders->cancel($this->find($order), $request->user(), $data['reason']));
    }

    private function find(string $order): LabOrder
    {
        return LabOrder::findOrFail($order);
    }
}
