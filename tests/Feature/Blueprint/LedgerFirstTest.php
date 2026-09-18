<?php

namespace Tests\Feature\Blueprint;

use App\Models\StockLedger;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Correction #1 (ledger-first) and acceptance test AT-2: every
 * stock_balance row equals SUM(stock_ledger.qty_base) for its triple, and
 * ledger rows are append-only.
 */
class LedgerFirstTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_balances_reconcile_to_the_ledger_after_receipts_and_sales(): void
    {
        $this->receive('B-2401', now()->addDays(195)->toDateString(), '150', '2.0500');
        $this->receive('B-2405', now()->addDays(348)->toDateString(), '1800', '2.1800');

        $this->checkout([$this->saleLine('BOX', '1', '500.0000')], [['method' => 'CASH', 'amount' => '500.0000']]);
        $this->checkout([$this->saleLine('STR', '3', '28.0000')], [['method' => 'CASH', 'amount' => '84.0000']]);

        // Part 7.1's nightly reconciliation query must return zero rows.
        $mismatches = DB::table('stock_balances as b')
            ->leftJoin('stock_ledgers as l', function ($join) {
                $join->on('l.product_id', '=', 'b.product_id')
                    ->on('l.batch_id', '=', 'b.batch_id')
                    ->on('l.store_id', '=', 'b.store_id');
            })
            ->groupBy('b.id', 'b.qty_on_hand')
            ->havingRaw('COALESCE(SUM(l.qty_base), 0) <> b.qty_on_hand')
            ->select('b.id')
            ->get();

        $this->assertCount(0, $mismatches, 'stock_balance drifted from the ledger');
        $this->assertSame('1720.0000', $this->ledgerSum(), '150 + 1800 − 200 − 30');
    }

    public function test_ledger_rows_cannot_be_updated_or_deleted(): void
    {
        $batch = $this->receive('B-2401', now()->addDays(195)->toDateString(), '150', '2.0500');
        $row = StockLedger::where('batch_id', $batch->id)->firstOrFail();

        $this->expectException(\LogicException::class);
        $row->update(['qty_base' => '999']);
    }

    public function test_ledger_rows_cannot_be_deleted(): void
    {
        $batch = $this->receive('B-2401', now()->addDays(195)->toDateString(), '150', '2.0500');
        $row = StockLedger::where('batch_id', $batch->id)->firstOrFail();

        $this->expectException(\LogicException::class);
        $row->delete();
    }
}
