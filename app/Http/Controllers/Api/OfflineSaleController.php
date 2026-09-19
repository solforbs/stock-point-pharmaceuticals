<?php

namespace App\Http\Controllers\Api;

use App\Models\OfflineSale;
use App\Models\Store;
use App\Services\Sales\OfflineSaleService;
use App\Services\Sales\StoreNotSellableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Part 16.7 / 17.5 — the counter keeps selling through an internet outage.
 * The terminal downloads a price pack while it is online, sells from it into
 * a local outbox while it is not, and replays the outbox here on reconnect.
 */
class OfflineSaleController extends ApiController
{
    /** GET /api/pos/offline-pack?store_id= — what this till may sell offline, and at what price. */
    public function pack(Request $request, OfflineSaleService $offline): JsonResponse
    {
        $this->requirePermission($request, 'sale.create');
        $data = $request->validate(['store_id' => ['required', 'uuid']]);

        $store = $this->sellableStore($request, $data['store_id']);

        return response()->json($offline->pricePack($store, $request->user()));
    }

    /**
     * POST /api/pos/offline-sales — one sale from a terminal's outbox. Any
     * 2xx means the server now holds it: 201 posted, 202 kept as a conflict
     * for a supervisor. A replay of the same id answers with the original.
     */
    public function store(Request $request, OfflineSaleService $offline): JsonResponse
    {
        $this->requirePermission($request, 'sale.create');
        $organisationId = $this->organisationId($request);

        $data = $request->validate([
            'id' => ['required', 'string', 'max:100'],
            'store_id' => ['required', 'uuid'],
            'terminal_id' => ['nullable', 'string', 'max:50'],
            'sold_at' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(5)->toIso8601String()],
            // Who was at the till, as the device recorded it; kept with the
            // payload for when another cashier hands the sale over.
            'sold_by' => ['nullable', 'string', 'max:150'],
            'total' => ['required', 'numeric', 'gt:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('organisation_id', $organisationId)],
            'lines.*.uom_id' => ['required', 'uuid'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gt:0'],
            'lines.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:CASH,MPESA,CARD'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ]);

        if ($existing = OfflineSale::where('idempotency_key', $data['id'])->first()) {
            return response()->json($existing->load('sale:id,doc_number,grand_total'))->header('X-Idempotent-Replay', 'true');
        }

        $store = $this->sellableStore($request, $data['store_id'], requireSellable: false);
        $result = $offline->submit($data, $store, $request->user());

        return response()->json($result->load('sale:id,doc_number,grand_total'), $result->status === OfflineSale::Posted ? 201 : 202);
    }

    /** GET /api/pos/offline-sales?status= — what the terminals handed over, conflicts first. */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');
        $request->validate(['status' => ['nullable', 'in:POSTED,CONFLICT,DISMISSED']]);

        $sales = OfflineSale::where('branch_id', $this->branchId($request))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->with(['sale:id,doc_number,grand_total', 'store:id,code,name', 'user:id,name', 'resolver:id,name'])
            ->orderByRaw("CASE WHEN status = 'CONFLICT' THEN 0 ELSE 1 END")
            ->orderByDesc('sold_at')
            ->paginate($request->integer('per_page', 50));

        return response()->json($sales);
    }

    /** POST /api/pos/offline-sales/{id}/retry — try a conflict again, e.g. after the stock is corrected. */
    public function retry(Request $request, string $offlineSale, OfflineSaleService $offline): JsonResponse
    {
        $this->requirePermission($request, 'sale.create');
        $record = OfflineSale::where('branch_id', $this->branchId($request))->findOrFail($offlineSale);

        return response()->json($offline->retry($record, $request->user())->load('sale:id,doc_number,grand_total'));
    }

    /** POST /api/pos/offline-sales/{id}/dismiss {reason} — decide a conflict will not be posted. */
    public function dismiss(Request $request, string $offlineSale, OfflineSaleService $offline): JsonResponse
    {
        $this->requirePermission($request, 'sale.void');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $record = OfflineSale::where('branch_id', $this->branchId($request))->findOrFail($offlineSale);

        return response()->json($offline->dismiss($record, $request->user(), $data['reason']));
    }

    /**
     * A store in the active branch. The pack is only ever built for a store
     * that may sell; a replay is accepted for any store in the branch, and a
     * store closed to sales since then becomes a conflict, not a lost sale.
     */
    private function sellableStore(Request $request, string $storeId, bool $requireSellable = true): Store
    {
        $store = Store::where('branch_id', $this->branchId($request))->with('branch')->findOrFail($storeId);

        if ($requireSellable && ! $store->is_sellable) {
            throw new StoreNotSellableException($store->code);
        }

        return $store;
    }
}
