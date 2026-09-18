<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

class ManagementReports
{
    use FormatsReports;

    /**
     * @return array<string, mixed>
     */
    public function branchComparison(ReportContext $ctx): array
    {
        $rows = DB::table('branches as b')
            ->leftJoin('sales as s', fn ($j) => $j->on('s.branch_id', '=', 'b.id')->where('s.status', 'POSTED')->whereBetween('s.posted_at', [$ctx->fromDateTime(), $ctx->toDateTime()]))
            ->where('b.organisation_id', $ctx->organisationId)
            ->groupBy('b.id', 'b.code', 'b.name')
            ->selectRaw('b.code, b.name, COUNT(s.id) as n, COALESCE(SUM(s.subtotal - s.discount_total), 0) as net, COALESCE(SUM(s.cost_total), 0) as cost, COALESCE(SUM(s.discount_total), 0) as discount')
            ->orderBy('b.code')->get()
            ->map(fn ($r) => $this->withMargin(['code' => $r->code, 'name' => $r->name, 'transactions' => (int) $r->n, 'net_sales' => $this->d($r->net), 'discounts' => $this->d($r->discount), 'cogs' => $this->d($r->cost)]))->all();

        return $this->result([$this->col('code', 'Branch'), $this->col('name', 'Name'), $this->col('transactions', 'Txns', 'int'), $this->col('net_sales', 'Net sales', 'money'), $this->col('discounts', 'Discounts', 'money'), $this->col('cogs', 'COGS', 'money'), $this->col('gross_profit', 'Gross profit', 'money'), $this->col('margin_pct', 'Margin %', 'pct')], $rows, ['transactions', 'net_sales', 'discounts', 'cogs', 'gross_profit']);
    }

    /**
     * @return array<string, mixed>
     */
    public function abcAnalysis(ReportContext $ctx): array
    {
        $products = DB::table('sales as s')->join('sale_lines as l', 'l.sale_id', '=', 's.id')->join('products as p', 'p.id', '=', 'l.product_id')
            ->where('s.branch_id', $ctx->branchId)->where('s.status', 'POSTED')->whereBetween('s.posted_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->groupBy('l.product_id', 'p.code', 'p.name')
            ->selectRaw('p.code, p.name, SUM(l.line_total - l.tax_amount) as net, SUM(l.line_total - l.tax_amount - l.line_cost) as gp')
            ->orderByDesc('gp')->get();

        $totalGp = $products->reduce(fn ($c, $r) => bcadd($c, $this->d($r->gp), 4), '0.0000');
        $running = '0.0000';
        $rows = [];
        foreach ($products as $i => $r) {
            $gp = $this->d($r->gp);
            // A product belongs to the class of the cumulative share *before*
            // it is added, so the products that carry the first 80% are A.
            $before = $this->pct($running, $totalGp);
            $running = bcadd($running, $gp, 4);
            $cum = $this->pct($running, $totalGp);
            $rows[] = ['rank' => $i + 1, 'code' => $r->code, 'name' => $r->name, 'net_sales' => $this->d($r->net), 'gross_profit' => $gp, 'share_pct' => $this->pct($gp, $totalGp), 'cumulative_pct' => $cum,
                'class' => $before === null ? 'C' : ((float) $before < 80 ? 'A' : ((float) $before < 95 ? 'B' : 'C'))];
        }

        $out = $this->result([$this->col('rank', '#', 'int'), $this->col('class', 'Class'), $this->col('code', 'Code'), $this->col('name', 'Product'), $this->col('net_sales', 'Net sales', 'money'), $this->col('gross_profit', 'Gross profit', 'money'), $this->col('share_pct', 'Share %', 'pct'), $this->col('cumulative_pct', 'Cumulative %', 'pct')], $rows, ['net_sales', 'gross_profit']);
        foreach (['A', 'B', 'C'] as $class) {
            $out['totals']["class_{$class}_products"] = count(array_filter($rows, fn ($r) => $r['class'] === $class));
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function kpiScorecard(ReportContext $ctx): array
    {
        $sales = DB::table('sales')->where('branch_id', $ctx->branchId)->where('status', 'POSTED')->whereBetween('posted_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(subtotal - discount_total), 0) as net, COALESCE(SUM(cost_total), 0) as cost, COALESCE(SUM(grand_total), 0) as gross')->first();
        $voids = (int) DB::table('sales')->where('branch_id', $ctx->branchId)->where('status', 'VOIDED')->whereBetween('voided_at', [$ctx->fromDateTime(), $ctx->toDateTime()])->count();
        $ar = $this->d(DB::table('customer_credits as cc')->join('customers as c', 'c.id', '=', 'cc.customer_id')->where('c.organisation_id', $ctx->organisationId)->sum('cc.current_balance'));
        $ap = $this->d(DB::table('accounts_payables')->where('organisation_id', $ctx->organisationId)->sum('amount'));
        $stock = $this->d(DB::table('stock_balances')->whereIn('store_id', $ctx->storeIds)->selectRaw('SUM(qty_on_hand * wac) as v')->value('v'));
        $expiry = (new InventoryReports)->expiryRisk($ctx)['totals'];
        $fefo = (new InventoryReports)->fefoCompliance($ctx)['totals'];
        $net = $this->d($sales->net ?? 0);
        $gp = bcsub($net, $this->d($sales->cost ?? 0), 4);

        $row = [
            'period' => "{$ctx->from} to {$ctx->to}", 'transactions' => (int) ($sales->n ?? 0), 'net_sales' => $net, 'gross_total' => $this->d($sales->gross ?? 0), 'gross_profit' => $gp, 'margin_pct' => $this->pct($gp, $net),
            'avg_ticket' => (int) ($sales->n ?? 0) > 0 ? bcdiv($this->d($sales->gross), (string) $sales->n, 4) : '0.0000', 'voids' => $voids,
            'ar_outstanding' => $ar, 'ap_outstanding' => $ap, 'stock_value' => $stock,
            'expiry_risk_90d' => bcadd(bcadd($expiry['value_expired'], $expiry['value_critical_30'], 4), $expiry['value_warning_90'], 4),
            'fefo_compliance_pct' => $fefo['compliance_pct'],
        ];

        return $this->result([$this->col('period', 'Period'), $this->col('transactions', 'Txns', 'int'), $this->col('net_sales', 'Net sales', 'money'), $this->col('gross_total', 'Gross total', 'money'), $this->col('gross_profit', 'Gross profit', 'money'), $this->col('margin_pct', 'Margin %', 'pct'), $this->col('avg_ticket', 'Avg ticket', 'money'), $this->col('voids', 'Voids', 'int'), $this->col('ar_outstanding', 'AR outstanding', 'money'), $this->col('ap_outstanding', 'AP outstanding', 'money'), $this->col('stock_value', 'Stock value', 'money'), $this->col('expiry_risk_90d', 'Expiry risk ≤90d', 'money'), $this->col('fefo_compliance_pct', 'FEFO compliance %', 'pct')], [$row]);
    }
}
