<?php

namespace App\Services\Reports;

use App\Services\Finance\ChartOfAccountsBalances;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cash reports: the 13-week cash-flow forecast, the M-PESA and bank receipt
 * registers, and the petty cash book.
 */
class CashReports
{
    use FormatsReports;

    private const HORIZON_WEEKS = 13;

    /**
     * The 13-week rolling cash-flow forecast (week 1 starts on the "as of"
     * date, today when blank). Opening cash is what the ledger holds in the
     * cash, bank, M-PESA and petty cash accounts; receipts are open customer
     * invoices landing on their due date; payments are matched supplier
     * invoices on theirs and the monthly payroll at month end. Anything
     * already overdue is assumed to move in week 1.
     *
     * @return array<string, mixed>
     */
    public function cashflowForecast(ReportContext $ctx): array
    {
        $start = Carbon::parse((string) $ctx->filter('as_of', now()->toDateString()))->startOfDay();
        $collectPct = min(100.0, max(0.0, (float) $ctx->filter('collection_pct', 100)));
        $horizonEnd = $start->copy()->addWeeks(self::HORIZON_WEEKS)->subDay();

        $weeks = [];
        for ($i = 0; $i < self::HORIZON_WEEKS; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $weeks[$i] = ['week' => $i + 1, 'week_start' => $weekStart->toDateString(), 'week_end' => $weekStart->copy()->addDays(6)->toDateString(),
                'opening' => '0.0000', 'ar_receipts' => '0.0000', 'ap_payments' => '0.0000', 'payroll' => '0.0000', 'net_flow' => '0.0000', 'closing' => '0.0000'];
        }
        $weekOf = fn (Carbon $date): int => $date->lt($start) ? 0 : (int) floor($start->diffInDays($date) / 7);

        // Customer invoices still owing, on their due date.
        $beyond = ['ar' => '0.0000', 'ap' => '0.0000'];
        $invoices = DB::table('sales as s')
            ->join('customers as c', 'c.id', '=', 's.customer_id')
            ->join('accounts_receivables as ar', 'ar.sale_id', '=', 's.id')
            ->where('c.organisation_id', $ctx->organisationId)->where('s.status', 'POSTED')
            ->groupBy('s.id', 's.posted_at', 'c.payment_terms_days')
            ->havingRaw('SUM(ar.amount) > 0.0001')
            ->selectRaw('s.posted_at, c.payment_terms_days, SUM(ar.amount) as outstanding')->get();
        foreach ($invoices as $inv) {
            $due = Carbon::parse($inv->posted_at)->addDays((int) $inv->payment_terms_days)->startOfDay();
            $amount = bcmul($this->d($inv->outstanding), (string) ($collectPct / 100), 4);
            if ($due->gt($horizonEnd)) {
                $beyond['ar'] = bcadd($beyond['ar'], $amount, 4);

                continue;
            }
            $w = $weekOf($due);
            $weeks[$w]['ar_receipts'] = bcadd($weeks[$w]['ar_receipts'], $amount, 4);
        }

        // Matched supplier invoices still owing, oldest paid first, on their due date.
        foreach (DB::table('suppliers')->where('organisation_id', $ctx->organisationId)->get(['id', 'payment_terms_days']) as $supplier) {
            $credit = bcmul($this->d(DB::table('accounts_payables')->where('supplier_id', $supplier->id)->where('amount', '<', 0)->sum('amount')), '-1', 4);
            $supplierInvoices = DB::table('supplier_invoices')->where('supplier_id', $supplier->id)->where('match_status', 'MATCHED')->orderBy('invoice_date')->get(['grand_total', 'invoice_date', 'due_date']);
            foreach ($supplierInvoices as $inv) {
                $owing = $this->d($inv->grand_total);
                $applied = bccomp($credit, $owing, 4) >= 0 ? $owing : $credit;
                $owing = bcsub($owing, $applied, 4);
                $credit = bcsub($credit, $applied, 4);
                if (bccomp($owing, '0', 4) <= 0) {
                    continue;
                }
                $due = ($inv->due_date ? Carbon::parse($inv->due_date) : Carbon::parse($inv->invoice_date)->addDays((int) $supplier->payment_terms_days))->startOfDay();
                if ($due->gt($horizonEnd)) {
                    $beyond['ap'] = bcadd($beyond['ap'], $owing, 4);

                    continue;
                }
                $w = $weekOf($due);
                $weeks[$w]['ap_payments'] = bcadd($weeks[$w]['ap_payments'], $owing, 4);
            }
        }

        // Payroll leaves the bank at month end; the last posted run is the best estimate of the next.
        $payroll = $ctx->filter('payroll_monthly');
        if ($payroll === null) {
            $run = DB::table('payroll_runs')->where('organisation_id', $ctx->organisationId)->whereNotNull('posted_at')->orderByDesc('period_year')->orderByDesc('period_month')->first();
            $payroll = $run ? bcadd(bcadd($this->d($run->total_gross), $this->d($run->total_nssf_employer), 4), $this->d($run->total_housing_levy_employer), 4) : '0';
        }
        $payroll = $this->d($payroll);
        if (bccomp($payroll, '0', 4) > 0) {
            for ($month = $start->copy()->endOfMonth()->startOfDay(); $month->lte($horizonEnd); $month = $month->copy()->addMonthNoOverflow()->endOfMonth()->startOfDay()) {
                if ($month->gte($start)) {
                    $w = $weekOf($month);
                    $weeks[$w]['payroll'] = bcadd($weeks[$w]['payroll'], $payroll, 4);
                }
            }
        }

        $opening = ChartOfAccountsBalances::cashOnHand($ctx->organisationId, $start->copy()->subDay()->toDateString());
        $running = $opening;
        $lowest = null;
        $negativeWeeks = 0;
        foreach ($weeks as $i => $w) {
            $net = bcsub($w['ar_receipts'], bcadd($w['ap_payments'], $w['payroll'], 4), 4);
            $weeks[$i]['opening'] = $running;
            $running = bcadd($running, $net, 4);
            $weeks[$i]['net_flow'] = $net;
            $weeks[$i]['closing'] = $running;
            if ($lowest === null || bccomp($running, $lowest['closing'], 4) < 0) {
                $lowest = ['week' => $w['week'], 'closing' => $running];
            }
            $negativeWeeks += bccomp($running, '0', 4) < 0 ? 1 : 0;
        }

        $out = $this->result([
            $this->col('week', 'Week', 'int'), $this->col('week_start', 'From', 'date'), $this->col('week_end', 'To', 'date'),
            $this->col('opening', 'Opening cash', 'money'), $this->col('ar_receipts', 'Customer receipts', 'money'),
            $this->col('ap_payments', 'Supplier payments', 'money'), $this->col('payroll', 'Payroll', 'money'),
            $this->col('net_flow', 'Net cash flow', 'money'), $this->col('closing', 'Closing cash', 'money'),
        ], array_values($weeks), ['ar_receipts', 'ap_payments', 'payroll', 'net_flow']);

        $out['from'] = $start->toDateString();
        $out['to'] = $horizonEnd->toDateString();
        $out['totals'] += [
            'opening_cash' => $opening,
            'closing_cash_week_13' => $running,
            'lowest_closing_week' => $lowest['week'] ?? null,
            'lowest_closing_cash' => $lowest['closing'] ?? $opening,
            'weeks_below_zero' => $negativeWeeks,
            'receivable_after_week_13' => $beyond['ar'],
            'payable_after_week_13' => $beyond['ap'],
            'assumed_collection_pct' => number_format($collectPct, 2, '.', ''),
            'monthly_payroll_used' => $payroll,
        ];

        return $out;
    }

    /**
     * Every M-PESA (or the chosen method) receipt in date order: who paid,
     * which code, how it was applied, and whether it was exact, short (the
     * invoice is still part-owing) or over (the excess sits as unallocated
     * credit) — the register to work from when a figure does not agree.
     *
     * @return array<string, mixed>
     */
    public function mpesaLog(ReportContext $ctx): array
    {
        return $this->receiptRegister($ctx, [(string) $ctx->filter('method', 'MPESA')], 'Payer phone');
    }

    /**
     * Bank, cheque and card receipts with the account they were paid into and the payer's bank details.
     *
     * @return array<string, mixed>
     */
    public function bankReceipts(ReportContext $ctx): array
    {
        $method = $ctx->filter('method');

        return $this->receiptRegister($ctx, $method ? [(string) $method] : ['BANK', 'CHEQUE', 'CARD'], 'Payer account');
    }

    /**
     * @param  list<string>  $methods
     * @return array<string, mixed>
     */
    private function receiptRegister(ReportContext $ctx, array $methods, string $payerLabel): array
    {
        $payments = DB::table('payments as p')
            ->leftJoin('customers as c', 'c.id', '=', 'p.customer_id')
            ->leftJoin('bank_accounts as b', 'b.id', '=', 'p.bank_account_id')
            ->where('p.branch_id', $ctx->branchId)->whereIn('p.method', $methods)
            ->whereBetween('p.received_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->orderBy('p.received_at')->orderBy('p.id')
            ->get(['p.id', 'p.received_at', 'p.method', 'p.reference', 'p.amount', 'p.status', 'p.reconciled_at', 'p.payer_name', 'p.payer_bank', 'p.payer_account',
                'c.code as customer_code', 'c.name as customer', 'c.mpesa_phone', 'c.phone', 'b.name as bank_account']);

        $allocations = DB::table('payment_allocations')->whereIn('payment_id', $payments->pluck('id'))->where('allocated_to_type', 'sale')->get(['payment_id', 'allocated_to_id', 'amount'])->groupBy('payment_id');
        $saleIds = $allocations->flatten(1)->pluck('allocated_to_id')->unique();
        $owingNow = DB::table('accounts_receivables')->whereIn('sale_id', $saleIds)->groupBy('sale_id')->selectRaw('sale_id, SUM(amount) as owing')->pluck('owing', 'sale_id');
        // Every cleared receipt applied to those invoices, so "still owing" can be read as at each receipt, not as today.
        $everyAllocation = DB::table('payment_allocations as a')->join('payments as p2', 'p2.id', '=', 'a.payment_id')
            ->whereIn('a.allocated_to_id', $saleIds)->where('a.allocated_to_type', 'sale')->where('p2.status', 'CLEARED')
            ->get(['a.allocated_to_id as sale_id', 'a.payment_id', 'a.amount', 'p2.received_at'])->groupBy('sale_id');

        $rows = [];
        $running = '0.0000';
        foreach ($payments as $p) {
            $mine = $allocations->get($p->id, collect());
            $allocated = $mine->reduce(fn ($c, $a) => bcadd($c, $this->d($a->amount), 4), '0.0000');
            $unallocated = bcsub($this->d($p->amount), $allocated, 4);
            $stillOwing = $mine->pluck('allocated_to_id')->unique()->reduce(function ($c, $id) use ($owingNow, $everyAllocation, $p) {
                $later = $everyAllocation->get($id, collect())
                    ->filter(fn ($a) => $a->payment_id !== $p->id && ($a->received_at > $p->received_at || ($a->received_at == $p->received_at && $a->payment_id > $p->id)))
                    ->reduce(fn ($sum, $a) => bcadd($sum, $this->d($a->amount), 4), '0.0000');

                return bcadd($c, bcadd($this->d(max(0, (float) ($owingNow[$id] ?? 0))), $later, 4), 4);
            }, '0.0000');
            $status = match (true) {
                $p->status === 'REVERSED' => 'REVERSED',
                bccomp($unallocated, '0', 4) > 0 => 'OVERPAID',
                bccomp($stillOwing, '0', 4) > 0 => 'SHORT_PAID',
                default => 'EXACT',
            };
            if ($p->status === 'CLEARED') {
                $running = bcadd($running, $this->d($p->amount), 4);
            }
            $rows[] = [
                'received_at' => $p->received_at, 'customer_code' => $p->customer_code, 'customer' => $p->customer, 'method' => $p->method,
                'reference' => $p->reference, 'bank_account' => $p->bank_account,
                'payer' => $p->payer_name ?: null,
                'payer_detail' => trim(implode(' ', array_filter([$p->payer_bank, $p->payer_account ?: ($p->method === 'MPESA' ? ($p->mpesa_phone ?: $p->phone) : null)]))) ?: null,
                'amount' => $this->d($p->amount), 'allocated' => $allocated, 'unallocated' => $unallocated, 'invoice_still_owing' => $stillOwing,
                'status' => $status, 'reconciled' => $p->reconciled_at !== null, 'running_total' => $running,
            ];
        }

        $columns = [
            $this->col('received_at', 'Received', 'datetime'), $this->col('customer_code', 'Code'), $this->col('customer', 'Customer'),
            $this->col('method', 'Method'), $this->col('reference', 'Reference'), $this->col('bank_account', 'Paid into'),
            $this->col('payer', 'Payer'), $this->col('payer_detail', $payerLabel),
            $this->col('amount', 'Amount', 'money'), $this->col('allocated', 'Applied to invoices', 'money'), $this->col('unallocated', 'Held as credit', 'money'),
            $this->col('invoice_still_owing', 'Invoice still owing', 'money'), $this->col('status', 'Result'), $this->col('reconciled', 'Reconciled', 'bool'),
            $this->col('running_total', 'Running total', 'money'),
        ];
        $out = $this->result($columns, $rows, ['amount', 'allocated', 'unallocated']);
        $out['totals']['short_paid'] = collect($rows)->where('status', 'SHORT_PAID')->count();
        $out['totals']['overpaid'] = collect($rows)->where('status', 'OVERPAID')->count();
        $out['totals']['not_reconciled'] = collect($rows)->where('status', '!=', 'REVERSED')->where('reconciled', false)->count();

        return $out;
    }

    /**
     * The petty cash book for the branch: opening float, each top-up (in) and
     * voucher (out) with a running balance, and the closing float.
     *
     * @return array<string, mixed>
     */
    public function pettyCashBook(ReportContext $ctx): array
    {
        $opening = ChartOfAccountsBalances::pettyCash($ctx->organisationId, $ctx->branchId, Carbon::parse($ctx->from)->subDay()->toDateString());

        $vouchers = DB::table('petty_cash_vouchers as v')->leftJoin('chart_of_accounts as a', 'a.id', '=', 'v.account_id')
            ->where('v.branch_id', $ctx->branchId)->whereBetween('v.voucher_date', [$ctx->from, $ctx->to])
            ->orderBy('v.voucher_date')->orderBy('v.created_at')
            ->get(['v.voucher_date', 'v.doc_number', 'v.voucher_type', 'v.funding_source', 'v.payee', 'v.description', 'v.receipt_ref', 'v.amount', 'v.status', 'a.code', 'a.name']);

        $rows = [['date' => $ctx->from, 'doc_number' => null, 'type' => 'OPENING', 'account' => null, 'payee' => null, 'description' => 'Opening float', 'receipt_ref' => null, 'money_in' => '0.0000', 'money_out' => '0.0000', 'status' => null, 'balance' => $opening]];
        $running = $opening;
        $in = '0.0000';
        $out = '0.0000';
        foreach ($vouchers as $v) {
            $isTopUp = $v->voucher_type === 'TOPUP';
            $counts = $v->status === 'POSTED';
            $amount = $this->d($v->amount);
            if ($counts) {
                $running = $isTopUp ? bcadd($running, $amount, 4) : bcsub($running, $amount, 4);
                if ($isTopUp) {
                    $in = bcadd($in, $amount, 4);
                } else {
                    $out = bcadd($out, $amount, 4);
                }
            }
            $rows[] = [
                'date' => $v->voucher_date, 'doc_number' => $v->doc_number, 'type' => $isTopUp ? 'TOP-UP ('.$v->funding_source.')' : 'EXPENSE',
                'account' => $v->code ? "{$v->code} {$v->name}" : null, 'payee' => $v->payee, 'description' => $v->description, 'receipt_ref' => $v->receipt_ref,
                'money_in' => $isTopUp ? $amount : '0.0000', 'money_out' => $isTopUp ? '0.0000' : $amount, 'status' => $v->status, 'balance' => $running,
            ];
        }

        $result = $this->result([
            $this->col('date', 'Date', 'date'), $this->col('doc_number', 'Voucher'), $this->col('type', 'Type'), $this->col('account', 'Expense account'),
            $this->col('payee', 'Paid to'), $this->col('description', 'Description'), $this->col('receipt_ref', 'Receipt'),
            $this->col('money_in', 'In', 'money'), $this->col('money_out', 'Out', 'money'), $this->col('status', 'Status'), $this->col('balance', 'Balance', 'money'),
        ], $rows);
        $result['totals'] += ['opening_float' => $opening, 'topped_up' => $in, 'spent' => $out, 'closing_float' => $running];

        return $result;
    }
}
