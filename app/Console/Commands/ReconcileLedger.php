<?php

namespace App\Console\Commands;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use App\Models\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Part 7.1 / 12.4 — the two reconciliation checks that must pass every
 * night: every stock_balance equals SUM(stock_ledger) for its triple, and
 * the inventory value in the ledger equals the GL inventory account. Any
 * row returned is a P1 incident, so the command exits non-zero.
 */
class ReconcileLedger extends Command
{
    protected $signature = 'inventory:reconcile-ledger';

    protected $description = 'Verify stock_balances against the stock ledger and the ledger value against GL inventory (nightly, Part 7.1 / 12.4)';

    public function handle(): int
    {
        $mismatches = DB::table('stock_balances as b')
            ->leftJoin('stock_ledgers as l', function ($join) {
                $join->on('l.product_id', '=', 'b.product_id')
                    ->on('l.batch_id', '=', 'b.batch_id')
                    ->on('l.store_id', '=', 'b.store_id');
            })
            ->groupBy('b.id', 'b.product_id', 'b.batch_id', 'b.store_id', 'b.qty_on_hand')
            ->havingRaw('COALESCE(SUM(l.qty_base), 0) <> b.qty_on_hand')
            ->selectRaw('b.product_id, b.batch_id, b.store_id, b.qty_on_hand as cached, COALESCE(SUM(l.qty_base), 0) as ledger')
            ->get();

        foreach ($mismatches as $row) {
            $this->error("DRIFT product {$row->product_id} batch {$row->batch_id} store {$row->store_id}: cached {$row->cached}, ledger {$row->ledger}");
        }

        $orphans = DB::table('stock_ledgers as l')
            ->leftJoin('stock_balances as b', function ($join) {
                $join->on('l.product_id', '=', 'b.product_id')
                    ->on('l.batch_id', '=', 'b.batch_id')
                    ->on('l.store_id', '=', 'b.store_id');
            })
            ->whereNull('b.id')
            ->count();
        if ($orphans > 0) {
            $this->error("{$orphans} ledger row(s) have no balance row.");
        }

        $negative = DB::table('stock_balances')->where('qty_on_hand', '<', 0)->count();
        if ($negative > 0) {
            $this->warn("{$negative} balance(s) are negative — legal only from offline sync (Part 17.4); investigate.");
        }

        $glWarnings = 0;
        foreach (Organisation::all() as $org) {
            $ledgerValue = number_format((float) DB::table('stock_ledgers')->where('organisation_id', $org->id)->sum('total_cost'), 4, '.', '');
            $account = ChartOfAccount::where('organisation_id', $org->id)->where('system_role', 'INVENTORY')->first();
            $glValue = $account
                ? number_format((float) JournalEntryLine::where('account_id', $account->id)->selectRaw('SUM(debit_amount) - SUM(credit_amount) as net')->value('net'), 4, '.', '')
                : null;

            if ($glValue !== null && bccomp($ledgerValue, $glValue, 4) !== 0) {
                $glWarnings++;
                $this->error("GL/ledger inventory mismatch for {$org->name}: ledger {$ledgerValue} vs GL 1200 {$glValue}");
            } else {
                $this->line("{$org->name}: ledger inventory value {$ledgerValue}".($glValue !== null ? " = GL {$glValue}" : ' (no GL inventory account)'));
            }
        }

        $failed = $mismatches->count() > 0 || $orphans > 0 || $glWarnings > 0;
        $this->{$failed ? 'error' : 'info'}($failed ? 'Reconciliation FAILED.' : 'Reconciliation passed: balances = ledger, ledger = GL.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
