<?php

namespace App\Http\Controllers\Api;

use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\Store;
use App\Services\Inventory\StockCountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Part 7.7 / 21.8 — batch-level stock counts. */
class StockCountController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        return response()->json(
            StockCount::query()
                ->whereIn('store_id', Store::where('branch_id', $this->branchId($request))->pluck('id'))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->with('store:id,code,name')->withCount('lines')
                ->orderByDesc('created_at')->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $count): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        $count = $this->find($request, $count)->load(['store:id,code,name', 'lines.batch:id,batch_number,expiry_date', 'lines.product:id,code,name']);
        $payload = $count->toArray();
        if (! $request->user()->can('product.cost.view')) {
            foreach ($payload['lines'] as &$line) {
                unset($line['variance_value']);
            }
        }

        return response()->json($payload + ['variance_reasons' => StockCountService::VARIANCE_REASONS]);
    }

    public function store(Request $request, StockCountService $counts): JsonResponse
    {
        $this->requirePermission($request, 'stock.count.enter');

        $data = $request->validate([
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['uuid', 'exists:products,id'],
        ]);

        return response()->json($counts->plan($data + ['user_id' => $request->user()->id]), 201);
    }

    public function start(Request $request, string $count, StockCountService $counts): JsonResponse
    {
        $this->requirePermission($request, 'stock.count.enter');

        return response()->json($counts->start($this->find($request, $count), $request->user()->id));
    }

    public function enter(Request $request, string $count, string $line, StockCountService $counts): JsonResponse
    {
        $this->requirePermission($request, 'stock.count.enter');

        $data = $request->validate([
            'counted_qty' => ['required', 'numeric', 'min:0'],
            'reason_code' => ['nullable', 'string', 'max:40'],
        ]);
        $countLine = StockCountLine::where('stock_count_id', $this->find($request, $count)->id)->findOrFail($line);

        return response()->json($counts->enter($countLine, (string) $data['counted_qty'], $data['reason_code'] ?? null));
    }

    public function review(Request $request, string $count, StockCountService $counts): JsonResponse
    {
        $this->requirePermission($request, 'stock.count.enter');

        return response()->json($counts->submitForReview($this->find($request, $count), $request->user()->id));
    }

    public function approve(Request $request, string $count, StockCountService $counts): JsonResponse
    {
        $this->requirePermission($request, 'stock.count.post');

        return response()->json($counts->approve($this->find($request, $count), $request->user()->id));
    }

    public function close(Request $request, string $count, StockCountService $counts): JsonResponse
    {
        $this->requirePermission($request, 'stock.count.post');

        return response()->json($counts->close($this->find($request, $count), $request->user()->id));
    }

    private function find(Request $request, string $id): StockCount
    {
        return StockCount::whereIn('store_id', Store::where('branch_id', $this->branchId($request))->pluck('id'))->findOrFail($id);
    }
}
