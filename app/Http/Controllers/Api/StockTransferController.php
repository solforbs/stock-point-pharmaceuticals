<?php

namespace App\Http\Controllers\Api;

use App\Models\StockTransfer;
use App\Models\Store;
use App\Services\Inventory\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Part 7.6 / 21.8 — transfers with IN_TRANSIT. */
class StockTransferController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');
        $storeIds = Store::where('branch_id', $this->branchId($request))->pluck('id');

        return response()->json(
            StockTransfer::query()
                ->where(fn ($q) => $q->whereIn('from_store_id', $storeIds)->orWhereIn('to_store_id', $storeIds))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->with(['fromStore:id,code,name', 'toStore:id,code,name'])->withCount('lines')
                ->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $transfer): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        return response()->json($this->find($request, $transfer)->load(['fromStore:id,code,name', 'toStore:id,code,name', 'lines.batch:id,batch_number,expiry_date,status', 'lines.product:id,code,name']));
    }

    public function store(Request $request, StockTransferService $transfers): JsonResponse
    {
        $this->requirePermission($request, 'stock.transfer.create');

        $data = $request->validate([
            'from_store_id' => ['required', 'uuid', 'exists:stores,id', 'different:to_store_id'],
            'to_store_id' => ['required', 'uuid', 'exists:stores,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'lines.*.batch_id' => ['required', 'uuid', 'exists:product_batches,id'],
            'lines.*.qty_base' => ['required', 'numeric', 'gt:0'],
        ]);

        return response()->json($transfers->create($data + ['user_id' => $request->user()->id]), 201);
    }

    public function approve(Request $request, string $transfer, StockTransferService $transfers): JsonResponse
    {
        $this->requirePermission($request, 'stock.transfer.approve');

        return response()->json($transfers->approve($this->find($request, $transfer), $request->user()->id));
    }

    public function dispatch(Request $request, string $transfer, StockTransferService $transfers): JsonResponse
    {
        $this->requirePermission($request, 'stock.transfer.dispatch');

        return response()->json($transfers->dispatch($this->find($request, $transfer), $request->user()->id));
    }

    public function receive(Request $request, string $transfer, StockTransferService $transfers): JsonResponse
    {
        $this->requirePermission($request, 'stock.transfer.receive');

        $data = $request->validate([
            'lines' => ['nullable', 'array'],
            'lines.*.id' => ['required', 'uuid'],
            'lines.*.qty_received' => ['required', 'numeric', 'min:0'],
        ]);
        $received = [];
        foreach ($data['lines'] ?? [] as $line) {
            $received[$line['id']] = (string) $line['qty_received'];
        }

        return response()->json($transfers->receive($this->find($request, $transfer), $request->user()->id, $received));
    }

    public function resolve(Request $request, string $transfer, StockTransferService $transfers): JsonResponse
    {
        $this->requirePermission($request, 'stock.transfer.approve');

        $data = $request->validate([
            'resolution' => ['required', 'in:FOUND_AT_SOURCE,FOUND_AT_DESTINATION,LOST'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'adjustment_reason_code' => ['nullable', 'in:BREAKAGE,THEFT,EXPIRY,SAMPLING,CORRECTION_OF_ERROR,DONATION,COLD_CHAIN_LOSS'],
        ]);

        return response()->json($transfers->resolveDiscrepancy($this->find($request, $transfer), $request->user()->id, $data['resolution'], $data['reason'], $data['adjustment_reason_code'] ?? null));
    }

    private function find(Request $request, string $id): StockTransfer
    {
        $storeIds = Store::where('branch_id', $this->branchId($request))->pluck('id');

        return StockTransfer::where(fn ($q) => $q->whereIn('from_store_id', $storeIds)->orWhereIn('to_store_id', $storeIds))->findOrFail($id);
    }
}
