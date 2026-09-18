<?php

namespace App\Http\Controllers\Api;

use App\Models\ColdChainExcursion;
use App\Models\ColdChainReading;
use App\Models\Store;
use App\Services\Quality\ColdChainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Part 8.5 — cold chain readings, excursions and their review. */
class ColdChainController extends ApiController
{
    /** GET /api/cold-chain/summary — per store: window, last reading, open excursions. */
    public function summary(Request $request, ColdChainService $coldChain): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');
        $branchId = $this->branchId($request);

        $openCounts = ColdChainExcursion::where('branch_id', $branchId)->where('status', '!=', 'CLOSED')
            ->selectRaw('store_id, COUNT(*) as open_count')->groupBy('store_id')->pluck('open_count', 'store_id');

        $stores = Store::where('branch_id', $branchId)->with('storageCondition')->orderBy('code')->get()
            ->map(function (Store $store) use ($coldChain, $openCounts) {
                $range = $coldChain->rangeFor($store);
                $last = ColdChainReading::where('store_id', $store->id)->latest('recorded_at')->first();

                return [
                    'store' => $store->only(['id', 'code', 'name', 'store_type']),
                    'range' => $range,
                    'last_reading' => $last,
                    'last_in_range' => $last ? $coldChain->isInRange((string) $last->temperature_c, $range) : null,
                    'open_excursions' => (int) ($openCounts[$store->id] ?? 0),
                ];
            });

        return response()->json($stores);
    }

    public function readings(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');
        $filters = $request->validate([
            'store_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'excursions_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,500'],
        ]);

        return response()->json(
            ColdChainReading::where('branch_id', $this->branchId($request))
                ->when($filters['store_id'] ?? null, fn ($q, $v) => $q->where('store_id', $v))
                ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('recorded_at', '>=', $v))
                ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('recorded_at', '<=', $v))
                ->when($request->boolean('excursions_only'), fn ($q) => $q->where('is_excursion', true))
                ->with(['store:id,code,name', 'recorder:id,name'])
                ->orderByDesc('recorded_at')
                ->paginate($filters['per_page'] ?? 25)
        );
    }

    public function storeReading(Request $request, ColdChainService $coldChain): JsonResponse
    {
        $this->requirePermission($request, 'coldchain.record');
        $data = $request->validate([
            'store_id' => ['required', 'uuid'],
            'temperature_c' => ['required', 'numeric', 'between:-80,80'],
            'humidity_pct' => ['nullable', 'numeric', 'between:0,100'],
            'recorded_at' => ['nullable', 'date', 'before_or_equal:now'],
            'source' => ['nullable', 'in:MANUAL,LOGGER'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $store = Store::where('branch_id', $this->branchId($request))->with('storageCondition')->findOrFail($data['store_id']);
        $reading = $coldChain->record($store, $data, $request->user()->id);

        return response()->json($reading->load(['store:id,code,name', 'excursion']), 201);
    }

    public function excursions(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');
        $filters = $request->validate([
            'status' => ['nullable', 'in:OPEN,UNDER_REVIEW,CLOSED,UNRESOLVED'],
            'store_id' => ['nullable', 'uuid'],
        ]);

        return response()->json(
            ColdChainExcursion::where('branch_id', $this->branchId($request))
                ->when($filters['status'] ?? null, fn ($q, $v) => $v === 'UNRESOLVED' ? $q->where('status', '!=', 'CLOSED') : $q->where('status', $v))
                ->when($filters['store_id'] ?? null, fn ($q, $v) => $q->where('store_id', $v))
                ->with(['store:id,code,name', 'closer:id,name'])
                ->withCount('readings')
                ->orderByDesc('started_at')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function excursion(Request $request, string $excursion): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        return response()->json($this->findExcursion($request, $excursion)->load([
            'store:id,code,name,store_type', 'closer:id,name', 'reviewer:id,name',
            'readings' => fn ($q) => $q->orderBy('recorded_at')->with('recorder:id,name'),
        ]));
    }

    /** POST /api/cold-chain/excursions/{id}/review — a pharmacist takes the excursion under review. */
    public function reviewExcursion(Request $request, string $excursion, ColdChainService $coldChain): JsonResponse
    {
        $this->requirePermission($request, 'coldchain.review');

        return response()->json($coldChain->startReview($this->findExcursion($request, $excursion), $request->user()->id));
    }

    public function closeExcursion(Request $request, string $excursion, ColdChainService $coldChain): JsonResponse
    {
        $this->requirePermission($request, 'coldchain.review');
        $data = $request->validate([
            'impact_assessment' => ['required', 'string', 'min:10', 'max:4000'],
            'action_taken' => ['required', 'in:NO_IMPACT,STOCK_QUARANTINED,STOCK_DISPOSED'],
        ]);

        $closed = $coldChain->close($this->findExcursion($request, $excursion), $data['impact_assessment'], $data['action_taken'], $request->user()->id);

        return response()->json($closed->load(['store:id,code,name', 'closer:id,name']));
    }

    private function findExcursion(Request $request, string $id): ColdChainExcursion
    {
        return ColdChainExcursion::where('branch_id', $this->branchId($request))->findOrFail($id);
    }
}
