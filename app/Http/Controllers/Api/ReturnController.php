<?php

namespace App\Http\Controllers\Api;

use App\Models\CustomerReturn;
use App\Models\CustomerReturnLine;
use App\Models\SupplierReturn;
use App\Services\Inventory\ReturnReasons;
use App\Services\Procurement\SupplierReturnService;
use App\Services\Sales\CustomerReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Part 11.1 / 11.2 — customer and supplier returns. */
class ReturnController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');

        return response()->json(
            CustomerReturn::where('branch_id', $this->branchId($request))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->input('sale_id'), fn ($q, $v) => $q->where('sale_id', $v))
                ->with(['customer:id,code,name', 'sale:id,doc_number,sale_mode'])->withCount('lines')
                ->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $return): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');

        $return = $this->find($request, $return)->load(['customer:id,code,name', 'sale:id,doc_number,sale_mode,posted_at', 'lines.product:id,code,name,generic_name,strength,description,base_uom_id', 'lines.product.baseUom:id,code', 'lines.batch:id,batch_number,expiry_date,status']);
        $payload = $return->toArray();
        if (! $request->user()->can('product.cost.view')) {
            unset($payload['cost_total']);
            foreach ($payload['lines'] as &$line) {
                unset($line['unit_cost'], $line['line_cost']);
            }
        }

        return response()->json($payload);
    }

    public function store(Request $request, CustomerReturnService $returns): JsonResponse
    {
        $this->requirePermission($request, 'return.create');

        $data = $request->validate([
            'sale_id' => ['required', 'uuid', 'exists:sales,id'],
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'recall_id' => ['nullable', 'uuid', 'exists:recalls,id'],
            'refund_method' => ['nullable', 'in:CASH,MPESA,BANK,CUSTOMER_ACCOUNT'],
            'refund_reference' => ['nullable', 'string', 'max:100'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sale_line_id' => ['required', 'uuid'],
            'lines.*.batch_id' => ['required', 'uuid', 'exists:product_batches,id'],
            'lines.*.qty_base' => ['required', 'numeric', 'gt:0'],
            'lines.*.disposition' => ['nullable', 'in:RESALEABLE,QUARANTINE,DESTROY,REJECT'],
            'lines.*.inspection_notes' => ['nullable', 'string', 'max:255'],
            'lines.*.return_reason' => ['nullable', Rule::in(ReturnReasons::CUSTOMER)],
            'lines.*.remarks' => ['nullable', 'required_if:lines.*.return_reason,OTHER', 'string', 'max:500'],
        ]);

        foreach ($data['lines'] as $line) {
            if (($line['disposition'] ?? null) === 'RESALEABLE') {
                $this->requirePermission($request, 'quality.release');
            }
        }

        return response()->json($returns->create($data + ['user_id' => $request->user()->id]), 201);
    }

    /** The inspection decision. RESALEABLE is a pharmacist's call (Part 11.1). */
    public function disposition(Request $request, string $return, string $line, CustomerReturnService $returns): JsonResponse
    {
        $this->requirePermission($request, 'return.post');
        $data = $request->validate([
            'disposition' => ['required', 'in:RESALEABLE,QUARANTINE,DESTROY,REJECT'],
            'inspection_notes' => ['nullable', 'string', 'max:255'],
        ]);
        if ($data['disposition'] === 'RESALEABLE') {
            $this->requirePermission($request, 'quality.release');
        }

        $returnLine = CustomerReturnLine::where('customer_return_id', $this->find($request, $return)->id)->findOrFail($line);

        return response()->json($returns->disposition($returnLine, $data['disposition'], $data['inspection_notes'] ?? null, $request->user()->id));
    }

    public function post(Request $request, string $return, CustomerReturnService $returns): JsonResponse
    {
        $this->requirePermission($request, 'return.post');
        $data = $request->validate(['witness_user_id' => ['nullable', 'integer', 'exists:users,id']]);

        return response()->json($returns->post($this->find($request, $return), $request->user()->id, $data['witness_user_id'] ?? null));
    }

    public function reject(Request $request, string $return, CustomerReturnService $returns): JsonResponse
    {
        $this->requirePermission($request, 'return.post');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        return response()->json($returns->reject($this->find($request, $return), $request->user()->id, $data['reason']));
    }

    public function supplierReturns(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'supplier.view');

        return response()->json(
            SupplierReturn::where('branch_id', $this->branchId($request))
                ->with(['supplier:id,code,name', 'store:id,code'])->withCount('lines')
                ->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 25))
        );
    }

    public function supplierReturn(Request $request, string $return): JsonResponse
    {
        $this->requirePermission($request, 'supplier.view');

        return response()->json(SupplierReturn::where('branch_id', $this->branchId($request))->with(['supplier:id,code,name', 'store:id,code', 'lines.product:id,code,name,generic_name,strength,description,base_uom_id', 'lines.product.baseUom:id,code', 'lines.batch:id,batch_number,expiry_date,status'])->findOrFail($return));
    }

    /** POST /api/supplier-returns — posts immediately: the reverse of a GRN. */
    public function storeSupplierReturn(Request $request, SupplierReturnService $returns): JsonResponse
    {
        $this->requirePermission($request, 'supplier.return');

        $data = $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:suppliers,id'],
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'recall_id' => ['nullable', 'uuid', 'exists:recalls,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'lines.*.batch_id' => ['required', 'uuid', 'exists:product_batches,id'],
            'lines.*.qty_base' => ['required', 'numeric', 'gt:0'],
            'lines.*.return_reason' => ['nullable', Rule::in(ReturnReasons::SUPPLIER)],
            'lines.*.remarks' => ['nullable', 'required_if:lines.*.return_reason,OTHER', 'string', 'max:500'],
        ]);

        return response()->json($returns->post($data + ['user_id' => $request->user()->id]), 201);
    }

    private function find(Request $request, string $id): CustomerReturn
    {
        return CustomerReturn::where('branch_id', $this->branchId($request))->findOrFail($id);
    }
}
