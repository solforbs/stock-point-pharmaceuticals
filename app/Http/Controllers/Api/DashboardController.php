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

            $pendingQcQty = DB::table('stock_balances as b')
                ->join('product_batches as pb', 'pb.id', '=', 'b.batch_id')
                ->whereIn('b.store_id', $storeIds)
                ->where('pb.status', 'PENDING_QC')
                ->sum('b.qty_on_hand');

            $expiring90dQty = DB::table('stock_balances as b')
                ->join('product_batches as pb', 'pb.id', '=', 'b.batch_id')
                ->whereIn('b.store_id', $storeIds)
                ->where('pb.status', 'RELEASED')
                ->whereDate('pb.expiry_date', '<=', now()->addDays(90)->toDateString())
                ->sum('b.qty_on_hand');

            $lowStockCount = DB::table('products as p')
                ->where('p.organisation_id', $organisationId)
                ->where('p.is_active', true)
                ->where('p.reorder_point', '>', 0)
                ->whereRaw('(SELECT COALESCE(SUM(b.qty_on_hand - b.qty_reserved), 0) FROM stock_balances b WHERE b.product_id = p.id AND b.store_id IN (SELECT id FROM stores WHERE branch_id = ?)) < p.reorder_point', [$branchId])
                ->count();

            $inventory = [
                'pending_qc_batches' => (clone $batchesInBranch)->where('status', 'PENDING_QC')->count(),
                'pending_qc_qty' => number_format((float) $pendingQcQty, 4, '.', ''),
                'quarantined_batches' => (clone $batchesInBranch)->where('status', 'QUARANTINED')->count(),
                'expiring_90d_batches' => (clone $batchesInBranch)->where('status', 'RELEASED')->whereDate('expiry_date', '<=', now()->addDays(90)->toDateString())->count(),
                'expiring_90d_qty' => number_format((float) $expiring90dQty, 4, '.', ''),
                'expired_batches_on_hand' => (clone $batchesInBranch)->where('status', 'EXPIRED')->count(),
                'low_stock_count' => $lowStockCount,
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

        // Compliance and people signals from the modules that own them; each
        // key only appears for someone who may open that screen.
        $quality = [];
        if ($user->can('coldchain.record') || $user->can('coldchain.review')) {
            $quality['cold_chain_open_excursions'] = DB::table('cold_chain_excursions')->where('branch_id', $branchId)->whereIn('status', ['OPEN', 'UNDER_REVIEW'])->count();
        }
        if ($user->can('adr.report') || $user->can('adr.manage')) {
            $quality['adr_draft_reports'] = DB::table('adr_reports')->where('branch_id', $branchId)->where('status', 'DRAFT')->count();
        }
        if ($user->can('licence.view')) {
            $quality['licences_expired'] = DB::table('licences')->where('organisation_id', $organisationId)->where('is_active', true)->whereDate('expiry_date', '<', $today)->count();
            $quality['licences_expiring_60d'] = DB::table('licences')->where('organisation_id', $organisationId)->where('is_active', true)
                ->whereBetween('expiry_date', [$today, now()->addDays(60)->toDateString()])->count();
        }
        // Everyone sees the SOPs they have not read yet, so this block is never empty.
        $quality['documents_to_acknowledge'] = DB::table('controlled_documents as d')
            ->join('document_versions as v', 'v.id', '=', 'd.current_version_id')
            ->where('d.organisation_id', $organisationId)->where('d.status', 'ACTIVE')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('document_acknowledgements as a')
                ->whereColumn('a.document_version_id', 'v.id')->where('a.user_id', $user->id))
            ->count();

        $people = [];
        if ($user->can('leave.approve')) {
            $people['leave_pending'] = DB::table('leave_requests')->where('organisation_id', $organisationId)->where('status', 'PENDING')->count();
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

        $arSummary = null;
        if ($user->can('finance.ar.view')) {
            $customerIds = DB::table('customers')->where('organisation_id', $organisationId)->pluck('id');
            $arTotal = DB::table('customer_credits')->whereIn('customer_id', $customerIds)->sum('current_balance');
            $arCustomersCount = DB::table('customer_credits')->whereIn('customer_id', $customerIds)->where('current_balance', '>', 0)->count();

            $arSummary = [
                'total' => number_format((float) $arTotal, 4, '.', ''),
                'customers_count' => $arCustomersCount,
                'd90_plus' => '0.0000',
            ];
        }

        return response()->json([
            'as_of' => now()->toIso8601String(),
            'sales_today' => $sales,
            'approvals' => $approvals === [] ? null : $approvals,
            'inventory' => $inventory,
            'procurement' => $procurement,
            'compliance' => $compliance,
            'quality' => $quality,
            'people' => $people === [] ? null : $people,
            'finance' => $finance,
            'ar' => $arSummary,
        ]);
    }
}
