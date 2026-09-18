<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProcurementReports
{
    use FormatsReports;

    /**
     * @return array<string, mixed>
     */
    public function openPurchaseOrders(ReportContext $ctx): array
    {
        $rows = DB::table('purchase_orders as po')->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('purchase_order_lines as l', 'l.purchase_order_id', '=', 'po.id')
            ->where('po.branch_id', $ctx->branchId)->whereIn('po.status', ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED'])
            ->groupBy('po.id', 'po.doc_number', 's.code', 's.name', 'po.status', 'po.created_at', 'po.sent_at', 'po.expected_date')
            ->selectRaw('po.doc_number, s.code as supplier_code, s.name as supplier, po.status, po.created_at, po.sent_at, po.expected_date, COUNT(l.id) as line_count, SUM(l.qty_ordered * l.unit_price) as value')
            ->orderBy('po.created_at')->get()
            ->map(function ($r) {
                $age = (int) Carbon::parse($r->created_at)->diffInDays(now());

                return ['doc_number' => $r->doc_number, 'supplier_code' => $r->supplier_code, 'supplier' => $r->supplier, 'status' => $r->status, 'created_at' => $r->created_at, 'expected_date' => $r->expected_date,
                    'days_open' => $age, 'age_bucket' => match (true) {
                        $age <= 7 => '0-7', $age <= 14 => '8-14', $age <= 30 => '15-30', default => '30+'
                    },
                    'overdue' => $r->expected_date && $r->expected_date < now()->toDateString(), 'lines' => (int) $r->line_count, 'value' => $this->d($r->value)];
            })->all();

        return $this->result([$this->col('doc_number', 'PO'), $this->col('supplier', 'Supplier'), $this->col('status', 'Status'), $this->col('created_at', 'Raised', 'datetime'), $this->col('expected_date', 'Expected', 'date'), $this->col('days_open', 'Days open', 'int'), $this->col('age_bucket', 'Age'), $this->col('overdue', 'Overdue', 'bool'), $this->col('lines', 'Lines', 'int'), $this->col('value', 'Value', 'money')], $rows, ['lines', 'value']);
    }

    /**
     * @return array<string, mixed>
     */
    public function outstandingQuantities(ReportContext $ctx): array
    {
        $rows = DB::table('purchase_order_lines as l')->join('purchase_orders as po', 'po.id', '=', 'l.purchase_order_id')
            ->join('products as p', 'p.id', '=', 'l.product_id')->join('units_of_measure as u', 'u.id', '=', 'l.uom_id')->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('goods_receipt_lines as g', 'g.purchase_order_line_id', '=', 'l.id')
            ->where('po.branch_id', $ctx->branchId)->whereIn('po.status', ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED'])
            ->groupBy('l.id', 'po.doc_number', 's.name', 'p.code', 'p.name', 'u.code', 'l.qty_ordered', 'l.unit_price', 'po.expected_date')
            ->havingRaw('l.qty_ordered - COALESCE(SUM(g.qty_accepted), 0) > 0')
            ->selectRaw('po.doc_number, s.name as supplier, p.code, p.name, u.code as uom, l.qty_ordered, COALESCE(SUM(g.qty_accepted), 0) as received, l.qty_ordered - COALESCE(SUM(g.qty_accepted), 0) as outstanding, l.unit_price, po.expected_date')
            ->orderBy('po.expected_date')->get()
            ->map(fn ($r) => ['doc_number' => $r->doc_number, 'supplier' => $r->supplier, 'code' => $r->code, 'name' => $r->name, 'uom' => $r->uom, 'qty_ordered' => $this->d($r->qty_ordered), 'received' => $this->d($r->received), 'outstanding' => $this->d($r->outstanding), 'outstanding_value' => bcmul($this->d($r->outstanding), $this->d($r->unit_price), 4), 'expected_date' => $r->expected_date])->all();

        return $this->result([$this->col('doc_number', 'PO'), $this->col('supplier', 'Supplier'), $this->col('code', 'Code'), $this->col('name', 'Product'), $this->col('uom', 'UOM'), $this->col('qty_ordered', 'Ordered', 'qty'), $this->col('received', 'Received', 'qty'), $this->col('outstanding', 'Outstanding', 'qty'), $this->col('outstanding_value', 'Outstanding value', 'money'), $this->col('expected_date', 'Expected', 'date')], $rows, ['qty_ordered', 'received', 'outstanding', 'outstanding_value']);
    }

    /**
     * @return array<string, mixed>
     */
    public function supplierPerformance(ReportContext $ctx): array
    {
        $rows = DB::table('suppliers as s')
            ->leftJoin('purchase_orders as po', fn ($j) => $j->on('po.supplier_id', '=', 's.id')->where('po.branch_id', $ctx->branchId)->whereBetween('po.created_at', [$ctx->fromDateTime(), $ctx->toDateTime()]))
            ->leftJoin('goods_receipts as g', fn ($j) => $j->on('g.purchase_order_id', '=', 'po.id')->where('g.status', 'POSTED'))
            ->leftJoin('goods_receipt_lines as gl', 'gl.goods_receipt_id', '=', 'g.id')
            ->where('s.organisation_id', $ctx->organisationId)
            ->groupBy('s.id', 's.code', 's.name', 's.status', 's.licence_expiry')
            ->selectRaw('s.code, s.name, s.status, s.licence_expiry, COUNT(DISTINCT po.id) as pos, COUNT(DISTINCT g.id) as grns, SUM(CASE WHEN g.received_at IS NOT NULL AND po.expected_date IS NOT NULL AND DATE(g.received_at) <= po.expected_date THEN 1 ELSE 0 END) as on_time, SUM(CASE WHEN g.received_at IS NOT NULL AND po.expected_date IS NOT NULL THEN 1 ELSE 0 END) as dated, COALESCE(SUM(gl.qty_delivered), 0) as delivered, COALESCE(SUM(gl.qty_rejected), 0) as rejected, AVG(CASE WHEN g.received_at IS NOT NULL AND po.sent_at IS NOT NULL THEN DATEDIFF(g.received_at, po.sent_at) END) as lead_days')
            ->havingRaw('COUNT(DISTINCT po.id) > 0')
            ->orderBy('s.name')->get()
            ->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'status' => $r->status, 'licence_expiry' => $r->licence_expiry, 'purchase_orders' => (int) $r->pos, 'receipts' => (int) $r->grns,
                'on_time_pct' => (int) $r->dated > 0 ? number_format($r->on_time / $r->dated * 100, 2, '.', '') : null,
                'rejection_pct' => (float) $r->delivered > 0 ? number_format($r->rejected / $r->delivered * 100, 2, '.', '') : '0.00',
                'avg_lead_days' => $r->lead_days !== null ? number_format((float) $r->lead_days, 1, '.', '') : null])->all();

        return $this->result([$this->col('code', 'Code'), $this->col('name', 'Supplier'), $this->col('status', 'Status'), $this->col('licence_expiry', 'Licence expiry', 'date'), $this->col('purchase_orders', 'POs', 'int'), $this->col('receipts', 'GRNs', 'int'), $this->col('on_time_pct', 'On time %', 'pct'), $this->col('rejection_pct', 'Rejected %', 'pct'), $this->col('avg_lead_days', 'Avg lead days', 'qty')], $rows, ['purchase_orders', 'receipts']);
    }

    /**
     * @return array<string, mixed>
     */
    public function spendBySupplier(ReportContext $ctx): array
    {
        $rows = DB::table('goods_receipts as g')->join('suppliers as s', 's.id', '=', 'g.supplier_id')->join('goods_receipt_lines as l', 'l.goods_receipt_id', '=', 'g.id')
            ->where('g.branch_id', $ctx->branchId)->where('g.status', 'POSTED')->whereBetween('g.received_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->groupBy('s.id', 's.code', 's.name')
            ->selectRaw('s.code, s.name, COUNT(DISTINCT g.id) as grns, SUM(l.qty_accepted * l.unit_cost) as spend, SUM(l.qty_accepted * COALESCE(l.landed_unit_cost, l.unit_cost)) as landed')
            ->orderByDesc('spend')->get()
            ->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'receipts' => (int) $r->grns, 'invoice_value' => $this->d($r->spend), 'landed_value' => $this->d($r->landed), 'landing_cost' => bcsub($this->d($r->landed), $this->d($r->spend), 4)])->all();

        return $this->result([$this->col('code', 'Code'), $this->col('name', 'Supplier'), $this->col('receipts', 'GRNs', 'int'), $this->col('invoice_value', 'Invoice value', 'money'), $this->col('landed_value', 'Landed value', 'money'), $this->col('landing_cost', 'Freight & clearing', 'money')], $rows, ['receipts', 'invoice_value', 'landed_value', 'landing_cost']);
    }

    /**
     * @return array<string, mixed>
     */
    public function priceVariance(ReportContext $ctx): array
    {
        $rows = DB::table('supplier_invoice_lines as il')->join('supplier_invoices as i', 'i.id', '=', 'il.supplier_invoice_id')
            ->join('purchase_order_lines as pl', 'pl.id', '=', 'il.purchase_order_line_id')->join('purchase_orders as po', 'po.id', '=', 'pl.purchase_order_id')
            ->join('products as p', 'p.id', '=', 'il.product_id')->join('suppliers as s', 's.id', '=', 'i.supplier_id')
            ->where('i.branch_id', $ctx->branchId)->whereBetween('i.invoice_date', [$ctx->from, $ctx->to])
            ->whereRaw('il.unit_price <> pl.unit_price')
            ->orderBy('i.invoice_date')
            ->get(['i.invoice_number', 'i.invoice_date', 'i.match_status', 's.name as supplier', 'po.doc_number as po', 'p.code', 'p.name', 'il.qty', 'pl.unit_price as po_price', 'il.unit_price as invoice_price'])
            ->map(function ($r) {
                $diff = bcsub($this->d($r->invoice_price), $this->d($r->po_price), 4);

                return ['invoice_number' => $r->invoice_number, 'invoice_date' => $r->invoice_date, 'match_status' => $r->match_status, 'supplier' => $r->supplier, 'po' => $r->po, 'code' => $r->code, 'name' => $r->name, 'qty' => $this->d($r->qty),
                    'po_price' => $this->d($r->po_price), 'invoice_price' => $this->d($r->invoice_price), 'variance_unit' => $diff, 'variance_pct' => $this->pct($diff, $this->d($r->po_price)), 'variance_line' => bcmul($diff, $this->d($r->qty), 4)];
            })->all();

        return $this->result([$this->col('invoice_number', 'Invoice'), $this->col('invoice_date', 'Date', 'date'), $this->col('match_status', 'Match'), $this->col('supplier', 'Supplier'), $this->col('po', 'PO'), $this->col('code', 'Code'), $this->col('name', 'Product'), $this->col('qty', 'Qty', 'qty'), $this->col('po_price', 'PO price', 'money'), $this->col('invoice_price', 'Invoice price', 'money'), $this->col('variance_unit', 'Variance/unit', 'money'), $this->col('variance_pct', 'Variance %', 'pct'), $this->col('variance_line', 'Variance (line)', 'money')], $rows, ['variance_line']);
    }

    /**
     * @return array<string, mixed>
     */
    public function matchingExceptions(ReportContext $ctx): array
    {
        $rows = DB::table('supplier_invoices as i')->join('suppliers as s', 's.id', '=', 'i.supplier_id')
            ->where('i.branch_id', $ctx->branchId)->whereIn('i.match_status', ['EXCEPTION', 'UNMATCHED'])
            ->orderBy('i.invoice_date')
            ->get(['i.doc_number', 'i.invoice_number', 'i.invoice_date', 'i.due_date', 's.name as supplier', 'i.grand_total', 'i.match_status', 'i.created_at'])
            ->map(fn ($r) => ['doc_number' => $r->doc_number, 'invoice_number' => $r->invoice_number, 'invoice_date' => $r->invoice_date, 'due_date' => $r->due_date, 'supplier' => $r->supplier, 'grand_total' => $this->d($r->grand_total), 'match_status' => $r->match_status, 'days_outstanding' => (int) Carbon::parse($r->created_at)->diffInDays(now())])->all();

        return $this->result([$this->col('doc_number', 'Ref'), $this->col('invoice_number', 'Supplier invoice'), $this->col('invoice_date', 'Date', 'date'), $this->col('due_date', 'Due', 'date'), $this->col('supplier', 'Supplier'), $this->col('grand_total', 'Value', 'money'), $this->col('match_status', 'Status'), $this->col('days_outstanding', 'Days', 'int')], $rows, ['grand_total']);
    }

    /**
     * @return array<string, mixed>
     */
    public function supplierLicences(ReportContext $ctx): array
    {
        $horizon = now()->addDays(90)->toDateString();
        $rows = DB::table('suppliers')->where('organisation_id', $ctx->organisationId)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('licence_expiry')->orWhere('licence_expiry', '<=', $horizon))
            ->orderBy('licence_expiry')->get(['code', 'name', 'status', 'licence_number', 'licence_expiry'])
            ->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'status' => $r->status, 'licence_number' => $r->licence_number, 'licence_expiry' => $r->licence_expiry,
                'state' => $r->licence_expiry === null ? 'MISSING' : ($r->licence_expiry < now()->toDateString() ? 'EXPIRED' : 'EXPIRING'),
                'days' => $r->licence_expiry ? (int) now()->startOfDay()->diffInDays(Carbon::parse($r->licence_expiry), false) : null])->all();

        return $this->result([$this->col('code', 'Code'), $this->col('name', 'Supplier'), $this->col('status', 'Status'), $this->col('licence_number', 'Licence'), $this->col('licence_expiry', 'Expiry', 'date'), $this->col('state', 'State'), $this->col('days', 'Days', 'int')], $rows);
    }
}
