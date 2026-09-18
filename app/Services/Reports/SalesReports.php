<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SalesReports
{
    use FormatsReports;

    private function postedSales(ReportContext $ctx): Builder
    {
        return DB::table('sales as s')
            ->where('s.branch_id', $ctx->branchId)->where('s.status', 'POSTED')
            ->whereBetween('s.posted_at', [$ctx->fromDateTime(), $ctx->toDateTime()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function dailySummary(ReportContext $ctx): array
    {
        $rows = $this->postedSales($ctx)
            ->groupBy(DB::raw('DATE(s.posted_at)'), 's.sale_mode')
            ->selectRaw('DATE(s.posted_at) as day, s.sale_mode, COUNT(*) as n, SUM(s.subtotal) as subtotal, SUM(s.discount_total) as discount, SUM(s.tax_total) as tax, SUM(s.grand_total) as gross, SUM(s.cost_total) as cost')
            ->orderBy('day')->get();

        $days = [];
        foreach ($rows as $r) {
            $d = $days[$r->day] ?? ['day' => $r->day, 'retail_count' => 0, 'retail_net' => '0.0000', 'wholesale_count' => 0, 'wholesale_net' => '0.0000', 'dispensing_count' => 0, 'dispensing_net' => '0.0000',
                'transactions' => 0, 'net_sales' => '0.0000', 'discounts' => '0.0000', 'tax' => '0.0000', 'gross_total' => '0.0000', 'cogs' => '0.0000', 'gross_profit' => '0.0000', 'margin_pct' => null];
            $net = bcsub($this->d($r->subtotal), $this->d($r->discount), 4);
            $mode = strtolower($r->sale_mode);
            $d["{$mode}_count"] = ($d["{$mode}_count"] ?? 0) + (int) $r->n;
            $d["{$mode}_net"] = bcadd($d["{$mode}_net"] ?? '0.0000', $net, 4);
            $d['transactions'] += (int) $r->n;
            $d['net_sales'] = bcadd($d['net_sales'], $net, 4);
            $d['discounts'] = bcadd($d['discounts'], $this->d($r->discount), 4);
            $d['tax'] = bcadd($d['tax'], $this->d($r->tax), 4);
            $d['gross_total'] = bcadd($d['gross_total'], $this->d($r->gross), 4);
            $d['cogs'] = bcadd($d['cogs'], $this->d($r->cost), 4);
            $d['gross_profit'] = bcsub($d['net_sales'], $d['cogs'], 4);
            $d['margin_pct'] = $this->pct($d['gross_profit'], $d['net_sales']);
            $days[$r->day] = $d;
        }

        return $this->result([
            $this->col('day', 'Day', 'date'), $this->col('transactions', 'Txns', 'int'),
            $this->col('retail_count', 'Retail txns', 'int'), $this->col('retail_net', 'Retail net', 'money'),
            $this->col('wholesale_count', 'Wholesale txns', 'int'), $this->col('wholesale_net', 'Wholesale net', 'money'),
            $this->col('dispensing_count', 'Dispensing txns', 'int'), $this->col('dispensing_net', 'Dispensing net', 'money'),
            $this->col('net_sales', 'Net sales', 'money'), $this->col('discounts', 'Discounts', 'money'), $this->col('tax', 'VAT', 'money'),
            $this->col('gross_total', 'Gross total', 'money'), $this->col('cogs', 'COGS', 'money'), $this->col('gross_profit', 'Gross profit', 'money'), $this->col('margin_pct', 'Margin %', 'pct'),
        ], array_values($days), ['transactions', 'retail_net', 'wholesale_net', 'dispensing_net', 'net_sales', 'discounts', 'tax', 'gross_total', 'cogs', 'gross_profit']);
    }

    /**
     * @return array<string, mixed>
     */
    public function byCustomer(ReportContext $ctx): array
    {
        $rows = $this->postedSales($ctx)
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->groupBy('s.customer_id', 'c.code', 'c.name', 'c.customer_type')
            ->selectRaw('s.customer_id, c.code, c.name, c.customer_type, COUNT(*) as n, SUM(s.subtotal - s.discount_total) as net, SUM(s.discount_total) as discount, SUM(s.tax_total) as tax, SUM(s.cost_total) as cost')
            ->orderByDesc('net')->get()
            ->map(fn ($r) => $this->withMargin([
                'customer_id' => $r->customer_id, 'code' => $r->code ?? '—', 'name' => $r->name ?? 'Walk-in', 'customer_type' => $r->customer_type ?? 'WALK_IN',
                'transactions' => (int) $r->n, 'net_sales' => $this->d($r->net), 'discounts' => $this->d($r->discount), 'tax' => $this->d($r->tax), 'cogs' => $this->d($r->cost),
            ]))->all();

        return $this->result([
            $this->col('code', 'Code'), $this->col('name', 'Customer'), $this->col('customer_type', 'Type'), $this->col('transactions', 'Txns', 'int'),
            $this->col('net_sales', 'Net sales', 'money'), $this->col('discounts', 'Discounts', 'money'), $this->col('tax', 'VAT', 'money'),
            $this->col('cogs', 'COGS', 'money'), $this->col('gross_profit', 'Gross profit', 'money'), $this->col('margin_pct', 'Margin %', 'pct'),
        ], $rows, ['transactions', 'net_sales', 'discounts', 'tax', 'cogs', 'gross_profit']);
    }

    /**
     * @return array<string, mixed>
     */
    public function byProduct(ReportContext $ctx): array
    {
        $rows = $this->saleLines($ctx)
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'p.category_id')
            ->when($ctx->filter('category_id'), fn ($q, $v) => $q->where('p.category_id', $v))
            ->groupBy('l.product_id', 'p.code', 'p.name', 'pc.name')
            ->selectRaw('l.product_id, p.code, p.name, pc.name as category, SUM(CASE WHEN l.is_bonus = 0 THEN l.qty_base ELSE 0 END) as qty_sold, SUM(CASE WHEN l.is_bonus = 1 THEN l.qty_base ELSE 0 END) as qty_bonus, SUM(l.line_total - l.tax_amount) as net, SUM(l.discount_amount) as discount, SUM(l.line_cost) as cost')
            ->orderByDesc('net')->get()
            ->map(fn ($r) => $this->withMargin([
                'product_id' => $r->product_id, 'code' => $r->code, 'name' => $r->name, 'category' => $r->category,
                'qty_sold' => $this->d($r->qty_sold), 'qty_bonus' => $this->d($r->qty_bonus), 'net_sales' => $this->d($r->net), 'discounts' => $this->d($r->discount), 'cogs' => $this->d($r->cost),
            ]))->all();

        return $this->result([
            $this->col('code', 'Code'), $this->col('name', 'Product'), $this->col('category', 'Category'),
            $this->col('qty_sold', 'Qty sold (base)', 'qty'), $this->col('qty_bonus', 'Bonus qty', 'qty'),
            $this->col('net_sales', 'Net sales', 'money'), $this->col('discounts', 'Discounts', 'money'), $this->col('cogs', 'COGS', 'money'),
            $this->col('gross_profit', 'Gross profit', 'money'), $this->col('margin_pct', 'Margin %', 'pct'),
        ], $rows, ['qty_sold', 'qty_bonus', 'net_sales', 'discounts', 'cogs', 'gross_profit']);
    }

    /**
     * @return array<string, mixed>
     */
    public function byCashier(ReportContext $ctx): array
    {
        $rows = $this->postedSales($ctx)
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->groupBy('s.user_id', 'u.name')
            ->selectRaw('s.user_id, u.name, COUNT(*) as n, SUM(s.subtotal - s.discount_total) as net, SUM(s.discount_total) as discount, SUM(s.grand_total) as gross, SUM(CASE WHEN s.sale_mode = "RETAIL" THEN 1 ELSE 0 END) as retail_n, SUM(CASE WHEN s.sale_mode = "WHOLESALE" THEN 1 ELSE 0 END) as wholesale_n')
            ->orderByDesc('net')->get()
            ->map(fn ($r) => [
                'user_id' => $r->user_id, 'name' => $r->name, 'transactions' => (int) $r->n, 'retail_txns' => (int) $r->retail_n, 'wholesale_txns' => (int) $r->wholesale_n,
                'net_sales' => $this->d($r->net), 'discounts' => $this->d($r->discount), 'gross_total' => $this->d($r->gross),
                'avg_ticket' => (int) $r->n > 0 ? bcdiv($this->d($r->gross), (string) $r->n, 4) : '0.0000',
            ])->all();

        return $this->result([
            $this->col('name', 'User'), $this->col('transactions', 'Txns', 'int'), $this->col('retail_txns', 'Retail', 'int'), $this->col('wholesale_txns', 'Wholesale', 'int'),
            $this->col('net_sales', 'Net sales', 'money'), $this->col('discounts', 'Discounts', 'money'), $this->col('gross_total', 'Gross total', 'money'), $this->col('avg_ticket', 'Avg ticket', 'money'),
        ], $rows, ['transactions', 'retail_txns', 'wholesale_txns', 'net_sales', 'discounts', 'gross_total']);
    }

    /**
     * @return array<string, mixed>
     */
    public function hourlyProfile(ReportContext $ctx): array
    {
        $rows = $this->postedSales($ctx)
            ->groupBy(DB::raw('HOUR(s.posted_at)'))
            ->selectRaw('HOUR(s.posted_at) as hour, COUNT(*) as n, SUM(s.subtotal - s.discount_total) as net')
            ->orderBy('hour')->get()
            ->map(fn ($r) => ['hour' => sprintf('%02d:00', $r->hour), 'transactions' => (int) $r->n, 'net_sales' => $this->d($r->net)])->all();

        return $this->result([$this->col('hour', 'Hour'), $this->col('transactions', 'Txns', 'int'), $this->col('net_sales', 'Net sales', 'money')], $rows, ['transactions', 'net_sales']);
    }

    /**
     * @return array<string, mixed>
     */
    public function discountAnalysis(ReportContext $ctx): array
    {
        $rows = $this->saleLines($ctx)
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->where('l.discount_amount', '>', 0)
            ->groupBy('s.user_id', 'u.name', 'l.product_id', 'p.code', 'p.name')
            ->selectRaw('u.name as user, p.code, p.name as product, COUNT(*) as line_count, SUM(l.discount_amount) as discount, AVG(l.discount_pct) as avg_pct, MAX(l.discount_pct) as max_pct, SUM(CASE WHEN l.approved_by IS NOT NULL THEN 1 ELSE 0 END) as approved_lines, SUM(l.line_total - l.tax_amount) as net')
            ->orderByDesc('discount')->get()
            ->map(fn ($r) => [
                'user' => $r->user, 'code' => $r->code, 'product' => $r->product, 'lines' => (int) $r->line_count, 'discount' => $this->d($r->discount),
                'avg_pct' => number_format((float) $r->avg_pct, 2, '.', ''), 'max_pct' => number_format((float) $r->max_pct, 2, '.', ''),
                'approved_lines' => (int) $r->approved_lines, 'net_after_discount' => $this->d($r->net),
            ])->all();

        return $this->result([
            $this->col('user', 'User'), $this->col('code', 'Code'), $this->col('product', 'Product'), $this->col('lines', 'Lines', 'int'),
            $this->col('discount', 'Discount given', 'money'), $this->col('avg_pct', 'Avg %', 'pct'), $this->col('max_pct', 'Max %', 'pct'),
            $this->col('approved_lines', 'Approved lines', 'int'), $this->col('net_after_discount', 'Net after discount', 'money'),
        ], $rows, ['lines', 'discount', 'approved_lines', 'net_after_discount']);
    }

    /**
     * @return array<string, mixed>
     */
    public function bonusGoodsCost(ReportContext $ctx): array
    {
        $rows = $this->saleLines($ctx)
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->where('l.is_bonus', true)
            ->groupBy('l.product_id', 'p.code', 'p.name')
            ->selectRaw('p.code, p.name, COUNT(DISTINCT s.id) as sales, SUM(l.qty_base) as qty, SUM(l.line_cost) as cost')
            ->orderByDesc('cost')->get()
            ->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'sales' => (int) $r->sales, 'qty_base' => $this->d($r->qty), 'cost' => $this->d($r->cost)])->all();

        return $this->result([$this->col('code', 'Code'), $this->col('name', 'Product'), $this->col('sales', 'Sales', 'int'), $this->col('qty_base', 'Free qty (base)', 'qty'), $this->col('cost', 'Cost of free goods', 'money')], $rows, ['sales', 'qty_base', 'cost']);
    }

    /**
     * @return array<string, mixed>
     */
    public function quotationConversion(ReportContext $ctx): array
    {
        $rows = DB::table('quotations')->where('branch_id', $ctx->branchId)
            ->whereBetween('created_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->groupBy('status')->selectRaw('status, COUNT(*) as n, SUM(grand_total) as value')->get();

        $total = (int) $rows->sum('n');
        $converted = (int) ($rows->firstWhere('status', 'CONVERTED')->n ?? 0);
        $data = $rows->map(fn ($r) => ['status' => $r->status, 'quotations' => (int) $r->n, 'value' => $this->d($r->value), 'share_pct' => $total > 0 ? number_format($r->n / $total * 100, 2, '.', '') : '0.00'])->all();

        $out = $this->result([$this->col('status', 'Status'), $this->col('quotations', 'Quotations', 'int'), $this->col('value', 'Value', 'money'), $this->col('share_pct', 'Share %', 'pct')], $data, ['quotations', 'value']);
        $out['totals']['conversion_rate_pct'] = $total > 0 ? number_format($converted / $total * 100, 2, '.', '') : null;

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function voidLog(ReportContext $ctx): array
    {
        $rows = DB::table('sales as s')->join('users as u', 'u.id', '=', 's.voided_by')
            ->where('s.branch_id', $ctx->branchId)->where('s.status', 'VOIDED')
            ->whereBetween('s.voided_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->orderByDesc('s.voided_at')
            ->get(['s.doc_number', 's.sale_mode', 's.grand_total', 's.posted_at', 's.voided_at', 'u.name as voided_by', 's.void_reason'])
            ->map(fn ($r) => ['doc_number' => $r->doc_number, 'sale_mode' => $r->sale_mode, 'grand_total' => $this->d($r->grand_total), 'posted_at' => $r->posted_at, 'voided_at' => $r->voided_at, 'voided_by' => $r->voided_by, 'reason' => $r->void_reason])->all();

        return $this->result([$this->col('doc_number', 'Sale'), $this->col('sale_mode', 'Mode'), $this->col('grand_total', 'Value', 'money'), $this->col('posted_at', 'Posted', 'datetime'), $this->col('voided_at', 'Voided', 'datetime'), $this->col('voided_by', 'By'), $this->col('reason', 'Reason')], $rows, ['grand_total']);
    }

    private function saleLines(ReportContext $ctx): Builder
    {
        return $this->postedSales($ctx)->join('sale_lines as l', 'l.sale_id', '=', 's.id');
    }
}
