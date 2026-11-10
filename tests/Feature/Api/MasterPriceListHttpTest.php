<?php

namespace Tests\Feature\Api;

use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

class MasterPriceListHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    private Product $notStocked;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('L1', now()->addYears(2)->toDateString(), '100', '2.0000');
        $this->notStocked = Product::create([
            'organisation_id' => $this->org->id, 'code' => 'PCM500', 'name' => 'Paracetamol 500mg',
            'base_uom_id' => $this->uoms['TAB']->id, 'default_price' => '1.0000', 'is_active' => true,
        ]);
    }

    public function test_the_list_offers_only_products_in_stock_with_their_current_prices(): void
    {
        $this->grantPermissions(['product.view']);
        Sanctum::actingAs($this->user);

        $data = $this->getJson('/api/inventory/master-price-list')->assertOk()->assertJsonPath('list', null)->json();

        $this->assertSame([$this->amox->id], array_column($data['products'], 'id'));
        $this->assertSame('2.5000', $data['products'][0]['default_price']);
        $this->assertNull($data['products'][0]['master_price']);
        $this->assertSame('TAB', $data['products'][0]['base_uom']);
        $this->assertEquals(100, (float) $data['products'][0]['qty_on_hand']);
    }

    public function test_saving_needs_the_price_management_permission(): void
    {
        $this->grantPermissions(['product.view']);
        Sanctum::actingAs($this->user);

        $this->putJson('/api/inventory/master-price-list', ['prices' => [['product_id' => $this->amox->id, 'price' => '3.25']]])->assertForbidden();
        $this->assertSame(0, PriceList::where('is_master', true)->count());
    }

    public function test_a_saved_price_is_what_the_pos_charges_ahead_of_a_mode_list(): void
    {
        $this->grantPermissions(['product.view', 'price.manage', 'sale.create']);
        Sanctum::actingAs($this->user);
        $retail = PriceList::create(['organisation_id' => $this->org->id, 'code' => 'RTL', 'name' => 'Retail', 'sale_mode' => 'RETAIL', 'currency' => 'KES', 'effective_from' => now()->subMonth()->toDateString(), 'is_active' => true]);
        ProductPrice::create(['price_list_id' => $retail->id, 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'factor_type' => 'FIXED', 'unit_price' => '900', 'effective_from' => now()->subMonth()->toDateString()]);
        $this->assertSame('900.0000', $this->retailBoxPrice());

        $this->putJson('/api/inventory/master-price-list', ['prices' => [['product_id' => $this->amox->id, 'price' => '3.25']]])
            ->assertOk()->assertJsonPath('changed', 1)->assertJsonPath('unchanged', 0)->assertJsonPath('list.code', 'MASTER');

        // A box is 200 tablets: 200 x 3.25.
        $this->assertSame('650.0000', $this->retailBoxPrice());
        $this->assertSame('3.2500', (string) $this->amox->fresh()->default_price);
        $this->getJson('/api/inventory/master-price-list')->assertOk()->assertJsonPath('products.0.master_price', '3.2500');

        // Saving the same prices again writes nothing.
        $this->putJson('/api/inventory/master-price-list', ['prices' => [['product_id' => $this->amox->id, 'price' => '3.2500']]])
            ->assertOk()->assertJsonPath('changed', 0)->assertJsonPath('unchanged', 1);
        $this->assertSame(1, ProductPrice::where('price_list_id', PriceList::where('is_master', true)->value('id'))->count());

        // Switching the master list off hands the price back to the mode list.
        PriceList::where('is_master', true)->update(['is_active' => false]);
        $this->assertSame('900.0000', $this->retailBoxPrice());
    }

    public function test_a_price_must_be_above_zero(): void
    {
        $this->grantPermissions(['product.view', 'price.manage']);
        Sanctum::actingAs($this->user);

        $this->putJson('/api/inventory/master-price-list', ['prices' => [['product_id' => $this->amox->id, 'price' => '0']]])
            ->assertStatus(422)->assertJsonValidationErrors('prices.0.price');
    }

    public function test_the_pdf_is_the_saved_list(): void
    {
        $this->grantPermissions(['product.view', 'price.manage']);
        Sanctum::actingAs($this->user);

        $this->get('/api/inventory/master-price-list/pdf')->assertStatus(422)->assertJsonPath('error.code', 'MASTER_PRICE_LIST_NOT_SAVED');

        $this->putJson('/api/inventory/master-price-list', ['prices' => [['product_id' => $this->amox->id, 'price' => '3.25']]])->assertOk();
        $this->get('/api/inventory/master-price-list/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    private function retailBoxPrice(): string
    {
        return (string) $this->postJson('/api/pricing/quote', [
            'sale_mode' => 'RETAIL', 'store_id' => $this->store->id,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => '1']],
        ])->assertOk()->json('lines.0.unit_price');
    }
}
