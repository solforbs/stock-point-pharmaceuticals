<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Store;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Client request, 2026-10-26: the till lists only products its store can
 * sell now, not the whole catalogue.
 */
class PosInStockSearchTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['product.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_only_products_with_released_in_date_stock_in_the_store_are_listed(): void
    {
        $this->amox->uoms()->where('is_base', true)->update(['barcode' => '6001234500001']);
        $empty = Product::create(['organisation_id' => $this->org->id, 'code' => 'AMOX250', 'name' => 'Amoxicillin 250mg', 'base_uom_id' => $this->amox->base_uom_id, 'requires_batch' => true]);
        $otherStore = Store::create(['organisation_id' => $this->org->id, 'branch_id' => $this->branch->id, 'code' => 'SHOP', 'name' => 'Shop', 'store_type' => 'RETAIL', 'is_sellable' => true]);

        $this->getJson("/api/products?q=Amox&sellable_in_store={$this->store->id}")->assertOk()->assertJsonCount(0, 'data');

        $this->receive('B1', now()->addYear()->toDateString(), '100', '2.0000', 'TAB', release: false);
        $this->getJson("/api/products?q=Amox&sellable_in_store={$this->store->id}")->assertOk()->assertJsonCount(0, 'data');

        $this->receive('B2', now()->addYear()->toDateString(), '100', '2.0000');
        $this->getJson("/api/products?q=Amox&sellable_in_store={$this->store->id}")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->amox->id);
        $this->getJson("/api/products?barcode=6001234500001&sellable_in_store={$this->store->id}")->assertOk()->assertJsonCount(1, 'data');

        $this->getJson("/api/products?q=Amox&sellable_in_store={$otherStore->id}")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/products?q=Amox')->assertOk()->assertJsonFragment(['id' => $empty->id]);
    }

    public function test_an_expired_batch_does_not_count_as_stock(): void
    {
        $this->receive('OLD', now()->subDay()->toDateString(), '100', '2.0000');

        $this->getJson("/api/products?q=Amox&sellable_in_store={$this->store->id}")->assertOk()->assertJsonCount(0, 'data');
    }
}
