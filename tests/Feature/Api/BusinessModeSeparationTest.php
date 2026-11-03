<?php

namespace Tests\Feature\Api;

use App\Models\Store;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Retail and wholesale under one roof are two businesses: a session that
 * chose one (X-Business-Mode) sees only that business's stores, stock and
 * sales. The wholesale warehouse's stock is not on the retail shelf.
 */
class BusinessModeSeparationTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('B1', now()->addYears(2)->toDateString(), '2000', '2.0000');
        $this->grantPermissions(['sale.create', 'sale.view', 'stock.view', 'product.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_the_retail_session_does_not_see_the_wholesale_stores_stock(): void
    {
        $this->assertSame('WHOLESALE', $this->store->fresh()->business_mode, 'a MAIN store belongs to the wholesale business');

        $this->getJson('/api/inventory/stock', ['X-Business-Mode' => 'WHOLESALE'])->assertOk()->assertJsonPath('data.0.store_id', $this->store->id);
        $this->getJson('/api/inventory/stock', ['X-Business-Mode' => 'RETAIL'])->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/inventory/stock')->assertOk()->assertJsonPath('data.0.store_id', $this->store->id);

        $retail = $this->getJson('/api/products?q='.$this->amox->code, ['X-Business-Mode' => 'RETAIL'])->assertOk()->json('data.0.stock');
        $this->assertEquals(0, (float) $retail['on_hand']);
        $wholesale = $this->getJson('/api/products?q='.$this->amox->code, ['X-Business-Mode' => 'WHOLESALE'])->assertOk()->json('data.0.stock');
        $this->assertEquals(2000, (float) $wholesale['on_hand']);
    }

    public function test_the_retail_session_lists_none_of_the_wholesale_sales(): void
    {
        $quote = $this->postJson('/api/pricing/quote', [
            'sale_mode' => 'RETAIL', 'store_id' => $this->store->id,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => '1']],
        ])->assertOk()->json();
        $this->postJson('/api/sales/checkout', [
            'quote_id' => $quote['quote_id'], 'store_id' => $this->store->id,
            'payments' => [['method' => 'CASH', 'amount' => $quote['totals']['grand_total']]],
        ], ['Idempotency-Key' => 'mode-1'])->assertCreated();

        $this->getJson('/api/sales', ['X-Business-Mode' => 'WHOLESALE'])->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/sales', ['X-Business-Mode' => 'RETAIL'])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_each_session_dashboard_counts_only_its_own_business_today_and_over_seven_days(): void
    {
        Store::ensureRetailStoreFor($this->branch);
        $retailStore = Store::where('branch_id', $this->branch->id)->where('business_mode', 'RETAIL')->firstOrFail();
        $this->checkout([$this->saleLine('TAB', '10', '5.0000')], [['method' => 'CASH', 'amount' => '50.0000']]);
        // The dashboard reads posted sales only, so a sale moved onto the retail store stands in for a retail one.
        $retailSale = $this->checkout([$this->saleLine('TAB', '4', '5.0000')], [['method' => 'CASH', 'amount' => '20.0000']]);
        $retailSale->forceFill(['store_id' => $retailStore->id, 'posted_at' => now()->subDays(2)])->save();

        $wholesale = $this->getJson('/api/dashboard/summary', ['X-Business-Mode' => 'WHOLESALE'])->assertOk()
            ->assertJsonPath('sales_today.count', 1)
            ->assertJsonPath('sales_7d.count', 1)
            ->json();
        $retail = $this->getJson('/api/dashboard/summary', ['X-Business-Mode' => 'RETAIL'])->assertOk()
            ->assertJsonPath('sales_today.count', 0)
            ->assertJsonPath('sales_7d.count', 1)
            ->assertJsonPath('sales_7d.by_day.4.count', 1)
            ->json();

        $this->assertSame(bcadd((string) $retailSale->grand_total, '0', 4), $retail['sales_7d']['total']);
        $this->assertNotSame($wholesale['sales_7d']['total'], $retail['sales_7d']['total']);
    }

    public function test_a_branch_in_one_business_only_ignores_the_mode(): void
    {
        $this->branch->forceFill(['wholesale_enabled' => false])->save();

        $this->getJson('/api/inventory/stock', ['X-Business-Mode' => 'RETAIL'])->assertOk()->assertJsonPath('data.0.store_id', $this->store->id);
    }

    public function test_a_branch_trading_in_both_gets_an_empty_retail_store_once(): void
    {
        Store::ensureRetailStoreFor($this->branch);
        Store::ensureRetailStoreFor($this->branch);

        $retail = Store::where('branch_id', $this->branch->id)->where('business_mode', 'RETAIL')->get();
        $this->assertCount(1, $retail);
        $this->assertTrue($retail->first()->is_sellable);

        $stores = collect($this->getJson('/api/stores')->assertOk()->json())->keyBy('code');
        $this->assertSame('RETAIL', $stores['RETAIL']['business_mode']);
        $this->assertSame('WHOLESALE', $stores['MAIN']['business_mode']);
    }
}
