<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\PickingList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Part 10.6 — packing, between pick and dispatch. A completed picking list
 * is packed into a number of packages (with an optional weight and notes)
 * before the delivery note goes out. Packing is recorded, not enforced:
 * dispatch does not require it.
 */
class PackingController extends ApiController
{
    /** GET /api/packing/queue?packed=yes|no — completed picks not yet dispatched. */
    public function queue(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'warehouse.pick');

        $lists = PickingList::query()
            ->where('branch_id', $this->branchId($request))
            ->where('status', 'COMPLETED')
            ->whereDoesntHave('deliveryNotes', fn ($q) => $q->whereIn('status', ['DISPATCHED', 'DELIVERED']))
            ->when($request->input('packed') === 'yes', fn ($q) => $q->whereNotNull('packed_at'))
            ->when($request->input('packed') === 'no', fn ($q) => $q->whereNull('packed_at'))
            ->with(['salesOrder:id,doc_number,customer_id,status', 'salesOrder.customer:id,code,name', 'packer:id,name'])
            ->withCount('lines')
            ->orderByRaw('packed_at IS NOT NULL')->orderBy('completed_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($lists);
    }

    /** POST /api/picking-lists/{list}/pack — record (or correct) the packing of a completed pick. */
    public function pack(Request $request, string $list): JsonResponse
    {
        $this->requirePermission($request, 'warehouse.pick');

        $data = $request->validate([
            'package_count' => ['required', 'integer', 'min:1', 'max:65535'],
            'total_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'packing_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $list = PickingList::where('branch_id', $this->branchId($request))->findOrFail($list);
        if ($list->status !== 'COMPLETED') {
            return $this->error('INVALID_STATE', "Picking list {$list->doc_number} is {$list->status}; only a completed pick can be packed.", 422, ['status' => $list->status]);
        }
        if ($list->deliveryNotes()->whereIn('status', ['DISPATCHED', 'DELIVERED'])->exists()) {
            return $this->error('ALREADY_DISPATCHED', "Picking list {$list->doc_number} has already been dispatched; its packing can no longer change.", 422);
        }

        $before = $list->only(['packed_at', 'packed_by', 'package_count', 'total_weight_kg', 'packing_notes']);
        $isCorrection = $list->packed_at !== null;

        $list->update([
            'packed_at' => now(),
            'packed_by' => $request->user()->id,
            'package_count' => $data['package_count'],
            'total_weight_kg' => isset($data['total_weight_kg']) ? (string) $data['total_weight_kg'] : null,
            'packing_notes' => $data['packing_notes'] ?? null,
        ]);

        AuditLog::record($isCorrection ? 'PACKING_CORRECTED' : 'PICKING_LIST_PACKED', 'picking_list', (string) $list->id, [
            'reference' => $list->doc_number,
            'before_json' => $isCorrection ? $before : null,
            'after_json' => $list->only(['packed_at', 'packed_by', 'package_count', 'total_weight_kg', 'packing_notes']),
        ]);

        return response()->json($list->fresh(['salesOrder:id,doc_number,customer_id,status', 'salesOrder.customer:id,code,name', 'packer:id,name'])?->loadCount('lines'));
    }
}
