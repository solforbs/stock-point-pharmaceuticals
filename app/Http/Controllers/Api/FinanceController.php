<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends ApiController
{
    /** GET /api/finance/trial-balance — derived from posted journals only (Part 20.1). */
    public function trialBalance(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'report.financial.view');
        $filters = $request->validate(['as_of' => ['nullable', 'date']]);
        $asOf = $filters['as_of'] ?? now()->toDateString();

        $rows = DB::table('chart_of_accounts as a')
            ->where('a.organisation_id', $this->organisationId($request))
            ->leftJoin('journal_entry_lines as l', 'l.account_id', '=', 'a.id')
            ->leftJoin('journal_entries as j', function ($join) use ($asOf) {
                $join->on('j.id', '=', 'l.journal_id')->whereDate('j.entry_date', '<=', $asOf);
            })
            ->groupBy('a.id', 'a.code', 'a.name', 'a.account_type', 'a.system_role')
            ->selectRaw('a.code, a.name, a.account_type, a.system_role, COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.debit_amount END), 0) as debit, COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.credit_amount END), 0) as credit')
            ->orderBy('a.code')
            ->get()
            ->map(fn ($r) => [
                'code' => $r->code, 'name' => $r->name, 'account_type' => $r->account_type, 'system_role' => $r->system_role,
                'debit' => number_format((float) $r->debit, 4, '.', ''), 'credit' => number_format((float) $r->credit, 4, '.', ''),
                'net' => number_format((float) $r->debit - (float) $r->credit, 4, '.', ''),
            ]);

        $totalDebit = $rows->reduce(fn ($c, $r) => bcadd($c, $r['debit'], 4), '0.0000');
        $totalCredit = $rows->reduce(fn ($c, $r) => bcadd($c, $r['credit'], 4), '0.0000');

        return response()->json([
            'as_of' => $asOf,
            'accounts' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced' => bccomp($totalDebit, $totalCredit, 4) === 0,
        ]);
    }

    public function journals(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'journal.post');

        $filters = $request->validate([
            'source_doc_type' => ['nullable', 'string', 'max:40'],
            'period_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ]);

        return response()->json(
            JournalEntry::where('organisation_id', $this->organisationId($request))
                ->when($filters['source_doc_type'] ?? null, fn ($q, $v) => $q->where('source_doc_type', $v))
                ->when($filters['period_id'] ?? null, fn ($q, $v) => $q->where('period_id', $v))
                ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('entry_date', '>=', $v))
                ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('entry_date', '<=', $v))
                ->with('lines.account:id,code,name,system_role')
                ->orderByDesc('posted_at')
                ->paginate($filters['per_page'] ?? 25)
        );
    }

    public function periods(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'report.financial.view');

        return response()->json(FinancialPeriod::where('organisation_id', $this->organisationId($request))->orderByDesc('start_date')->get());
    }

    /**
     * POST /api/finance/periods/{id}/close — Part 12.4. The closing checklist
     * that can be verified from data is verified: every journal in the
     * period balances and the ledger reconciles.
     */
    public function closePeriod(Request $request, string $period): JsonResponse
    {
        $this->requirePermission($request, 'period.close');

        $period = FinancialPeriod::where('organisation_id', $this->organisationId($request))->findOrFail($period);
        if ($period->status !== 'OPEN') {
            return $this->error('INVALID_STATE', "Period {$period->fiscal_year}/{$period->period_no} is {$period->status}.", 409);
        }

        $unbalanced = JournalEntry::where('period_id', $period->id)->get()->reject(fn (JournalEntry $j) => $j->isBalanced())->count();
        $driftRows = DB::table('stock_balances as b')
            ->leftJoin('stock_ledgers as l', fn ($j) => $j->on('l.product_id', '=', 'b.product_id')->on('l.batch_id', '=', 'b.batch_id')->on('l.store_id', '=', 'b.store_id'))
            ->groupBy('b.id', 'b.qty_on_hand')->havingRaw('COALESCE(SUM(l.qty_base), 0) <> b.qty_on_hand')->select('b.id');
        $drift = DB::query()->fromSub($driftRows, 'drift')->count();

        if ($unbalanced > 0 || $drift > 0) {
            return $this->error('CLOSE_CHECKLIST_FAILED', 'The period cannot close until every journal balances and the stock ledger reconciles.', 422, [
                'unbalanced_journals' => $unbalanced, 'stock_balance_drift_rows' => $drift,
            ]);
        }

        $period->update(['status' => 'CLOSED', 'closed_by' => $request->user()->id, 'closed_at' => now()]);
        AuditLog::record('PERIOD_CLOSED', 'financial_period', $period->id, ['reference' => "{$period->fiscal_year}/{$period->period_no}"]);

        return response()->json($period->fresh());
    }
}
