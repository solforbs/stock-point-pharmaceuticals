<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QualityReports
{
    use FormatsReports;

    /**
     * @return array<string, mixed>
     */
    public function quarantineAgeing(ReportContext $ctx): array
    {
        $rows = DB::table('product_batches as pb')->join('products as p', 'p.id', '=', 'pb.product_id')
            ->join('stock_balances as b', 'b.batch_id', '=', 'pb.id')->join('stores as s', 's.id', '=', 'b.store_id')
            ->whereIn('b.store_id', $ctx->storeIds)->where('b.qty_on_hand', '>', 0)
            ->where(fn ($q) => $q->whereIn('pb.status', ['QUARANTINED', 'PENDING_QC', 'RECALLED']))
            ->orderBy('pb.updated_at')
            ->get(['p.code', 'p.name', 'pb.batch_number', 'pb.expiry_date', 'pb.status', 's.code as store', 'b.qty_on_hand', 'pb.landed_unit_cost', 'pb.updated_at'])
            ->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'batch' => $r->batch_number, 'expiry_date' => $r->expiry_date, 'status' => $r->status, 'store' => $r->store,
                'qty_base' => $this->d($r->qty_on_hand), 'value_at_cost' => bcmul($this->d($r->qty_on_hand), $this->d($r->landed_unit_cost), 4), 'since' => $r->updated_at, 'days_held' => (int) Carbon::parse($r->updated_at)->diffInDays(now())])->all();

        return $this->result([$this->col('code', 'Code'), $this->col('name', 'Product'), $this->col('batch', 'Batch'), $this->col('expiry_date', 'Expiry', 'date'), $this->col('status', 'Status'), $this->col('store', 'Store'), $this->col('qty_base', 'Qty', 'qty'), $this->col('value_at_cost', 'Value at cost', 'money'), $this->col('since', 'Since', 'datetime'), $this->col('days_held', 'Days held', 'int')], $rows, ['qty_base', 'value_at_cost']);
    }

    /**
     * @return array<string, mixed>
     */
    public function recallEffectiveness(ReportContext $ctx): array
    {
        $rows = DB::table('recalls as r')->leftJoin('recall_batches as rb', 'rb.recall_id', '=', 'r.id')
            ->where('r.organisation_id', $ctx->organisationId)
            ->groupBy('r.id', 'r.doc_number', 'r.status', 'r.source', 'r.external_reference', 'r.initiated_at', 'r.closed_at', 'r.effectiveness_pct')
            ->selectRaw('r.doc_number, r.status, r.source, r.external_reference, r.initiated_at, r.closed_at, r.effectiveness_pct, COUNT(rb.id) as batches, COALESCE(SUM(rb.distributed_qty), 0) as distributed, COALESCE(SUM(rb.recovered_qty), 0) as recovered, COALESCE(SUM(rb.disposed_qty), 0) as disposed')
            ->orderByDesc('r.initiated_at')->get()
            ->map(fn ($r) => ['doc_number' => $r->doc_number, 'status' => $r->status, 'source' => $r->source, 'reference' => $r->external_reference, 'initiated_at' => $r->initiated_at, 'closed_at' => $r->closed_at,
                'batches' => (int) $r->batches, 'distributed' => $this->d($r->distributed), 'recovered' => $this->d($r->recovered), 'outstanding' => bcsub($this->d($r->distributed), $this->d($r->recovered), 4), 'disposed' => $this->d($r->disposed),
                'effectiveness_pct' => $this->pct($this->d($r->recovered), $this->d($r->distributed)), 'days_open' => (int) Carbon::parse($r->initiated_at)->diffInDays($r->closed_at ? Carbon::parse($r->closed_at) : now())])->all();

        return $this->result([$this->col('doc_number', 'Recall'), $this->col('status', 'Status'), $this->col('source', 'Source'), $this->col('reference', 'Reference'), $this->col('initiated_at', 'Initiated', 'datetime'), $this->col('closed_at', 'Closed', 'datetime'), $this->col('batches', 'Batches', 'int'), $this->col('distributed', 'Distributed', 'qty'), $this->col('recovered', 'Recovered', 'qty'), $this->col('outstanding', 'Outstanding', 'qty'), $this->col('disposed', 'Disposed', 'qty'), $this->col('effectiveness_pct', 'Effectiveness %', 'pct'), $this->col('days_open', 'Days', 'int')], $rows, ['batches', 'distributed', 'recovered', 'outstanding', 'disposed']);
    }

    /**
     * @return array<string, mixed>
     */
    public function wasteByReason(ReportContext $ctx): array
    {
        $rows = DB::table('waste_disposals as w')->leftJoin('waste_disposal_lines as l', 'l.waste_disposal_id', '=', 'w.id')
            ->where('w.branch_id', $ctx->branchId)->where('w.status', 'POSTED')->whereBetween('w.posted_at', [$ctx->fromDateTime(), $ctx->toDateTime()])
            ->groupBy('w.reason')->selectRaw('w.reason, COUNT(DISTINCT w.id) as disposals, COALESCE(SUM(l.qty_base), 0) as qty, COALESCE(SUM(l.line_value), 0) as value, SUM(CASE WHEN w.certificate_reference IS NULL OR w.certificate_reference = "" THEN 1 ELSE 0 END) as missing_certificates')
            ->orderByDesc('value')->get()
            ->map(fn ($r) => ['reason' => $r->reason, 'disposals' => (int) $r->disposals, 'qty_base' => $this->d($r->qty), 'value' => $this->d($r->value), 'missing_certificates' => (int) $r->missing_certificates])->all();

        return $this->result([$this->col('reason', 'Reason'), $this->col('disposals', 'Disposals', 'int'), $this->col('qty_base', 'Qty', 'qty'), $this->col('value', 'Value written off', 'money'), $this->col('missing_certificates', 'Without certificate', 'int')], $rows, ['disposals', 'qty_base', 'value', 'missing_certificates']);
    }

    /**
     * @return array<string, mixed>
     */
    public function licenceCalendar(ReportContext $ctx): array
    {
        $horizon = now()->addDays(180)->toDateString();
        $rows = [];
        foreach (DB::table('suppliers')->where('organisation_id', $ctx->organisationId)->where('is_active', true)->whereNotNull('licence_expiry')->where('licence_expiry', '<=', $horizon)->get(['name', 'licence_number', 'licence_expiry']) as $s) {
            $rows[] = ['party_type' => 'SUPPLIER', 'party' => $s->name, 'document' => 'Trading licence '.$s->licence_number, 'expiry_date' => $s->licence_expiry];
        }
        foreach (DB::table('customers')->where('organisation_id', $ctx->organisationId)->whereNotNull('exemption_expiry')->where('exemption_expiry', '<=', $horizon)->get(['name', 'exemption_ref', 'exemption_expiry']) as $c) {
            $rows[] = ['party_type' => 'CUSTOMER', 'party' => $c->name, 'document' => 'Tax exemption '.$c->exemption_ref, 'expiry_date' => $c->exemption_expiry];
        }
        usort($rows, fn ($a, $b) => strcmp((string) $a['expiry_date'], (string) $b['expiry_date']));
        foreach ($rows as &$row) {
            $row['days'] = (int) now()->startOfDay()->diffInDays(Carbon::parse($row['expiry_date']), false);
            $row['state'] = $row['days'] < 0 ? 'EXPIRED' : ($row['days'] <= 30 ? 'CRITICAL' : 'UPCOMING');
        }

        return $this->result([$this->col('party_type', 'Type'), $this->col('party', 'Party'), $this->col('document', 'Document'), $this->col('expiry_date', 'Expiry', 'date'), $this->col('days', 'Days', 'int'), $this->col('state', 'State')], $rows);
    }
}
