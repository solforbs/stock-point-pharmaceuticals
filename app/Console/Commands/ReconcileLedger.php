<?php

namespace App\Console\Commands;

use App\Services\Inventory\LedgerReconciliation;
use Illuminate\Console\Command;

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

    public function handle(LedgerReconciliation $reconciliation): int
    {
        $mismatches = $reconciliation->balanceDrift();

        foreach ($mismatches as $row) {
            $this->error("DRIFT product {$row->product_id} batch {$row->batch_id} store {$row->store_id}: cached {$row->cached}, ledger {$row->ledger}");
        }

        $orphans = $reconciliation->orphanLedgerRows();
        if ($orphans > 0) {
            $this->error("{$orphans} ledger row(s) have no balance row.");
        }

        $negative = $reconciliation->negativeBalances();
        if ($negative > 0) {
            $this->warn("{$negative} balance(s) are negative — legal only from offline sync (Part 17.4); investigate.");
        }

        $glWarnings = 0;
        foreach ($reconciliation->glComparison() as $gl) {
            if (! $gl['matches']) {
                $glWarnings++;
                $this->error("GL/ledger inventory mismatch for {$gl['organisation']}: ledger {$gl['ledger_value']} vs GL 1200 {$gl['gl_value']}");
            } else {
                $this->line("{$gl['organisation']}: ledger inventory value {$gl['ledger_value']}".($gl['gl_value'] !== null ? " = GL {$gl['gl_value']}" : ' (no GL inventory account)'));
            }
        }

        $failed = $mismatches->count() > 0 || $orphans > 0 || $glWarnings > 0;
        $this->{$failed ? 'error' : 'info'}($failed ? 'Reconciliation FAILED.' : 'Reconciliation passed: balances = ledger, ledger = GL.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
