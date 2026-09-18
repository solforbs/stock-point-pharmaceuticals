<?php

namespace App\Http\Controllers\Api;

use App\Models\DeliveryNote;
use App\Models\GoodsReceipt;
use App\Models\PickingList;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\StockAdjustment;
use App\Models\Store;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Part 21 — the read side the screens need: lists and single-document
 * views for every document type that posts elsewhere, plus the small
 * lookups (users for witness/approver pickers, product categories).
 */
class DocumentListController extends ApiController
{
    public function purchaseOrder(Request $request, string $po): JsonResponse
    {
        $this->requirePermission($request, 'po.create');

        return response()->json(
            PurchaseOrder::where('branch_id', $this->branchId($request))
                ->with([
                    'supplier:id,code,name,licence_expiry,status',
                    // qty_received = Σ accepted across every GRN for the line (Part 9.2 outstanding quantity).
                    'lines' => fn ($q) => $q->withSum('goodsReceiptLines as qty_received', 'qty_accepted'),
                    'lines.product:id,code,name', 'lines.uom:id,code,name',
                    'goodsReceipts:id,purchase_order_id,doc_number,status,received_at',
                ])
                ->findOrFail($po)
        );
    }

    public function quotations(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');

        return response()->json(
            Quotation::where('branch_id', $this->branchId($request))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->input('customer_id'), fn ($q, $v) => $q->where('customer_id', $v))
                ->with('customer:id,code,name')->withCount('lines')
                ->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 25))
        );
    }

    public function goodsReceipts(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'grn.create');

        return response()->json(
            GoodsReceipt::where('branch_id', $this->branchId($request))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->input('supplier_id'), fn ($q, $v) => $q->where('supplier_id', $v))
                ->with(['supplier:id,code,name', 'purchaseOrder:id,doc_number', 'store:id,code'])->withCount('lines')
                ->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 25))
        );
    }

    public function supplierInvoices(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'invoice.match');

        return response()->json(
            SupplierInvoice::where('branch_id', $this->branchId($request))
                ->when($request->input('match_status'), fn ($q, $v) => $q->where('match_status', $v))
                ->when($request->input('supplier_id'), fn ($q, $v) => $q->where('supplier_id', $v))
                ->with('supplier:id,code,name')->withCount('lines')
                ->orderByDesc('invoice_date')->paginate($request->integer('per_page', 25))
        );
    }

    public function supplierInvoice(Request $request, string $invoice): JsonResponse
    {
        $this->requirePermission($request, 'invoice.match');

        return response()->json(
            SupplierInvoice::where('branch_id', $this->branchId($request))
                ->with(['supplier:id,code,name', 'lines.product:id,code,name', 'lines.purchaseOrderLine:id,purchase_order_id,qty_ordered,unit_price'])
                ->findOrFail($invoice)
        );
    }

    public function adjustments(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        return response()->json(
            StockAdjustment::whereIn('store_id', $this->storeIds($request))
                ->when($request->input('approval_status'), fn ($q, $v) => $q->where('approval_status', $v))
                ->with('store:id,code,name')->withCount('lines')
                ->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 25))
        );
    }

    public function adjustment(Request $request, string $adjustment): JsonResponse
    {
        $this->requirePermission($request, 'stock.view');

        $adjustment = StockAdjustment::whereIn('store_id', $this->storeIds($request))
            ->with(['store:id,code,name', 'lines.product:id,code,name', 'lines.batch:id,batch_number,expiry_date'])
            ->findOrFail($adjustment);
        $payload = $adjustment->toArray();
        if (! $request->user()->can('product.cost.view')) {
            unset($payload['total_value']);
            foreach ($payload['lines'] as &$line) {
                unset($line['unit_cost'], $line['line_value']);
            }
        }

        return response()->json($payload);
    }

    public function deliveryNotes(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');

        return response()->json(
            DeliveryNote::where('branch_id', $this->branchId($request))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->with(['salesOrder:id,doc_number,customer_id', 'salesOrder.customer:id,code,name'])->withCount('lines')
                ->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 25))
        );
    }

    public function deliveryNote(Request $request, string $note): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');

        return response()->json(
            DeliveryNote::where('branch_id', $this->branchId($request))
                ->with(['salesOrder:id,doc_number,customer_id,status', 'salesOrder.customer:id,code,name', 'lines.product:id,code,name', 'lines.batchAllocations.batch:id,batch_number,expiry_date'])
                ->findOrFail($note)
        );
    }

    public function pickingLists(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'warehouse.pick');

        return response()->json(
            PickingList::where('branch_id', $this->branchId($request))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->with(['salesOrder:id,doc_number,customer_id', 'salesOrder.customer:id,code,name'])->withCount('lines')
                ->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 25))
        );
    }

    public function pickingList(Request $request, string $list): JsonResponse
    {
        $this->requirePermission($request, 'warehouse.pick');

        return response()->json(
            PickingList::where('branch_id', $this->branchId($request))
                ->with(['salesOrder:id,doc_number,customer_id,status', 'salesOrder.customer:id,code,name', 'lines.product:id,code,name', 'lines.batch:id,batch_number,expiry_date', 'deliveryNotes:id,picking_list_id,doc_number,status'])
                ->findOrFail($list)
        );
    }

    /** A minimal directory for witness and approver pickers: id and name only. */
    public function users(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));

        return response()->json(
            User::query()->where('is_active', true)
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "%{$term}%")))
                ->orderBy('name')->limit(50)->get(['id', 'name', 'username'])
        );
    }

    public function productCategories(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'product.view');

        return response()->json(ProductCategory::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'parent_id']));
    }

    /**
     * @return list<string>
     */
    private function storeIds(Request $request): array
    {
        return Store::where('branch_id', $this->branchId($request))->pluck('id')->map(fn ($id) => (string) $id)->all();
    }
}
