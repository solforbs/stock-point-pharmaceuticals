<?php

namespace App\Http\Controllers\Api;

use App\Models\Recall;
use App\Models\WasteDisposal;
use App\Services\Quality\RecallService;
use App\Services\Quality\WasteDisposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Part 8.4 recalls and Part 11.3 waste disposal. */
class QualityController extends ApiController
{
    // ---- Recalls -------------------------------------------------------

    public function recalls(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        return response()->json(
            Recall::where('organisation_id', $this->organisationId($request))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->withCount(['batches', 'customers'])
                ->orderByDesc('initiated_at')->paginate($request->integer('per_page', 25))
        );
    }

    /** GET /api/recalls/{id} — the traceability answer, live. */
    public function recall(Request $request, string $recall, RecallService $recalls): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        return response()->json($recalls->trace($this->findRecall($request, $recall)));
    }

    public function initiateRecall(Request $request, RecallService $recalls): JsonResponse
    {
        $this->requirePermission($request, 'recall.initiate');

        $data = $request->validate([
            'source' => ['required', 'in:MANUFACTURER,PPB,INTERNAL'],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'batch_ids' => ['required', 'array', 'min:1'],
            'batch_ids.*' => ['uuid', 'exists:product_batches,id'],
        ]);

        $recall = $recalls->initiate($data + ['organisation_id' => $this->organisationId($request), 'user_id' => $request->user()->id]);

        return response()->json($recalls->trace($recall), 201);
    }

    public function blockRecall(Request $request, string $recall, RecallService $recalls): JsonResponse
    {
        $this->requirePermission($request, 'recall.initiate');

        return response()->json($recalls->trace($recalls->block($this->findRecall($request, $recall), $request->user()->id)));
    }

    public function notifyRecall(Request $request, string $recall, RecallService $recalls): JsonResponse
    {
        $this->requirePermission($request, 'recall.initiate');
        $data = $request->validate(['notification_reference' => ['nullable', 'string', 'max:100']]);

        return response()->json($recalls->trace($recalls->notify($this->findRecall($request, $recall), $request->user()->id, $data['notification_reference'] ?? null)));
    }

    public function reconcileRecall(Request $request, string $recall, RecallService $recalls): JsonResponse
    {
        $this->requirePermission($request, 'recall.initiate');

        return response()->json($recalls->trace($recalls->reconcile($this->findRecall($request, $recall), $request->user()->id)));
    }

    public function dispositionRecall(Request $request, string $recall, RecallService $recalls): JsonResponse
    {
        $this->requirePermission($request, 'recall.initiate');
        $data = $request->validate(['disposition' => ['required', 'in:RETURN_TO_SUPPLIER,DESTROY']]);

        return response()->json($recalls->trace($recalls->disposition($this->findRecall($request, $recall), $request->user()->id, $data['disposition'])));
    }

    public function closeRecall(Request $request, string $recall, RecallService $recalls): JsonResponse
    {
        $this->requirePermission($request, 'recall.initiate');

        return response()->json($recalls->trace($recalls->close($this->findRecall($request, $recall), $request->user()->id)));
    }

    // ---- Waste and disposal --------------------------------------------

    public function wasteDisposals(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        return response()->json(
            WasteDisposal::where('branch_id', $this->branchId($request))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->input('reason'), fn ($q, $v) => $q->where('reason', $v))
                ->with('store:id,code,name')->withCount('lines')
                ->orderByDesc('created_at')->paginate($request->integer('per_page', 25))
        );
    }

    public function wasteDisposal(Request $request, string $disposal): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        return response()->json($this->findDisposal($request, $disposal)->load(['store:id,code,name', 'lines.product:id,code,name', 'lines.batch:id,batch_number,expiry_date,status']));
    }

    public function storeWasteDisposal(Request $request, WasteDisposalService $waste): JsonResponse
    {
        $this->requirePermission($request, 'stock.adjust');

        $data = $request->validate([
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'reason' => ['required', 'in:EXPIRED,DAMAGED,RECALLED,EXCURSION,CONTAMINATED'],
            'recall_id' => ['nullable', 'uuid', 'exists:recalls,id'],
            'disposal_method' => ['nullable', 'string', 'max:100'],
            'disposal_contractor' => ['nullable', 'string', 'max:150'],
            'certificate_reference' => ['nullable', 'string', 'max:100'],
            'ppb_reference' => ['nullable', 'string', 'max:100'],
            'witnessed_by_1' => ['nullable', 'integer', 'exists:users,id'],
            'witnessed_by_2' => ['nullable', 'integer', 'exists:users,id', 'different:witnessed_by_1'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'lines.*.batch_id' => ['required', 'uuid', 'exists:product_batches,id'],
            'lines.*.qty_base' => ['required', 'numeric', 'gt:0'],
        ]);

        return response()->json($waste->create($data + ['user_id' => $request->user()->id]), 201);
    }

    /** POST /api/waste-disposals/{id}/post — two witnesses, then the write-off posts. */
    public function postWasteDisposal(Request $request, string $disposal, WasteDisposalService $waste): JsonResponse
    {
        $this->requirePermission($request, 'waste.approve');

        $data = $request->validate([
            'disposal_method' => ['nullable', 'string', 'max:100'],
            'disposal_contractor' => ['nullable', 'string', 'max:150'],
            'certificate_reference' => ['nullable', 'string', 'max:100'],
            'ppb_reference' => ['nullable', 'string', 'max:100'],
            'witnessed_by_1' => ['nullable', 'integer', 'exists:users,id'],
            'witnessed_by_2' => ['nullable', 'integer', 'exists:users,id', 'different:witnessed_by_1'],
        ]);

        return response()->json($waste->post($this->findDisposal($request, $disposal), $request->user()->id, $data));
    }

    private function findRecall(Request $request, string $id): Recall
    {
        return Recall::where('organisation_id', $this->organisationId($request))->findOrFail($id);
    }

    private function findDisposal(Request $request, string $id): WasteDisposal
    {
        return WasteDisposal::where('branch_id', $this->branchId($request))->findOrFail($id);
    }
}
