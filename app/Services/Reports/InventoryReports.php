<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryReports
{
    use FormatsReports;

    /**
     * @return array<string, mixed>
     */
    public function valuation(ReportContext $ctx): array
    {
        $rows = DB::table('stock_balances as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('stores as s', 's.id', '=', 'b.store_id')
            ->whereIn('b.store_id', $ctx->storeIds)
            ->when($ctx->filter('store_id'), fn ($q, $v) => $q->where('b.store_id', $v))
            ->groupBy('b.product_id', 'p.code', 'p.name', 'p.default_price', 'b.store_id', 's.code')
            ->havingRaw('SUM(b.qty_on_hand) <> 0')
            ->selectRaw('p.code, p.name, s.code as store, SUM(b.qty_on_hand) as qty, SUM(b.qty_on_hand * b.wac) as value_cost, SUM(b.qty_on_hand) * p.default_price as value_retail')
            ->orderBy('p.name')->orderBy('s.code')->get()
            ->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'store' => $r->store, 'qty_base' => $this->d($r->qty), 'value_at_cost' => $this->d($r->value_cost), 'value_at_retail' => $this->d($r->value_retail),
                'wac' => bccomp($this->d($r->qty), '0', 4) !== 0 ? bcdiv($this->d($r->value_cost), $this->d($r->qty), 4) : '0.0000'])->all();

        return $this->result([
            $this->col('code', 'Code'), $this->col('name', 'Product'), $this->col('store', 'Store'), $this->col('qty_base', 'On hand (base)', 'qty'),
            $this->col('wac', 'WAC', 'money'), $this->col('value_at_cost', 'Value at cost', 'money'), $this->col('value_at_retail', 'Value at retail', 'money'),
        ], $rows, ['qty_base', 'value_at_cost', 'value_at_retail']);
    }

    /**
     * @return array<string, mixed>
     */
    public function expiryRisk(ReportContext $ctx): array
    {
        $today = now()->toDateString();
        $rows = DB::table('stock_balances as b')
            ->join('product_batches as pb', 'pb.id', '=', 'b.batch_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('stores as s', 's.id', '=', 'b.store_id')
            ->whereIn('b.store_id', $ctx->storeIds)->where('b.qty_on_hand', '>', 0)
            ->orderBy('pb.expiry_date')
            ->get(['p.code', 'p.name', 's.code as store', 'pb.batch_number', 'pb.expiry_date', 'pb.status', 'b.qty_on_hand', 'pb.landed_unit_cost'])
            ->map(function ($r) use ($today) {
                $days = (int) now()->startOfDay()->diffInDays(Carbon::parse($r->expiry_date), false);
                $tier = match (true) {
                    $r->expiry_date < $today => 'EXPIRED',
                    $days <= 30 => 'CRITICAL_30',
                    $days <= 90 => 'WARNING_90',
                    $days <= 180 => 'WATCH_180',
                    default => 'OK',
                };

                return ['tier' => $tier, 'code' => $r->code, 'name' => $r->name, 'store' => $r->store, 'batch' => $r->batch_number, 'expiry_date' => $r->expiry_date, 'days_to_expiry' => $days,
                    'status' => $r->status, 'qty_base' => $this->d($r->qty_on_hand), 'value_at_cost' => bcmul($this->d($r->qty_on_hand), $this->d($r->landed_unit_cost), 4)];
            })->filter(fn ($row) => $row['tier'] !== 'OK')->values()->all();

        $out = $this->result([
            $this->col('tier', 'Tier'), $this->col('code', 'Code'), $this->col('name', 'Product'), $this->col('store', 'Store'), $this->col('batch', 'Batch'),
            $this->col('expiry_date', 'Expiry', 'date'), $this->col('days_to_expiry', 'Days', 'int'), $this->col('status', 'Status'), $this->col('qty_base', 'Qty (base)', 'qty'), $this->col('value_at_cost', 'Value at cost', 'money'),
        ], $rows, ['qty_base', 'value_at_cost']);
        foreach (['EXPIRED', 'CRITICAL_30', 'WARNING_90', 'WATCH_180'] as $tier) {
            $out['totals']['value_'.strtolower($tier)] = array_reduce(array_filter($rows, fn ($r) => $r['tier'] === $tier), fn ($c, $r) => bcadd($c, $r['value_at_cost'], 4), '0.0000');
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function movementClasses(ReportContext $ctx): array
    {
        $deadDays = (int) ($ctx->filter('dead_days') ?? 90);
        $sold = DB::table('sales as s')->join('sale_lines as l', 'l.sale_id', '=', 's.id')
            ->where('s.branch_id', $ctx->branchId)->where('s.status', 'POSTED')
            ->whereBetween('s.posted_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->groupBy('l.product_id')->selectRaw('l.product_id, SUM(l.qty_base) as qty')->pluck('qty', 'product_id');
        $lastSale = DB::table('sales as s')->join('sale_lines as l', 'l.sale_id', '=', 's.id')
            ->where('s.branch_id', $ctx->branchId)->where('s.status', 'POSTED')
            ->groupBy('l.product_id')->selectRaw('l.product_id, MAX(s.posted_at) as last_sold')->pluck('last_sold', 'product_id');

        $rows = DB::table('stock_balances as b')->join('products as p', 'p.id', '=', 'b.product_id')
            ->whereIn('b.store_id', $ctx->storeIds)
            ->groupBy('b.product_id', 'p.code', 'p.name')->havingRaw('SUM(b.qty_on_hand) > 0')
            ->selectRaw('b.product_id, p.code, p.name, SUM(b.qty_on_hand) as qty, SUM(b.qty_on_hand * b.wac) as value')
            ->orderBy('p.name')->get()
            ->map(function ($r) use ($sold, $lastSale, $ctx, $deadDays) {
                $soldQty = $this->d($sold[$r->product_id] ?? 0);
                $perDay = bcdiv($soldQty, (string) $ctx->days(), 6);
                $daysOfStock = bccomp($perDay, '0', 6) > 0 ? (int) round((float) bcdiv($this->d($r->qty), $perDay, 2)) : null;
                $last = $lastSale[$r->product_id] ?? null;
                $daysSince = $last ? (int) Carbon::parse($last)->diffInDays(now()) : null;
                $class = match (true) {
                    $daysSince === null || $daysSince > $deadDays => 'DEAD',
                    $daysOfStock !== null && $daysOfStock <= 30 => 'FAST',
                    default => 'SLOW',
                };

                return ['class' => $class, 'code' => $r->code, 'name' => $r->name, 'on_hand' => $this->d($r->qty), 'value_at_cost' => $this->d($r->value), 'sold_in_period' => $soldQty,
                    'days_of_stock' => $daysOfStock, 'last_sold' => $last, 'days_since_last_sale' => $daysSince];
            })->all();

        $out = $this->result([
            $this->col('class', 'Class'), $this->col('code', 'Code'), $this->col('name', 'Product'), $this->col('on_hand', 'On hand', 'qty'), $this->col('value_at_cost', 'Value at cost', 'money'),
            $this->col('sold_in_period', 'Sold in period', 'qty'), $this->col('days_of_stock', 'Days of stock', 'int'), $this->col('last_sold', 'Last sold', 'datetime'), $this->col('days_since_last_sale', 'Days since sale', 'int'),
        ], $rows, ['on_hand', 'value_at_cost', 'sold_in_period']);
        $out['totals']['dead_stock_value'] = array_reduce(array_filter($rows, fn ($r) => $r['class'] === 'DEAD'), fn ($c, $r) => bcadd($c, $r['value_at_cost'], 4), '0.0000');

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function turnover(ReportContext $ctx): array
    {
        $cogs = $this->d(DB::table('sales')->where('branch_id', $ctx->branchId)->where('status', 'POSTED')->whereBetween('posted_at', [$ctx->fromDateTime(), $ctx->toDateTime()])->sum('cost_total'));
        $inventory = $this->d(DB::table('stock_balances')->whereIn('store_id', $ctx->storeIds)->selectRaw('SUM(qty_on_hand * wac) as v')->value('v'));
        $days = $ctx->days();
        $annualised = bcdiv(bcmul($cogs, '365', 4), (string) $days, 4);
        $turnover = bccomp($inventory, '0', 4) > 0 ? bcdiv($annualised, $inventory, 2) : null;
        $daysOfStock = $turnover && bccomp($turnover, '0', 2) > 0 ? (int) round(365 / (float) $turnover) : null;

        return $this->result([
            $this->col('period_days', 'Days', 'int'), $this->col('cogs', 'COGS in period', 'money'), $this->col('cogs_annualised', 'COGS annualised', 'money'),
            $this->col('inventory_value', 'Inventory value', 'money'), $this->col('turnover', 'Turnover (times/yr)', 'qty'), $this->col('days_of_stock', 'Days of stock', 'int'),
        ], [['period_days' => $days, 'cogs' => $cogs, 'cogs_annualised' => $annualised, 'inventory_value' => $inventory, 'turnover' => $turnover, 'days_of_stock' => $daysOfStock]]);
    }

    /**
     * @return array<string, mixed>
     */
    public function adjustments(ReportContext $ctx): array
    {
        $rows = DB::table('stock_adjustments as a')->join('users as u', 'u.id', '=', 'a.created_by')->join('stores as s', 's.id', '=', 'a.store_id')
            ->whereIn('a.store_id', $ctx->storeIds)->whereBetween('a.created_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->groupBy('a.reason_code', 'a.created_by', 'u.name', 'a.approval_status')
            ->selectRaw('a.reason_code, u.name as user, a.approval_status, COUNT(*) as n, SUM(a.total_value) as value')
            ->orderBy('a.reason_code')->get()
            ->map(fn ($r) => ['reason_code' => $r->reason_code, 'user' => $r->user, 'approval_status' => $r->approval_status, 'adjustments' => (int) $r->n, 'value' => $this->d($r->value)])->all();

        return $this->result([$this->col('reason_code', 'Reason'), $this->col('user', 'User'), $this->col('approval_status', 'Status'), $this->col('adjustments', 'Count', 'int'), $this->col('value', 'Value', 'money')], $rows, ['adjustments', 'value']);
    }

    /**
     * @return array<string, mixed>
     */
    public function countVariance(ReportContext $ctx): array
    {
        $rows = DB::table('stock_counts as c')->join('stores as s', 's.id', '=', 'c.store_id')
            ->leftJoin('stock_count_lines as l', 'l.stock_count_id', '=', 'c.id')
            ->whereIn('c.store_id', $ctx->storeIds)->whereIn('c.status', ['APPROVED', 'CLOSED'])
            ->whereBetween('c.approved_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->groupBy('c.id', 'c.doc_number', 's.code', 'c.approved_at', 'c.status')
            ->selectRaw('c.doc_number, s.code as store, c.approved_at, c.status, COUNT(l.id) as line_count, SUM(CASE WHEN l.variance_qty <> 0 THEN 1 ELSE 0 END) as variance_lines, SUM(CASE WHEN l.variance_value > 0 THEN l.variance_value ELSE 0 END) as gains, SUM(CASE WHEN l.variance_value < 0 THEN -l.variance_value ELSE 0 END) as losses')
            ->orderByDesc('c.approved_at')->get()
            ->map(fn ($r) => ['doc_number' => $r->doc_number, 'store' => $r->store, 'approved_at' => $r->approved_at, 'status' => $r->status, 'lines' => (int) $r->line_count, 'variance_lines' => (int) $r->variance_lines, 'gains' => $this->d($r->gains), 'losses' => $this->d($r->losses), 'net' => bcsub($this->d($r->gains), $this->d($r->losses), 4)])->all();

        return $this->result([$this->col('doc_number', 'Count'), $this->col('store', 'Store'), $this->col('approved_at', 'Approved', 'datetime'), $this->col('status', 'Status'), $this->col('lines', 'Lines', 'int'), $this->col('variance_lines', 'Variance lines', 'int'), $this->col('gains', 'Gains', 'money'), $this->col('losses', 'Losses', 'money'), $this->col('net', 'Net', 'money')], $rows, ['lines', 'variance_lines', 'gains', 'losses', 'net']);
    }

    /**
     * @return array<string, mixed>
     */
    public function inTransit(ReportContext $ctx): array
    {
        $rows = DB::table('stock_transfers as t')->join('stores as f', 'f.id', '=', 't.from_store_id')->join('stores as d', 'd.id', '=', 't.to_store_id')
            ->join('stock_transfer_lines as l', 'l.stock_transfer_id', '=', 't.id')
            ->where(fn ($q) => $q->whereIn('t.from_store_id', $ctx->storeIds)->orWhereIn('t.to_store_id', $ctx->storeIds))
            ->whereIn('t.status', ['DISPATCHED', 'DISCREPANCY'])
            ->groupBy('t.id', 't.doc_number', 'f.code', 'd.code', 't.status', 't.dispatched_at')
            ->selectRaw('t.doc_number, f.code as from_store, d.code as to_store, t.status, t.dispatched_at, COUNT(l.id) as line_count, SUM(l.qty_dispatched - COALESCE(l.qty_received, 0)) as qty_in_transit')
            ->orderBy('t.dispatched_at')->get()
            ->map(fn ($r) => ['doc_number' => $r->doc_number, 'from_store' => $r->from_store, 'to_store' => $r->to_store, 'status' => $r->status, 'dispatched_at' => $r->dispatched_at,
                'days_in_transit' => $r->dispatched_at ? (int) Carbon::parse($r->dispatched_at)->diffInDays(now()) : null, 'lines' => (int) $r->line_count, 'qty_in_transit' => $this->d($r->qty_in_transit)])->all();

        return $this->result([$this->col('doc_number', 'Transfer'), $this->col('from_store', 'From'), $this->col('to_store', 'To'), $this->col('status', 'Status'), $this->col('dispatched_at', 'Dispatched', 'datetime'), $this->col('days_in_transit', 'Days', 'int'), $this->col('lines', 'Lines', 'int'), $this->col('qty_in_transit', 'Qty in transit', 'qty')], $rows, ['lines', 'qty_in_transit']);
    }

    /**
     * @return array<string, mixed>
     */
    public function negativeStock(ReportContext $ctx): array
    {
        $rows = DB::table('stock_balances as b')->join('products as p', 'p.id', '=', 'b.product_id')->join('product_batches as pb', 'pb.id', '=', 'b.batch_id')->join('stores as s', 's.id', '=', 'b.store_id')
            ->whereIn('b.store_id', $ctx->storeIds)->where('b.qty_on_hand', '<', 0)
            ->get(['p.code', 'p.name', 'pb.batch_number', 's.code as store', 'b.qty_on_hand', 'b.last_movement_at'])
            ->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'batch' => $r->batch_number, 'store' => $r->store, 'qty_on_hand' => $this->d($r->qty_on_hand), 'last_movement_at' => $r->last_movement_at])->all();

        return $this->result([$this->col('code', 'Code'), $this->col('name', 'Product'), $this->col('batch', 'Batch'), $this->col('store', 'Store'), $this->col('qty_on_hand', 'On hand', 'qty'), $this->col('last_movement_at', 'Last movement', 'datetime')], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function fefoCompliance(ReportContext $ctx): array
    {
        $base = DB::table('sale_line_batch_allocations as a')->join('sale_lines as l', 'l.id', '=', 'a.sale_line_id')->join('sales as s', 's.id', '=', 'l.sale_id')
            ->where('s.branch_id', $ctx->branchId)->where('s.status', 'POSTED')->whereBetween('s.posted_at', [$ctx->fromDateTime(), $ctx->toDateTime()]);
        $total = (clone $base)->count();
        $overrides = (clone $base)->where('a.fefo_overridden', true)
            ->join('products as p', 'p.id', '=', 'l.product_id')->join('product_batches as pb', 'pb.id', '=', 'a.batch_id')->join('users as u', 'u.id', '=', 's.user_id')
            ->orderByDesc('s.posted_at')
            ->get(['s.doc_number', 's.posted_at', 'u.name as user', 'p.code', 'p.name', 'pb.batch_number', 'pb.expiry_date', 'a.qty_base', 'a.override_reason'])
            ->map(fn ($r) => ['doc_number' => $r->doc_number, 'posted_at' => $r->posted_at, 'user' => $r->user, 'code' => $r->code, 'name' => $r->name, 'batch' => $r->batch_number, 'expiry_date' => $r->expiry_date, 'qty_base' => $this->d($r->qty_base), 'reason' => $r->override_reason])->all();

        $out = $this->result([$this->col('doc_number', 'Sale'), $this->col('posted_at', 'Posted', 'datetime'), $this->col('user', 'User'), $this->col('code', 'Code'), $this->col('name', 'Product'), $this->col('batch', 'Batch chosen'), $this->col('expiry_date', 'Expiry', 'date'), $this->col('qty_base', 'Qty', 'qty'), $this->col('reason', 'Reason')], $overrides, ['qty_base']);
        $out['totals']['allocations'] = $total;
        $out['totals']['overrides'] = count($overrides);
        $out['totals']['compliance_pct'] = $total > 0 ? number_format(($total - count($overrides)) / $total * 100, 2, '.', '') : null;

        return $out;
    }
}
