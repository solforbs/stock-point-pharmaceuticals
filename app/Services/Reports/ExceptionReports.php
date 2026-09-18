<?php

namespace App\Services\Reports;

use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Part 19.3 — the exception reports auditors actually ask for. */
class ExceptionReports
{
    use FormatsReports;

    /**
     * @return array<string, mixed>
     */
    public function voids(ReportContext $ctx): array
    {
        $rows = DB::table('sales as s')->join('users as u', 'u.id', '=', 's.voided_by')
            ->where('s.branch_id', $ctx->branchId)->where('s.status', 'VOIDED')->whereBetween('s.voided_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->groupBy('u.name', DB::raw('DATE(s.voided_at)'))
            ->selectRaw('u.name as user, DATE(s.voided_at) as day, COUNT(*) as n, SUM(s.grand_total) as value, GROUP_CONCAT(s.doc_number SEPARATOR ", ") as documents, GROUP_CONCAT(s.void_reason SEPARATOR " | ") as reasons')
            ->orderBy('day')->get()
            ->map(fn ($r) => ['user' => $r->user, 'day' => $r->day, 'voids' => (int) $r->n, 'value' => $this->d($r->value), 'documents' => $r->documents, 'reasons' => $r->reasons])->all();

        return $this->result([$this->col('user', 'User'), $this->col('day', 'Day', 'date'), $this->col('voids', 'Voids', 'int'), $this->col('value', 'Value', 'money'), $this->col('documents', 'Documents'), $this->col('reasons', 'Reasons')], $rows, ['voids', 'value']);
    }

    /**
     * @return array<string, mixed>
     */
    public function discountsAboveThreshold(ReportContext $ctx): array
    {
        $threshold = (string) ($ctx->filter('threshold_pct') ?? '5');
        $rows = DB::table('sales as s')->join('sale_lines as l', 'l.sale_id', '=', 's.id')->join('products as p', 'p.id', '=', 'l.product_id')->join('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('users as a', 'a.id', '=', 'l.approved_by')
            ->where('s.branch_id', $ctx->branchId)->where('s.status', 'POSTED')->whereBetween('s.posted_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->where('l.discount_pct', '>', $threshold)
            ->orderByDesc('l.discount_pct')
            ->get(['s.doc_number', 's.posted_at', 'u.name as user', 'p.code', 'p.name', 'l.discount_pct', 'l.discount_amount', 'l.discount_source', 'a.name as approved_by'])
            ->map(fn ($r) => ['doc_number' => $r->doc_number, 'posted_at' => $r->posted_at, 'user' => $r->user, 'code' => $r->code, 'name' => $r->name, 'discount_pct' => number_format((float) $r->discount_pct, 2, '.', ''), 'discount_amount' => $this->d($r->discount_amount), 'source' => $r->discount_source, 'approved_by' => $r->approved_by ?? '— none —'])->all();

        $out = $this->result([$this->col('doc_number', 'Sale'), $this->col('posted_at', 'Posted', 'datetime'), $this->col('user', 'User'), $this->col('code', 'Code'), $this->col('name', 'Product'), $this->col('discount_pct', 'Discount %', 'pct'), $this->col('discount_amount', 'Discount', 'money'), $this->col('source', 'Source'), $this->col('approved_by', 'Approved by')], $rows, ['discount_amount']);
        $out['totals']['threshold_pct'] = $threshold;
        $out['totals']['unapproved'] = count(array_filter($rows, fn ($r) => $r['approved_by'] === '— none —'));

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function fefoOverrides(ReportContext $ctx): array
    {
        return (new InventoryReports)->fefoCompliance($ctx);
    }

    /**
     * @return array<string, mixed>
     */
    public function largeAdjustments(ReportContext $ctx): array
    {
        $threshold = (string) ($ctx->filter('threshold') ?? StockAdjustmentService::DEFAULT_APPROVAL_THRESHOLD);
        $rows = DB::table('stock_adjustments as a')->join('users as u', 'u.id', '=', 'a.created_by')->leftJoin('users as ap', 'ap.id', '=', 'a.approved_by')->join('stores as s', 's.id', '=', 'a.store_id')
            ->whereIn('a.store_id', $ctx->storeIds)->whereBetween('a.created_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->whereRaw('ABS(a.total_value) > ?', [$threshold])
            ->orderByDesc('a.created_at')
            ->get(['a.doc_number', 'a.created_at', 's.code as store', 'a.reason_code', 'a.total_value', 'a.approval_status', 'u.name as requested_by', 'ap.name as approved_by'])
            ->map(fn ($r) => ['doc_number' => $r->doc_number, 'created_at' => $r->created_at, 'store' => $r->store, 'reason_code' => $r->reason_code, 'value' => $this->d($r->total_value), 'approval_status' => $r->approval_status, 'requested_by' => $r->requested_by, 'approved_by' => $r->approved_by])->all();

        $out = $this->result([$this->col('doc_number', 'Adjustment'), $this->col('created_at', 'Raised', 'datetime'), $this->col('store', 'Store'), $this->col('reason_code', 'Reason'), $this->col('value', 'Value', 'money'), $this->col('approval_status', 'Status'), $this->col('requested_by', 'Requested by'), $this->col('approved_by', 'Approved by')], $rows, ['value']);
        $out['totals']['threshold'] = $threshold;

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function outOfHours(ReportContext $ctx): array
    {
        $open = (int) ($ctx->filter('open_hour') ?? 7);
        $close = (int) ($ctx->filter('close_hour') ?? 20);
        $rows = DB::table('sales as s')->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.branch_id', $ctx->branchId)->where('s.status', 'POSTED')->whereBetween('s.posted_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->whereRaw('(HOUR(s.posted_at) < ? OR HOUR(s.posted_at) >= ?)', [$open, $close])
            ->orderBy('s.posted_at')
            ->get(['s.doc_number', 's.posted_at', 's.sale_mode', 'u.name as user', 's.grand_total', 's.terminal_id'])
            ->map(fn ($r) => ['doc_number' => $r->doc_number, 'posted_at' => $r->posted_at, 'sale_mode' => $r->sale_mode, 'user' => $r->user, 'terminal' => $r->terminal_id, 'grand_total' => $this->d($r->grand_total)])->all();

        $out = $this->result([$this->col('doc_number', 'Sale'), $this->col('posted_at', 'Posted', 'datetime'), $this->col('sale_mode', 'Mode'), $this->col('user', 'User'), $this->col('terminal', 'Terminal'), $this->col('grand_total', 'Value', 'money')], $rows, ['grand_total']);
        $out['totals']['business_hours'] = sprintf('%02d:00–%02d:00', $open, $close);

        return $out;
    }

    /**
     * Every gapless sequence, checked by reading back the documents that
     * carry its numbers. Any hole is a missing document.
     *
     * @return array<string, mixed>
     */
    public function sequenceGaps(ReportContext $ctx): array
    {
        $tables = ['sales' => 'Sales', 'purchase_orders' => 'Purchase orders', 'goods_receipts' => 'Goods receipts', 'journal_entries' => 'Journals', 'payments' => null, 'stock_adjustments' => 'Adjustments', 'sales_orders' => 'Sales orders', 'delivery_notes' => 'Delivery notes', 'customer_returns' => 'Customer returns'];
        $rows = [];
        foreach ($tables as $table => $label) {
            if (! $label) {
                continue;
            }
            $query = DB::table($table)->whereNotNull('doc_number');
            if (Schema::hasColumn($table, 'branch_id')) {
                $query->where('branch_id', $ctx->branchId);
            }
            $numbers = $query->pluck('doc_number');
            $byPrefix = [];
            foreach ($numbers as $doc) {
                if (preg_match('/^(.*?)(\d+)$/', (string) $doc, $m)) {
                    $byPrefix[$m[1]][] = (int) $m[2];
                }
            }
            foreach ($byPrefix as $prefix => $seq) {
                sort($seq);
                $missing = [];
                for ($i = 1; $i < count($seq); $i++) {
                    for ($n = $seq[$i - 1] + 1; $n < $seq[$i]; $n++) {
                        $missing[] = $n;
                        if (count($missing) > 50) {
                            break 2;
                        }
                    }
                }
                if ($missing !== []) {
                    $rows[] = ['document' => $label, 'prefix' => $prefix, 'first' => $seq[0], 'last' => end($seq), 'issued' => count($seq), 'missing' => count($missing), 'missing_numbers' => implode(', ', array_slice($missing, 0, 50))];
                }
            }
        }

        return $this->result([$this->col('document', 'Document'), $this->col('prefix', 'Prefix'), $this->col('first', 'First', 'int'), $this->col('last', 'Last', 'int'), $this->col('issued', 'Issued', 'int'), $this->col('missing', 'Missing', 'int'), $this->col('missing_numbers', 'Missing numbers')], $rows, ['missing']);
    }

    /**
     * @return array<string, mixed>
     */
    public function manualJournals(ReportContext $ctx): array
    {
        $rows = DB::table('journal_entries as j')->join('users as u', 'u.id', '=', 'j.posted_by')->leftJoin('journal_entry_lines as l', 'l.journal_id', '=', 'j.id')
            ->where('j.organisation_id', $ctx->organisationId)->whereBetween('j.entry_date', [$ctx->from, $ctx->to])
            ->whereIn('j.source_doc_type', ['manual', 'MANUAL', 'manual_journal'])
            ->groupBy('j.id', 'j.doc_number', 'j.entry_date', 'j.narration', 'u.name', 'j.reverses_journal_id')
            ->selectRaw('j.doc_number, j.entry_date, j.narration, u.name as posted_by, j.reverses_journal_id, SUM(l.debit_amount) as debit')
            ->orderBy('j.entry_date')->get()
            ->map(fn ($r) => ['doc_number' => $r->doc_number, 'entry_date' => $r->entry_date, 'narration' => $r->narration, 'posted_by' => $r->posted_by, 'is_reversal' => (bool) $r->reverses_journal_id, 'amount' => $this->d($r->debit)])->all();

        return $this->result([$this->col('doc_number', 'Journal'), $this->col('entry_date', 'Date', 'date'), $this->col('narration', 'Narration'), $this->col('posted_by', 'Posted by'), $this->col('is_reversal', 'Reversal', 'bool'), $this->col('amount', 'Amount', 'money')], $rows, ['amount']);
    }
}
