<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceReports
{
    use FormatsReports;

    /**
     * @return list<object>
     */
    private function accountBalances(ReportContext $ctx, ?string $from, string $to): array
    {
        return DB::table('chart_of_accounts as a')
            ->leftJoin('journal_entry_lines as l', 'l.account_id', '=', 'a.id')
            ->leftJoin('journal_entries as j', function ($j) use ($from, $to) {
                $j->on('j.id', '=', 'l.journal_id')->whereDate('j.entry_date', '<=', $to);
                if ($from) {
                    $j->whereDate('j.entry_date', '>=', $from);
                }
            })
            ->where('a.organisation_id', $ctx->organisationId)
            ->groupBy('a.id', 'a.code', 'a.name', 'a.account_type', 'a.system_role')
            ->selectRaw('a.code, a.name, a.account_type, a.system_role, COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.debit_amount END), 0) as debit, COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.credit_amount END), 0) as credit')
            ->orderBy('a.code')->get()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function profitAndLoss(ReportContext $ctx): array
    {
        $rows = [];
        $sections = ['REVENUE' => '0.0000', 'COST_OF_SALES' => '0.0000', 'OPERATING_EXPENSES' => '0.0000', 'TAX' => '0.0000'];
        foreach ($this->accountBalances($ctx, $ctx->from, $ctx->to) as $a) {
            if (! in_array($a->account_type, ['REVENUE', 'EXPENSE'], true)) {
                continue;
            }
            $section = match (true) {
                $a->account_type === 'REVENUE' => 'REVENUE',
                (int) $a->code >= 7000 => 'TAX',
                (int) $a->code >= 6000 => 'OPERATING_EXPENSES',
                default => 'COST_OF_SALES',
            };
            $amount = $section === 'REVENUE' ? bcsub($this->d($a->credit), $this->d($a->debit), 4) : bcsub($this->d($a->debit), $this->d($a->credit), 4);
            if (bccomp($amount, '0', 4) === 0) {
                continue;
            }
            $rows[] = ['section' => $section, 'code' => $a->code, 'name' => $a->name, 'amount' => $amount];
            $sections[$section] = bcadd($sections[$section], $amount, 4);
        }

        $grossProfit = bcsub($sections['REVENUE'], $sections['COST_OF_SALES'], 4);
        $operating = bcsub($grossProfit, $sections['OPERATING_EXPENSES'], 4);
        $out = $this->result([$this->col('section', 'Section'), $this->col('code', 'Code'), $this->col('name', 'Account'), $this->col('amount', 'Amount', 'money')], $rows);
        $out['totals'] += [
            'revenue' => $sections['REVENUE'], 'cost_of_sales' => $sections['COST_OF_SALES'], 'gross_profit' => $grossProfit,
            'gross_margin_pct' => $this->pct($grossProfit, $sections['REVENUE']), 'operating_expenses' => $sections['OPERATING_EXPENSES'],
            'operating_profit' => $operating, 'tax' => $sections['TAX'], 'net_profit' => bcsub($operating, $sections['TAX'], 4),
        ];

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function balanceSheet(ReportContext $ctx): array
    {
        $rows = [];
        $totals = ['ASSET' => '0.0000', 'LIABILITY' => '0.0000', 'EQUITY' => '0.0000'];
        $result = '0.0000';
        foreach ($this->accountBalances($ctx, null, $ctx->to) as $a) {
            $net = bcsub($this->d($a->debit), $this->d($a->credit), 4);
            if (in_array($a->account_type, ['REVENUE', 'EXPENSE'], true)) {
                $result = bcsub($result, $net, 4); // credits (revenue) increase the result

                continue;
            }
            $balance = $a->account_type === 'ASSET' ? $net : bcmul($net, '-1', 4);
            if (bccomp($balance, '0', 4) === 0) {
                continue;
            }
            $rows[] = ['section' => $a->account_type, 'code' => $a->code, 'name' => $a->name, 'balance' => $balance];
            $totals[$a->account_type] = bcadd($totals[$a->account_type], $balance, 4);
        }
        $rows[] = ['section' => 'EQUITY', 'code' => '3200', 'name' => 'Current period result', 'balance' => $result];
        $totals['EQUITY'] = bcadd($totals['EQUITY'], $result, 4);

        $out = $this->result([$this->col('section', 'Section'), $this->col('code', 'Code'), $this->col('name', 'Account'), $this->col('balance', 'Balance', 'money')], $rows);
        $inventoryLedger = $this->d(DB::table('stock_balances as b')->join('stores as s', 's.id', '=', 'b.store_id')->join('branches as br', 'br.id', '=', 's.branch_id')->where('br.organisation_id', $ctx->organisationId)->selectRaw('SUM(b.qty_on_hand * b.wac) as v')->value('v'));
        $out['totals'] += [
            'as_of' => $ctx->to, 'assets' => $totals['ASSET'], 'liabilities' => $totals['LIABILITY'], 'equity' => $totals['EQUITY'],
            'balances' => bccomp($totals['ASSET'], bcadd($totals['LIABILITY'], $totals['EQUITY'], 4), 4) === 0,
            'inventory_per_ledger' => $inventoryLedger,
        ];

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function apAgeing(ReportContext $ctx): array
    {
        $today = Carbon::parse($ctx->to);
        $rows = [];
        $suppliers = DB::table('suppliers')->where('organisation_id', $ctx->organisationId)->orderBy('name')->get(['id', 'code', 'name', 'payment_terms_days']);
        foreach ($suppliers as $s) {
            $balance = $this->d(DB::table('accounts_payables')->where('supplier_id', $s->id)->sum('amount'));
            if (bccomp($balance, '0', 4) <= 0) {
                continue;
            }
            // Pay off the oldest invoices first with everything received so far, then age what remains.
            $paid = $this->d(DB::table('accounts_payables')->where('supplier_id', $s->id)->where('amount', '<', 0)->sum('amount'));
            $remainingCredit = bcmul($paid, '-1', 4);
            $buckets = ['current' => '0.0000', 'd1_30' => '0.0000', 'd31_60' => '0.0000', 'd61_90' => '0.0000', 'd90_plus' => '0.0000'];
            $invoices = DB::table('supplier_invoices')->where('supplier_id', $s->id)->where('match_status', 'MATCHED')->orderBy('invoice_date')->get(['grand_total', 'invoice_date', 'due_date']);
            foreach ($invoices as $inv) {
                $outstanding = $this->d($inv->grand_total);
                $applied = bccomp($remainingCredit, $outstanding, 4) >= 0 ? $outstanding : $remainingCredit;
                $outstanding = bcsub($outstanding, $applied, 4);
                $remainingCredit = bcsub($remainingCredit, $applied, 4);
                if (bccomp($outstanding, '0', 4) <= 0) {
                    continue;
                }
                $due = $inv->due_date ? Carbon::parse($inv->due_date) : Carbon::parse($inv->invoice_date)->addDays((int) $s->payment_terms_days);
                $overdue = (int) $due->diffInDays($today, false);
                $bucket = match (true) {
                    $overdue <= 0 => 'current', $overdue <= 30 => 'd1_30', $overdue <= 60 => 'd31_60', $overdue <= 90 => 'd61_90', default => 'd90_plus'
                };
                $buckets[$bucket] = bcadd($buckets[$bucket], $outstanding, 4);
            }
            $rows[] = ['code' => $s->code, 'name' => $s->name, 'balance' => $balance] + $buckets;
        }

        return $this->result([$this->col('code', 'Code'), $this->col('name', 'Supplier'), $this->col('balance', 'Balance', 'money'), $this->col('current', 'Current', 'money'), $this->col('d1_30', '1-30', 'money'), $this->col('d31_60', '31-60', 'money'), $this->col('d61_90', '61-90', 'money'), $this->col('d90_plus', '90+', 'money')], $rows, ['balance', 'current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus']);
    }

    /**
     * @return array<string, mixed>
     */
    public function customerStatement(ReportContext $ctx): array
    {
        $customerId = (string) $ctx->filter('customer_id');
        if ($customerId === '') {
            throw new \InvalidArgumentException('customer_id is required for a customer statement.');
        }
        $opening = $this->d(DB::table('accounts_receivables')->where('customer_id', $customerId)->where('created_at', '<', $ctx->fromDateTime())->sum('amount'));
        $running = $opening;
        $rows = [['date' => $ctx->from, 'type' => 'OPENING_BALANCE', 'reference' => '', 'debit' => '0.0000', 'credit' => '0.0000', 'balance' => $opening]];
        $entries = DB::table('accounts_receivables as ar')->leftJoin('sales as s', 's.id', '=', 'ar.sale_id')->leftJoin('payments as p', 'p.id', '=', 'ar.payment_id')
            ->where('ar.customer_id', $customerId)->whereBetween('ar.created_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->orderBy('ar.created_at')->get(['ar.created_at', 'ar.txn_type', 'ar.amount', 's.doc_number as sale_doc', 'p.reference as payment_ref', 'p.method']);
        foreach ($entries as $e) {
            $amount = $this->d($e->amount);
            $running = bcadd($running, $amount, 4);
            $rows[] = ['date' => $e->created_at, 'type' => $e->txn_type, 'reference' => $e->sale_doc ?? trim(($e->method ?? '').' '.($e->payment_ref ?? '')),
                'debit' => bccomp($amount, '0', 4) > 0 ? $amount : '0.0000', 'credit' => bccomp($amount, '0', 4) < 0 ? bcmul($amount, '-1', 4) : '0.0000', 'balance' => $running];
        }
        $out = $this->result([$this->col('date', 'Date', 'datetime'), $this->col('type', 'Type'), $this->col('reference', 'Reference'), $this->col('debit', 'Debit', 'money'), $this->col('credit', 'Credit', 'money'), $this->col('balance', 'Balance', 'money')], $rows, ['debit', 'credit']);
        $out['totals']['opening_balance'] = $opening;
        $out['totals']['closing_balance'] = $running;
        $out['totals']['customer'] = DB::table('customers')->where('id', $customerId)->first(['code', 'name', 'payment_terms_days']);

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function unallocatedReceipts(ReportContext $ctx): array
    {
        $rows = DB::table('payments as p')->leftJoin('customers as c', 'c.id', '=', 'p.customer_id')
            ->leftJoin('payment_allocations as a', 'a.payment_id', '=', 'p.id')
            ->where('p.branch_id', $ctx->branchId)->where('p.status', 'CLEARED')
            ->groupBy('p.id', 'p.received_at', 'p.method', 'p.reference', 'p.amount', 'c.code', 'c.name')
            ->havingRaw('p.amount - COALESCE(SUM(CASE WHEN a.allocated_to_type = "sale" THEN a.amount ELSE 0 END), 0) > 0')
            ->selectRaw('p.received_at, p.method, p.reference, p.amount, c.code, c.name, p.amount - COALESCE(SUM(CASE WHEN a.allocated_to_type = "sale" THEN a.amount ELSE 0 END), 0) as unallocated')
            ->orderBy('p.received_at')->get()
            ->map(fn ($r) => ['received_at' => $r->received_at, 'customer_code' => $r->code, 'customer' => $r->name, 'method' => $r->method, 'reference' => $r->reference, 'amount' => $this->d($r->amount), 'unallocated' => $this->d($r->unallocated), 'days' => (int) Carbon::parse($r->received_at)->diffInDays(now())])->all();

        return $this->result([$this->col('received_at', 'Received', 'datetime'), $this->col('customer_code', 'Code'), $this->col('customer', 'Customer'), $this->col('method', 'Method'), $this->col('reference', 'Reference'), $this->col('amount', 'Amount', 'money'), $this->col('unallocated', 'Unallocated', 'money'), $this->col('days', 'Days', 'int')], $rows, ['amount', 'unallocated']);
    }

    /**
     * @return array<string, mixed>
     */
    public function vatReturn(ReportContext $ctx): array
    {
        $rows = [];
        $output = '0.0000';
        $input = '0.0000';
        foreach ($this->accountBalances($ctx, $ctx->from, $ctx->to) as $a) {
            if ($a->system_role === 'VAT_OUTPUT') {
                $output = bcsub($this->d($a->credit), $this->d($a->debit), 4);
                $rows[] = ['line' => 'Output VAT on sales (net of credit notes)', 'amount' => $output];
            }
            if ($a->system_role === 'VAT_INPUT') {
                $input = bcsub($this->d($a->debit), $this->d($a->credit), 4);
                $rows[] = ['line' => 'Input VAT on purchases', 'amount' => $input];
            }
        }
        $sales = DB::table('sales')->where('branch_id', $ctx->branchId)->where('status', 'POSTED')->whereBetween('posted_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->selectRaw('SUM(subtotal - discount_total) as net, SUM(tax_total) as tax')->first();
        $rows[] = ['line' => 'Taxable sales (net, this branch)', 'amount' => $this->d($sales->net ?? 0)];
        $rows[] = ['line' => 'VAT charged on those sales', 'amount' => $this->d($sales->tax ?? 0)];
        $rows[] = ['line' => 'Net VAT payable (output − input), organisation', 'amount' => bcsub($output, $input, 4)];

        $out = $this->result([$this->col('line', 'Line'), $this->col('amount', 'Amount', 'money')], $rows);
        $out['totals'] += ['output_vat' => $output, 'input_vat' => $input, 'net_payable' => bcsub($output, $input, 4)];

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function tillSummary(ReportContext $ctx): array
    {
        $receipts = DB::table('payments')->where('branch_id', $ctx->branchId)->where('status', 'CLEARED')->whereBetween('received_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->groupBy(DB::raw('DATE(received_at)'), 'method')->selectRaw('DATE(received_at) as day, method, COUNT(*) as n, SUM(amount) as amount, SUM(CASE WHEN reconciled_at IS NOT NULL THEN amount ELSE 0 END) as reconciled')->get();
        $rows = $receipts->map(fn ($r) => ['day' => $r->day, 'source' => 'RECEIPT', 'method' => $r->method, 'count' => (int) $r->n, 'amount' => $this->d($r->amount), 'reconciled' => $this->d($r->reconciled)])->all();

        // Cash and M-PESA taken at the till on retail sales, from the sales journal.
        $tills = DB::table('journal_entries as j')->join('journal_entry_lines as l', 'l.journal_id', '=', 'j.id')->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('j.branch_id', $ctx->branchId)->where('j.source_doc_type', 'sale')->whereBetween('j.entry_date', [$ctx->from, $ctx->to])
            ->whereIn('a.system_role', ['CASH', 'MPESA_CLEARING', 'BANK'])
            ->groupBy('j.entry_date', 'a.system_role')->selectRaw('j.entry_date as day, a.system_role as method, COUNT(DISTINCT j.id) as n, SUM(l.debit_amount - l.credit_amount) as amount')->get();
        foreach ($tills as $t) {
            $rows[] = ['day' => $t->day, 'source' => 'POS_TENDER', 'method' => $t->method, 'count' => (int) $t->n, 'amount' => $this->d($t->amount), 'reconciled' => null];
        }
        usort($rows, fn ($a, $b) => strcmp((string) $a['day'], (string) $b['day']) ?: strcmp($a['source'], $b['source']));

        return $this->result([$this->col('day', 'Day', 'date'), $this->col('source', 'Source'), $this->col('method', 'Method'), $this->col('count', 'Count', 'int'), $this->col('amount', 'Amount', 'money'), $this->col('reconciled', 'Reconciled', 'money')], $rows, ['count', 'amount']);
    }

    /**
     * @return array<string, mixed>
     */
    public function periodCloseChecklist(ReportContext $ctx): array
    {
        $period = DB::table('financial_periods')->where('organisation_id', $ctx->organisationId)->where('status', 'OPEN')->orderBy('start_date')->first();
        $checks = [];
        $add = function (string $check, int|string $count, string $detail) use (&$checks) {
            $checks[] = ['check' => $check, 'passes' => is_int($count) ? $count === 0 : $count === 'OK', 'count' => is_int($count) ? $count : null, 'detail' => $detail];
        };

        $unbalanced = 0;
        if ($period) {
            $unbalancedRows = DB::table('journal_entries as j')->join('journal_entry_lines as l', 'l.journal_id', '=', 'j.id')->where('j.period_id', $period->id)
                ->groupBy('j.id')->havingRaw('ABS(SUM(l.debit_amount) - SUM(l.credit_amount)) > 0.0001')->select('j.id');
            $unbalanced = DB::query()->fromSub($unbalancedRows, 'u')->count();
        }
        $add('Every journal in the period balances', $unbalanced, 'Journals where Σ debit ≠ Σ credit');
        $driftRows = DB::table('stock_balances as b')->leftJoin('stock_ledgers as l', fn ($j) => $j->on('l.product_id', '=', 'b.product_id')->on('l.batch_id', '=', 'b.batch_id')->on('l.store_id', '=', 'b.store_id'))
            ->whereIn('b.store_id', $ctx->storeIds)->groupBy('b.id', 'b.qty_on_hand')->havingRaw('COALESCE(SUM(l.qty_base), 0) <> b.qty_on_hand')->select('b.id');
        $drift = DB::query()->fromSub($driftRows, 'd')->count();
        $add('Stock balances reconcile to the ledger', $drift, 'Balance rows whose quantity differs from the ledger sum');
        $unmatched = (int) DB::table('goods_receipts as g')->where('g.branch_id', $ctx->branchId)->where('g.status', 'POSTED')->where('g.is_emergency', false)
            ->whereNotExists(fn ($q) => $q->from('supplier_invoice_lines as il')->join('purchase_order_lines as pl', 'pl.id', '=', 'il.purchase_order_line_id')->join('supplier_invoices as i', 'i.id', '=', 'il.supplier_invoice_id')
                ->whereColumn('pl.purchase_order_id', 'g.purchase_order_id')->where('i.match_status', 'MATCHED'))->count();
        $add('All GRNs invoiced or accrued', $unmatched, 'Posted receipts with no matched supplier invoice (sitting in GRN accrual)');
        $add('No stock adjustments awaiting approval', (int) DB::table('stock_adjustments')->whereIn('store_id', $ctx->storeIds)->where('approval_status', 'PENDING')->count(), 'Pending adjustments');
        $add('No stock counts awaiting approval', (int) DB::table('stock_counts')->whereIn('store_id', $ctx->storeIds)->whereIn('status', ['COUNTING', 'REVIEW'])->count(), 'Counts in COUNTING or REVIEW');
        $add('No transfers unresolved', (int) DB::table('stock_transfers')->where(fn ($q) => $q->whereIn('from_store_id', $ctx->storeIds)->orWhereIn('to_store_id', $ctx->storeIds))->where('status', 'DISCREPANCY')->count(), 'Transfers in DISCREPANCY');
        $add('No unallocated receipts', count($this->unallocatedReceipts($ctx)['rows']), 'Receipts not applied to an invoice');
        $add('No failed eTIMS transmissions', (int) DB::table('sales')->where('branch_id', $ctx->branchId)->where('etims_status', 'FAILED')->count(), 'Sales KRA has not accepted');

        $ledgerValue = $this->d(DB::table('stock_balances as b')->join('stores as s', 's.id', '=', 'b.store_id')->join('branches as br', 'br.id', '=', 's.branch_id')->where('br.organisation_id', $ctx->organisationId)->selectRaw('SUM(b.qty_on_hand * b.wac) as v')->value('v'));
        $gl = collect($this->accountBalances($ctx, null, now()->toDateString()))->firstWhere('system_role', 'INVENTORY');
        $glValue = $gl ? bcsub($this->d($gl->debit), $this->d($gl->credit), 4) : '0.0000';
        $checks[] = ['check' => 'Inventory ledger value equals GL account 1200', 'passes' => bccomp($ledgerValue, $glValue, 2) === 0, 'count' => null, 'detail' => "Ledger {$ledgerValue} vs GL {$glValue}"];

        $out = $this->result([$this->col('check', 'Check'), $this->col('passes', 'Passes', 'bool'), $this->col('count', 'Count', 'int'), $this->col('detail', 'Detail')], $checks);
        $out['totals']['period'] = $period ? "{$period->fiscal_year}/{$period->period_no} ({$period->start_date} to {$period->end_date})" : null;
        $out['totals']['ready_to_close'] = collect($checks)->every(fn ($c) => $c['passes']);

        return $out;
    }
}
