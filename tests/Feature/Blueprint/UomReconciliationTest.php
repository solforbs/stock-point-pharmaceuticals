<?php

namespace Tests\Feature\Blueprint;

use App\Services\Inventory\StockLedgerService;
use Illuminate\Support\Str;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Blueprint acceptance test AT-11 / AT-10: "Buy 3 cartons (6,000 tablets),
 * sell 4 boxes (800) and 7 strips (70), adjust −13 tablets. Ledger sum:
 * 6,000 − 800 − 70 − 13 = 5,117. stock_balance.qty_on_hand = 5,117. Zero
 * rounding error."
 */
class UomReconciliationTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_conversions_reconcile_exactly_across_purchase_sale_and_adjustment(): void
    {
        // Buy 3 cartons at KES 4,100 per carton (= KES 2.05 per tablet).
        $batch = $this->receive('B-2405', now()->addMonths(18)->toDateString(), '3', '4100.0000', 'CTN');

        $this->assertSame('6000.0000', $this->ledgerSum($batch->id), 'A carton purchase must be stored as 2,000 base units each');
        $this->assertSame('2.0500', (string) $batch->fresh()->unit_cost, 'Batch cost must be held per base unit, not per purchase UOM');

        // Sell 4 boxes and 7 strips in one sale.
        $sale = $this->checkout([
            $this->saleLine('BOX', '4', '500.0000'),
            $this->saleLine('STR', '7', '28.0000'),
        ], [['method' => 'CASH', 'amount' => '2196.0000']]);

        $this->assertSame('800.0000', (string) $sale->lines[0]->qty_base);
        $this->assertSame('70.0000', (string) $sale->lines[1]->qty_base);

        // Adjust −13 tablets through the ledger (the only permitted write path).
        app(StockLedgerService::class)->post([
            'txn_type' => 'ADJUSTMENT_DOWN',
            'product_id' => $this->amox->id,
            'batch_id' => $batch->id,
            'store_id' => $this->store->id,
            'qty_base' => '-13.0000',
            'unit_cost' => '2.0500',
            'source_doc_type' => 'stock_adjustment',
            'source_doc_id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->assertSame('5117.0000', $this->ledgerSum($batch->id));
        $this->assertSame('5117.0000', (string) $this->balance($batch->id)->qty_on_hand);

        // Part 5.4 — largest-unit-first display of the remaining stock.
        $this->assertSame(
            ['CTN' => '2', 'BOX' => '5', 'STR' => '11', 'TAB' => '7'],
            $this->amox->formatQuantity('5117.0000'),
        );
    }
}
