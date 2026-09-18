<?php

namespace App\Services\Inventory;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use App\Models\Organisation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Part 7.1 / 12.4 — the reconciliation checks that must return zero rows:
 * every stock_balance equals SUM(stock_ledger) for its triple, every ledger
 * row has a balance row, and the ledger inventory value equals the GL
 * inventory account. Shared by the nightly `inventory:reconcile-ledger`
 * command and the System Health screen so both see the same truth.
 */
class LedgerReconciliation
{
    /**
     * Balance rows whose cached quantity differs from the ledger sum.
     *
     * @return Collection<int, \stdClass> rows of product_id, batch_id, store_id, cached, ledger
     */
    public function balanceDrift(): Collection
    {
        return DB::table('stock_balances as b')
            ->leftJoin('stock_ledgers as l', function ($join) {
                $join->on('l.product_id', '=', 'b.product_id')
                    ->on('l.batch_id', '=', 'b.batch_id')
                    ->on('l.store_id', '=', 'b.store_id');
            })
            ->groupBy('b.id', 'b.product_id', 'b.batch_id', 'b.store_id', 'b.qty_on_hand')
            ->havingRaw('COALESCE(SUM(l.qty_base), 0) <> b.qty_on_hand')
            ->selectRaw('b.product_id, b.batch_id, b.store_id, b.qty_on_hand as cached, COALESCE(SUM(l.qty_base), 0) as ledger')
            ->get();
    }

    /** Ledger rows with no matching balance row. */
    public function orphanLedgerRows(): int
    {
        return DB::table('stock_ledgers as l')
            ->leftJoin('stock_balances as b', function ($join) {
                $join->on('l.product_id', '=', 'b.product_id')
                    ->on('l.batch_id', '=', 'b.batch_id')
                    ->on('l.store_id', '=', 'b.store_id');
            })
            ->whereNull('b.id')
            ->count();
    }

    /** Negative balances — legal only from offline sync (Part 17.4). */
    public function negativeBalances(): int
    {
        return DB::table('stock_balances')->where('qty_on_hand', '<', 0)->count();
    }

    /**
     * Ledger inventory value against the GL inventory account, per organisation.
     *
     * @return list<array{organisation_id: string, organisation: string, ledger_value: string, gl_value: ?string, matches: bool}>
     */
    public function glComparison(): array
    {
        $rows = [];
        foreach (Organisation::all() as $org) {
            $ledgerValue = number_format((float) DB::table('stock_ledgers')->where('organisation_id', $org->id)->sum('total_cost'), 4, '.', '');
            $account = ChartOfAccount::where('organisation_id', $org->id)->where('system_role', 'INVENTORY')->first();
            $glValue = $account
                ? number_format((float) JournalEntryLine::where('account_id', $account->id)->selectRaw('SUM(debit_amount) - SUM(credit_amount) as net')->value('net'), 4, '.', '')
                : null;

            $rows[] = [
                'organisation_id' => (string) $org->id,
                'organisation' => (string) $org->name,
                'ledger_value' => $ledgerValue,
                'gl_value' => $glValue,
                'matches' => $glValue === null || bccomp($ledgerValue, $glValue, 4) === 0,
            ];
        }

        return $rows;
    }
}
