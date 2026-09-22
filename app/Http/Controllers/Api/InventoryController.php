<?php

namespace App\Http\Controllers\Api;

use App\Models\ProductBatch;
use App\Models\StockAdjustment;
use App\Models\StockLedger;
use App\Models\Store;
use App\Services\Inventory\BatchQualityService;
use App\Services\Inventory\InventoryReport;
use App\Services\Inventory\OpeningStockService;
use App\Services\Inventory\OpeningStockValidationException;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends ApiController
{
    /** GET /api/inventory/stock — the eight quantity states (Part 7.3). */
    public function stock(Request $request, InventoryReport $report): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        $filters = $request->validate([
            'product_id' => ['nullable', 'uuid'],
            'store_id' => ['nullable', 'uuid'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $rows = $report->stockStates($this->organisationId($request), $filters['product_id'] ?? null, $filters['store_id'] ?? null, $filters['q'] ?? null);

        if (! $request->user()->can('product.cost.view')) {
            foreach ($rows as &$row) {
                unset($row['value_at_cost']);
                foreach ($row['batches'] as &$b) {
                    unset($b['wac']);
                }
            }
        }

        return response()->json(['data' => $rows]);
    }

    /** GET /api/inventory/ledger — the real one, with a running balance when a batch is chosen. */
    public function ledger(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        $filters = $request->validate([
            'product_id' => ['nullable', 'uuid'],
            'batch_id' => ['nullable', 'uuid'],
            'store_id' => ['nullable', 'uuid'],
            'txn_type' => ['nullable', 'string', 'max:30'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'between:1,500'],
        ]);

        $query = StockLedger::query()
            ->where('organisation_id', $this->organisationId($request))
            ->when($filters['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($filters['batch_id'] ?? null, fn ($q, $v) => $q->where('batch_id', $v))
            ->when($filters['store_id'] ?? null, fn ($q, $v) => $q->where('store_id', $v))
            ->when($filters['txn_type'] ?? null, fn ($q, $v) => $q->where('txn_type', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('txn_datetime', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('txn_datetime', '<=', $v))
            ->with(['product:id,code,name', 'batch:id,batch_number,expiry_date', 'store:id,code'])
            ->orderBy('txn_datetime')->orderBy('created_at');

        $showCost = $request->user()->can('product.cost.view');

        if (! empty($filters['batch_id']) && ! empty($filters['store_id'])) {
            $running = '0.0000';
            $rows = $query->get()->map(function (StockLedger $row) use (&$running, $showCost) {
                $running = bcadd($running, (string) $row->qty_base, 4);
                $out = $row->toArray() + ['running_balance' => $running];
                if (! $showCost) {
                    unset($out['unit_cost'], $out['total_cost']);
                }

                return $out;
            });

            return response()->json(['data' => $rows]);
        }

        $page = $query->paginate($filters['per_page'] ?? 50);
        if (! $showCost) {
            $page->getCollection()->transform(fn (StockLedger $row) => collect($row->toArray())->except(['unit_cost', 'total_cost'])->all());
        }

        return response()->json($page);
    }

    /** POST /api/inventory/adjustments — reason mandatory; above threshold → approval (Part 7.8). */
    public function storeAdjustment(Request $request, StockAdjustmentService $adjustments): JsonResponse
    {
        $this->requirePermission($request, 'stock.adjust');

        $data = $request->validate([
            'store_id' => ['required', 'uuid', TenantRules::exists('stores')],
            'reason_code' => ['required', 'in:BREAKAGE,THEFT,EXPIRY,SAMPLING,CORRECTION_OF_ERROR,DONATION,COLD_CHAIN_LOSS'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', TenantRules::exists('products')],
            'lines.*.batch_id' => ['required', 'uuid', TenantRules::exists('product_batches')],
            'lines.*.qty_base' => ['required', 'numeric', 'not_in:0'],
        ]);

        $adjustment = $adjustments->create($data + ['user_id' => $request->user()->id]);

        return response()->json($adjustment, $adjustment->approval_status === 'APPROVED' ? 201 : 202);
    }

    public function approveAdjustment(Request $request, string $adjustment, StockAdjustmentService $adjustments): JsonResponse
    {
        $this->requirePermission($request, 'stock.adjust.approve');

        return response()->json($adjustments->approve(StockAdjustment::findOrFail($adjustment), $request->user()->id));
    }

    public function rejectAdjustment(Request $request, string $adjustment, StockAdjustmentService $adjustments): JsonResponse
    {
        $this->requirePermission($request, 'stock.adjust.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return response()->json($adjustments->reject(StockAdjustment::findOrFail($adjustment), $request->user()->id, $data['reason']));
    }

    /** GET /api/batches — filter by product, status, expiry window. */
    public function batches(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        $filters = $request->validate([
            'product_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'string', 'max:30'],
            'expiring_within_days' => ['nullable', 'integer', 'min:0'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $batches = ProductBatch::query()
            ->where('organisation_id', $this->organisationId($request))
            ->when($filters['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when(isset($filters['expiring_within_days']), fn ($q) => $q->whereDate('expiry_date', '<=', now()->addDays((int) $filters['expiring_within_days'])->toDateString()))
            ->with(['product:id,code,name', 'supplier:id,code,name'])
            ->withSum('balances as qty_on_hand', 'qty_on_hand')
            ->orderBy('expiry_date')
            ->paginate($filters['per_page'] ?? 50);

        return response()->json($batches);
    }

    /** GET /api/batches/{id} — Part 22.11: the full trace, never an estimate. */
    public function batch(Request $request, string $batch): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        $batch = ProductBatch::where('organisation_id', $this->organisationId($request))
            ->with(['product:id,code,name', 'supplier:id,code,name', 'balances.store:id,code,name'])
            ->findOrFail($batch);

        $movements = StockLedger::where('batch_id', $batch->id)
            ->with(['store:id,code'])
            ->orderBy('txn_datetime')
            ->get(['id', 'txn_type', 'store_id', 'qty_base', 'unit_cost', 'source_doc_type', 'source_doc_id', 'txn_datetime', 'user_id']);

        // Who received it: every sale line allocation → sale → customer.
        $recipients = DB::table('sale_line_batch_allocations as a')
            ->join('sale_lines as l', 'l.id', '=', 'a.sale_line_id')
            ->join('sales as s', 's.id', '=', 'l.sale_id')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->where('a.batch_id', $batch->id)
            ->where('s.status', 'POSTED')
            ->select('s.id as sale_id', 's.doc_number', 's.posted_at', 's.sale_mode', 'c.id as customer_id', 'c.code as customer_code', 'c.name as customer_name', 'a.qty_base', 'a.is_bonus')
            ->orderBy('s.posted_at')
            ->get();

        $showCost = $request->user()->can('product.cost.view');
        $payload = $batch->toArray();
        if (! $showCost) {
            unset($payload['unit_cost'], $payload['landed_unit_cost']);
            foreach ($payload['balances'] as &$b) {
                unset($b['wac']);
            }
        }

        return response()->json($payload + [
            'movements' => $movements->map(fn ($m) => $showCost ? $m : collect($m->toArray())->except('unit_cost')->all()),
            'recipients' => $recipients,
            'distributed_base' => number_format((float) $recipients->sum('qty_base'), 4, '.', ''),
        ]);
    }

    public function releaseBatch(Request $request, string $batch, BatchQualityService $quality): JsonResponse
    {
        $this->requirePermission($request, 'quality.release');
        $data = $request->validate(['justification' => ['nullable', 'string', 'max:255']]);

        return response()->json($quality->release($this->findBatch($request, $batch), $request->user()->id, $data['justification'] ?? null));
    }

    public function quarantineBatch(Request $request, string $batch, BatchQualityService $quality): JsonResponse
    {
        $this->requirePermission($request, 'quality.release');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        return response()->json($quality->quarantine($this->findBatch($request, $batch), $request->user()->id, $data['reason']));
    }

    /**
     * POST /api/inventory/opening-stock — the go-live stock take for one store
     * (Part 7.1). All-or-nothing; validation errors come back per row.
     */
    public function openingStock(Request $request, OpeningStockService $openingStock): JsonResponse
    {
        $this->requirePermission($request, 'stock.count.post');

        $data = $request->validate([
            'store_id' => ['required', 'uuid'],
            'dry_run' => ['nullable', 'boolean'],
            'rows' => ['required', 'array', 'min:1', 'max:10000'],
            'rows.*' => ['array'],
        ]);
        $store = Store::where('branch_id', $this->branchId($request))->findOrFail($data['store_id']);

        try {
            if ($request->boolean('dry_run')) {
                $openingStock->validateOnly($store, $data['rows']);

                return response()->json(['valid' => true, 'lines' => count($data['rows'])]);
            }

            return response()->json($openingStock->import($store, $data['rows'], $request->user()->id), 201);
        } catch (OpeningStockValidationException $e) {
            return $this->error('OPENING_STOCK_INVALID', $e->getMessage(), 422, ['rows' => $e->rowErrors]);
        }
    }

    private function findBatch(Request $request, string $id): ProductBatch
    {
        return ProductBatch::where('organisation_id', $this->organisationId($request))->findOrFail($id);
    }
}
