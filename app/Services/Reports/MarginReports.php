<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class MarginReports
{
    use FormatsReports;

    private function saleLines(ReportContext $ctx): Builder
    {
        return DB::table('sales as s')->join('sale_lines as l', 'l.sale_id', '=', 's.id')
            ->where('s.branch_id', $ctx->branchId)->where('s.status', 'POSTED')
            ->whereBetween('s.posted_at', [$ctx->fromDateTime(), $ctx->toDateTime()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function byProduct(ReportContext $ctx): array
    {
        $rows = $this->saleLines($ctx)->join('products as p', 'p.id', '=', 'l.product_id')
            ->where('l.is_bonus', false)
            ->groupBy('l.product_id', 'p.code', 'p.name')
            ->selectRaw('p.code, p.name, SUM(l.qty_base) as qty, SUM(l.line_total - l.tax_amount) as net, SUM(l.line_cost) as cost')
            ->orderByDesc('net')->get()
            ->map(function ($r) {
                $row = $this->withMargin(['code' => $r->code, 'name' => $r->name, 'qty_base' => $this->d($r->qty), 'net_sales' => $this->d($r->net), 'cogs' => $this->d($r->cost)]);
                $row['markup_pct'] = $this->pct($row['gross_profit'], $row['cogs']);

                return $row;
            })->all();

        return $this->result([
            $this->col('code', 'Code'), $this->col('name', 'Product'), $this->col('qty_base', 'Qty (base)', 'qty'), $this->col('net_sales', 'Revenue', 'money'),
            $this->col('cogs', 'COGS (batch cost)', 'money'), $this->col('gross_profit', 'Gross profit', 'money'), $this->col('margin_pct', 'Margin %', 'pct'), $this->col('markup_pct', 'Markup %', 'pct'),
        ], $rows, ['qty_base', 'net_sales', 'cogs', 'gross_profit']);
    }

    /**
     * @return array<string, mixed>
     */
    public function byCustomer(ReportContext $ctx): array
    {
        return (new SalesReports)->byCustomer($ctx);
    }

    /**
     * @return array<string, mixed>
     */
    public function belowFloor(ReportContext $ctx): array
    {
        $rows = $this->saleLines($ctx)
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->join('product_discount_policies as d', 'd.product_id', '=', 'l.product_id')
            ->leftJoin('users as a', 'a.id', '=', 'l.approved_by')
            ->where('l.is_bonus', false)->where('l.line_total', '>', 0)
            ->whereRaw('((l.line_total - l.tax_amount) - l.line_cost) / (l.line_total - l.tax_amount) * 100 < d.min_margin_pct')
            ->orderByDesc('s.posted_at')
            ->get(['s.doc_number', 's.posted_at', 's.sale_mode', 'p.code', 'p.name', 'l.qty_base', 'l.unit_price', 'l.discount_pct', 'l.line_total', 'l.tax_amount', 'l.line_cost', 'd.min_margin_pct', 'a.name as approved_by'])
            ->map(function ($r) {
                $net = bcsub($this->d($r->line_total), $this->d($r->tax_amount), 4);
                $gp = bcsub($net, $this->d($r->line_cost), 4);

                return ['doc_number' => $r->doc_number, 'posted_at' => $r->posted_at, 'sale_mode' => $r->sale_mode, 'code' => $r->code, 'name' => $r->name,
                    'qty_base' => $this->d($r->qty_base), 'discount_pct' => $this->d($r->discount_pct), 'net' => $net, 'cost' => $this->d($r->line_cost),
                    'margin_pct' => $this->pct($gp, $net), 'floor_pct' => number_format((float) $r->min_margin_pct, 2, '.', ''), 'approved_by' => $r->approved_by];
            })->all();

        return $this->result([
            $this->col('doc_number', 'Sale'), $this->col('posted_at', 'Posted', 'datetime'), $this->col('sale_mode', 'Mode'), $this->col('code', 'Code'), $this->col('name', 'Product'),
            $this->col('qty_base', 'Qty', 'qty'), $this->col('discount_pct', 'Discount %', 'pct'), $this->col('net', 'Net', 'money'), $this->col('cost', 'Cost', 'money'),
            $this->col('margin_pct', 'Margin %', 'pct'), $this->col('floor_pct', 'Floor %', 'pct'), $this->col('approved_by', 'Approved by'),
        ], $rows, ['net', 'cost']);
    }

    /**
     * @return array<string, mixed>
     */
    public function effectiveAfterBonus(ReportContext $ctx): array
    {
        $rows = $this->saleLines($ctx)->join('products as p', 'p.id', '=', 'l.product_id')
            ->groupBy('l.product_id', 'p.code', 'p.name')
            ->selectRaw('p.code, p.name, SUM(CASE WHEN l.is_bonus = 0 THEN l.line_total - l.tax_amount ELSE 0 END) as revenue, SUM(CASE WHEN l.is_bonus = 0 THEN l.line_cost ELSE 0 END) as cost_sold, SUM(CASE WHEN l.is_bonus = 1 THEN l.line_cost ELSE 0 END) as cost_bonus, SUM(CASE WHEN l.is_bonus = 1 THEN l.qty_base ELSE 0 END) as bonus_qty')
            ->havingRaw('SUM(CASE WHEN l.is_bonus = 1 THEN l.qty_base ELSE 0 END) > 0')
            ->orderByDesc('revenue')->get()
            ->map(function ($r) {
                $revenue = $this->d($r->revenue);
                $costAll = bcadd($this->d($r->cost_sold), $this->d($r->cost_bonus), 4);

                return ['code' => $r->code, 'name' => $r->name, 'revenue' => $revenue, 'cost_sold' => $this->d($r->cost_sold), 'bonus_qty' => $this->d($r->bonus_qty), 'cost_bonus' => $this->d($r->cost_bonus),
                    'headline_margin_pct' => $this->pct(bcsub($revenue, $this->d($r->cost_sold), 4), $revenue),
                    'effective_margin_pct' => $this->pct(bcsub($revenue, $costAll, 4), $revenue)];
            })->all();

        return $this->result([
            $this->col('code', 'Code'), $this->col('name', 'Product'), $this->col('revenue', 'Revenue', 'money'), $this->col('cost_sold', 'Cost of sold units', 'money'),
            $this->col('bonus_qty', 'Free qty', 'qty'), $this->col('cost_bonus', 'Cost of free units', 'money'), $this->col('headline_margin_pct', 'Headline margin %', 'pct'), $this->col('effective_margin_pct', 'Effective margin %', 'pct'),
        ], $rows, ['revenue', 'cost_sold', 'bonus_qty', 'cost_bonus']);
    }

    /**
     * @return array<string, mixed>
     */
    public function wacHistory(ReportContext $ctx): array
    {
        $rows = DB::table('stock_ledgers as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->join('product_batches as b', 'b.id', '=', 'l.batch_id')
            ->join('stores as st', 'st.id', '=', 'l.store_id')
            ->whereIn('l.store_id', $ctx->storeIds)->where('l.txn_type', 'GRN_RECEIPT')
            ->whereBetween('l.txn_datetime', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->when($ctx->filter('product_id'), fn ($q, $v) => $q->where('l.product_id', $v))
            ->orderBy('p.name')->orderBy('l.txn_datetime')
            ->get(['l.txn_datetime', 'p.code', 'p.name', 'b.batch_number', 'st.code as store', 'l.qty_base', 'l.unit_cost', 'l.product_id', 'l.store_id'])
            ->map(fn ($r) => [
                'date' => $r->txn_datetime, 'code' => $r->code, 'name' => $r->name, 'batch' => $r->batch_number, 'store' => $r->store,
                'qty_base' => $this->d($r->qty_base), 'landed_unit_cost' => $this->d($r->unit_cost),
                'current_wac' => $this->d(DB::table('stock_balances')->where('product_id', $r->product_id)->where('store_id', $r->store_id)->where('qty_on_hand', '>', 0)->selectRaw('SUM(qty_on_hand * wac) / NULLIF(SUM(qty_on_hand), 0) as wac')->value('wac')),
            ])->all();

        return $this->result([
            $this->col('date', 'Received', 'datetime'), $this->col('code', 'Code'), $this->col('name', 'Product'), $this->col('batch', 'Batch'), $this->col('store', 'Store'),
            $this->col('qty_base', 'Qty (base)', 'qty'), $this->col('landed_unit_cost', 'Landed unit cost', 'money'), $this->col('current_wac', 'Current WAC', 'money'),
        ], $rows, ['qty_base']);
    }
}
