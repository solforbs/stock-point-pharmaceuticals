<?php

namespace Tests\Feature\Api;

use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * The master price list set from Stock on Hand: one price per product in
 * stock, which the POS then charges in every mode.
 */
class MasterPriceListTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        // 2,000 tablets at 2.00 each: 400.00 a box of 200.
        $this->receive('M1', now()->addYears(2)->toDateString(), '2000', '2.0000');
        $this->grantPermissions(['product.view', 'product.cost.view', 'stock.view', 'sale.create', 'sale.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_the_sheet_lists_what_is_in_stock_with_its_cost_and_current_price(): void
    {
        $this->getJson('/api/price-lists/master')
            ->assertOk()
            ->assertJsonPath('price_list', null)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.product_id', $this->amox->id)
            ->assertJsonPath('rows.0.uom_code', 'BOX')
            ->assertJsonPath('rows.0.unit_cost', '400.0000')
            ->assertJsonPath('rows.0.master_price', null)
            ->assertJsonPath('rows.0.price', '500.0000');
    }

    public function test_the_saved_price_is_what_the_pos_charges_in_every_mode(): void
    {
        $this->grantPermissions(['price.manage'], 'Pricing');

        $this->putJson('/api/price-lists/master', ['items' => [['product_id' => $this->amox->id, 'price' => '650']]])
            ->assertOk()
            ->assertJsonPath('products', 1)
            ->assertJsonPath('prices_changed', 4);

        // Retail counter, by the box and by the tablet.
        $this->postJson('/api/pricing/quote', [
            'sale_mode' => 'RETAIL',
            'store_id' => $this->store->id,
            'lines' => [
                ['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => '1'],
                ['product_id' => $this->amox->id, 'uom_id' => $this->uoms['TAB']->id, 'quantity' => '1'],
            ],
        ])->assertOk()
            ->assertJsonPath('lines.0.landing_price', '650.0000')
            ->assertJsonPath('lines.1.landing_price', '3.2500');

        // Wholesale quotation.
        $this->postJson('/api/quotations', [
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'valid_until' => now()->addDays(7)->toDateString(),
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => '2']],
        ])->assertCreated()->assertJsonPath('grand_total', '1300.0000');

        $this->assertSame('3.2500', (string) $this->amox->fresh()->default_price);
        $this->getJson('/api/price-lists/master')->assertJsonPath('rows.0.master_price', '650.0000');

        // Saving the same price again changes nothing.
        $this->putJson('/api/price-lists/master', ['items' => [['product_id' => $this->amox->id, 'price' => '650.00']]])
            ->assertOk()->assertJsonPath('prices_changed', 0);
    }

    public function test_the_saved_list_downloads_as_a_pdf(): void
    {
        $this->getJson('/api/price-lists/master/pdf')->assertStatus(422);

        $this->grantPermissions(['price.manage'], 'Pricing');
        $this->putJson('/api/price-lists/master', ['items' => [['product_id' => $this->amox->id, 'price' => '650']]])->assertOk();

        $this->get('/api/price-lists/master/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_saving_needs_the_price_manage_permission(): void
    {
        $this->putJson('/api/price-lists/master', ['items' => [['product_id' => $this->amox->id, 'price' => '650']]])
            ->assertForbidden();
    }
}
