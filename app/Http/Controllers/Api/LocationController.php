<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Part 10.6 — warehouse locations (aisle / rack / bin) inside a store of the
 * active branch, and what is currently put away at each of them according
 * to the stock ledger.
 */
class LocationController extends ApiController
{
    /** GET /api/locations?store_id=&include_inactive= */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        $onHand = DB::table('stock_ledgers')->selectRaw('COALESCE(SUM(qty_base), 0)')->whereColumn('stock_ledgers.location_id', 'locations.id');

        $locations = Location::query()
            ->whereIn('store_id', $this->branchStoreIds($request))
            ->when($request->input('store_id'), fn ($q, $v) => $q->where('store_id', $v))
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->with('store:id,code,name')
            ->select('locations.*')
            ->selectSub($onHand, 'on_hand_base')
            ->orderBy('aisle')->orderBy('rack')->orderBy('bin')->orderBy('code')
            ->get();

        return response()->json($locations);
    }

    /** POST /api/locations */
    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'location.manage');

        $data = $request->validate([
            'store_id' => ['required', 'uuid', Rule::in($this->branchStoreIds($request))],
            'code' => ['required', 'string', 'max:40', Rule::unique('locations', 'code')->where('store_id', $request->input('store_id'))],
            ...$this->attributeRules(),
        ]);

        $location = Location::create($data + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        AuditLog::record('LOCATION_CREATED', 'location', (string) $location->id, ['reference' => $location->code, 'after_json' => $location->toArray()]);

        return response()->json($location->load('store:id,code,name'), 201);
    }

    /** PATCH /api/locations/{location} — rename, re-slot or (de)activate. */
    public function update(Request $request, string $location): JsonResponse
    {
        $this->requirePermission($request, 'location.manage');

        $location = Location::whereIn('store_id', $this->branchStoreIds($request))->findOrFail($location);
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:40', Rule::unique('locations', 'code')->where('store_id', $location->store_id)->ignore($location->id)],
            'is_active' => ['sometimes', 'boolean'],
            ...array_map(fn (array $rules) => ['sometimes', ...$rules], $this->attributeRules()),
        ]);

        $before = $location->toArray();
        $location->update($data + ['updated_by' => $request->user()->id]);

        $action = array_key_exists('is_active', $data) && $data['is_active'] !== $before['is_active']
            ? ($data['is_active'] ? 'LOCATION_REACTIVATED' : 'LOCATION_DEACTIVATED')
            : 'LOCATION_UPDATED';
        AuditLog::record($action, 'location', (string) $location->id, ['reference' => $location->code, 'before_json' => $before, 'after_json' => $location->fresh()?->toArray()]);

        return response()->json($location->fresh(['store:id,code,name']));
    }

    /** GET /api/locations/{location}/stock — on hand by product and batch from the ledger. */
    public function stock(Request $request, string $location): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        $location = Location::whereIn('store_id', $this->branchStoreIds($request))->with('store:id,code,name')->findOrFail($location);

        $rows = DB::table('stock_ledgers as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->join('product_batches as b', 'b.id', '=', 'l.batch_id')
            ->where('l.location_id', $location->id)
            ->groupBy('l.product_id', 'l.batch_id', 'p.code', 'p.name', 'b.batch_number', 'b.expiry_date', 'b.status')
            ->havingRaw('SUM(l.qty_base) <> 0')
            ->orderBy('p.name')->orderBy('b.expiry_date')
            ->selectRaw('l.product_id, l.batch_id, p.code as product_code, p.name as product_name, b.batch_number, b.expiry_date, b.status as batch_status, SUM(l.qty_base) as qty_base')
            ->get()
            ->map(fn ($r) => [
                'product_id' => $r->product_id, 'batch_id' => $r->batch_id,
                'product_code' => $r->product_code, 'product_name' => $r->product_name,
                'batch_number' => $r->batch_number, 'expiry_date' => $r->expiry_date, 'batch_status' => $r->batch_status,
                'qty_base' => bcadd((string) $r->qty_base, '0', 4),
            ]);

        return response()->json(['location' => $location, 'data' => $rows]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function attributeRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'location_type' => ['sometimes', 'required', Rule::in(Location::TYPES)],
            'aisle' => ['nullable', 'string', 'max:20'],
            'rack' => ['nullable', 'string', 'max:20'],
            'bin' => ['nullable', 'string', 'max:20'],
            'capacity' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<string>
     */
    private function branchStoreIds(Request $request): array
    {
        return Store::where('branch_id', $this->branchId($request))->pluck('id')->map(fn ($id) => (string) $id)->all();
    }
}
