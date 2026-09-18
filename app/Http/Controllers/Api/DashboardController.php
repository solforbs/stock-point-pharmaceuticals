<?php

namespace App\Http\Controllers\Api;

use App\Models\CustomerReturn;
use App\Models\FinancialPeriod;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Part 16.3 — one call for the landing page. Every block is gated by the
 * permission that gates the screen it links to and is null when the user
 * cannot see that screen, so the SPA never learns a number it may not show.
 */
class DashboardController extends ApiController
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $this->branchId($request);
        $organisationId = $this->organisationId($request);
        $today = now()->toDateString();
        $storeIds = Store::where('branch_id', $branchId)->pluck('id');

        $sales = null;
        if ($user->can('sale.view')) {
            $byMode = DB::table('sales')->where('branch_id', $branchId)->where('status', 'POSTED')->whereDate('posted_at', $today)
                ->selectRaw('sale_mode, COUNT(*) as n, COALESCE(SUM(grand_total), 0) as total')->groupBy('sale_mode')->get();
            $sales = [
                'count' => (int) $byMode->sum('n'),
                'total' => number_format((float) $byMode->sum('total'), 4, '.', ''),
                'by_mode' => $byMode->mapWithKeys(fn ($r) => [$r->sale_mode => ['count' => (int) $r->n, 'total' => number_format((float) $r->total, 4, '.', '')]]),
                'voided_today' => Sale::where('branch_id', $branchId)->where('status', 'VOIDED')->whereDate('voided_at', $today)->count(),
            ];
        }

        // Each approval queue is counted only for someone who may clear it.
        $queues = [
            'requisitions' => ['requisition.approve', fn () => Requisition::where('branch_id', $branchId)->where('status', 'PENDING_APPROVAL')->count()],
            'purchase_orders' => ['po.approve', fn () => PurchaseOrder::where('branch_id', $branchId)->where('status', 'PENDING_APPROVAL')->count()],
            'adjustments' => ['stock.adjust.approve', fn () => StockAdjustment::whereIn('store_id', $storeIds)->where('approval_status', 'PENDING')->count()],
            'transfers' => ['stock.transfer.approve', fn () => StockTransfer::whereIn('from_store_id', $storeIds)->where('status', 'DRAFT')->count()],
            'counts' => ['stock.count.post', fn () => StockCount::whereIn('store_id', $storeIds)->where('status', 'REVIEW')->count()],
            'customer_returns' => ['return.post', fn () => CustomerReturn::where('branch_id', $branchId)->where('status', 'DRAFT')->count()],
        ];
        $approvals = [];
        foreach ($queues as $key => [$permission, $count]) {
            if ($user->can($permission)) {
                $approvals[$key] = $count();
            }
        }

        $inventory = null;
        if ($user->can('stock.view')) {
            $batchesInBranch = ProductBatch::query()->whereExists(fn ($q) => $q->select(DB::raw(1))->from('stock_balances')
                ->whereColumn('stock_balances.batch_id', 'product_batches.id')->whereIn('stock_balances.store_id', $storeIds)->where('stock_balances.qty_on_hand', '>', 0));
            $inventory = [
                'pending_qc_batches' => (clone $batchesInBranch)->where('status', 'PENDING_QC')->count(),
                'quarantined_batches' => (clone $batchesInBranch)->where('status', 'QUARANTINED')->count(),
                'expiring_90d_batches' => (clone $batchesInBranch)->where('status', 'RELEASED')->whereDate('expiry_date', '<=', now()->addDays(90)->toDateString())->count(),
                'expired_batches_on_hand' => (clone $batchesInBranch)->where('status', 'EXPIRED')->count(),
                'transfers_in_transit' => StockTransfer::whereIn('status', ['DISPATCHED', 'DISCREPANCY'])
                    ->where(fn ($q) => $q->whereIn('from_store_id', $storeIds)->orWhereIn('to_store_id', $storeIds))->count(),
            ];
        }

        $procurement = null;
        if ($user->can('po.create')) {
            $procurement = [
                'open_purchase_orders' => PurchaseOrder::where('branch_id', $branchId)->whereIn('status', ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED'])->count(),
                'suppliers_licence_expired' => Supplier::where('organisation_id', $organisationId)->where('is_active', true)->whereDate('licence_expiry', '<', $today)->count(),
                'suppliers_licence_expiring_30d' => Supplier::where('organisation_id', $organisationId)->where('is_active', true)
                    ->whereBetween('licence_expiry', [$today, now()->addDays(30)->toDateString()])->count(),
            ];
        }

        $compliance = null;
        if ($user->can('tax.etims.manage')) {
            $compliance = [
                'etims_failed' => Sale::where('branch_id', $branchId)->where('etims_status', 'FAILED')->count()
                    + CustomerReturn::where('branch_id', $branchId)->where('etims_status', 'FAILED')->count(),
                'etims_pending' => Sale::where('branch_id', $branchId)->where('etims_status', 'PENDING')->count(),
            ];
        }

        $finance = null;
        if ($user->can('journal.post') || $user->can('period.close')) {
            $open = FinancialPeriod::where('organisation_id', $organisationId)->where('status', 'OPEN')
                ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->first();
            $finance = [
                'open_period' => $open?->only(['id', 'fiscal_year', 'period_no', 'start_date', 'end_date']),
                'period_open_for_today' => $open !== null,
            ];
        }

        return response()->json([
            'as_of' => now()->toIso8601String(),
            'sales_today' => $sales,
            'approvals' => $approvals === [] ? null : $approvals,
            'inventory' => $inventory,
            'procurement' => $procurement,
            'compliance' => $compliance,
            'finance' => $finance,
        ]);
    }
}
