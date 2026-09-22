<?php

namespace Tests\Feature\Api;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\NumberSequence;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductPrice;
use App\Models\ProductUom;
use App\Services\Procurement\GoodsReceiptService;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * The POS line's side panel: buying price, trade and retail per unit and
 * per pack, VAT treatment, the never-below price, the usual selling price
 * and in-stock alternatives, and the same buying price and store split on
 * the product master list.
 */
class ProductInsightHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('B-INS', now()->addYear()->toDateString(), '1000', '2.0000');
        $this->vat16();
        $this->discountPolicy();
        $this->priceBox('RETAIL', '520');
        $this->priceBox('WHOLESALE', '450');
        Sanctum::actingAs($this->user);
    }

    public function test_a_cost_viewer_sees_buying_trade_retail_tax_and_the_floor_per_unit(): void
    {
        $this->grantPermissions(['product.view', 'product.cost.view']);

        $response = $this->getJson("/api/products/{$this->amox->id}/insight?store_id={$this->store->id}")->assertOk();

        $response->assertJsonPath('tax.treatment', 'STANDARD')
            ->assertJsonPath('tax.rate_pct', '16.000')
            ->assertJsonPath('buying.last_purchase.unit_cost_per_base', '2.0000')
            ->assertJsonPath('buying.last_purchase.supplier', 'Pharma Distributors Ltd');

        $box = collect($response->json('uoms'))->firstWhere('uom_code', 'BOX');
        $this->assertSame('520.0000', $box['retail']['unit_price']);
        $this->assertSame('603.2000', $box['retail']['gross_price'], 'retail plus 16% VAT');
        $this->assertSame('450.0000', $box['trade']['unit_price']);
        $this->assertSame('400.0000', $box['unit_cost'], '200 tablets at 2.00');
        $this->assertSame('30.00', $box['retail_markup_pct']);
        $this->assertSame('425.5300', $box['retail']['floor_price'], '400 at a 6% minimum margin');
    }

    public function test_cost_and_buying_price_stay_hidden_without_the_cost_permission(): void
    {
        $this->grantPermissions(['product.view']);

        $response = $this->getJson("/api/products/{$this->amox->id}/insight?store_id={$this->store->id}")->assertOk();

        $response->assertJsonPath('buying', null);
        $box = collect($response->json('uoms'))->firstWhere('uom_code', 'BOX');
        $this->assertNull($box['unit_cost']);
        $this->assertNull($box['retail_markup_pct']);
        $this->assertSame('425.5300', $box['retail']['floor_price'], 'the never-below price is for every cashier');
    }

    public function test_the_usual_price_is_the_most_frequent_price_this_customer_paid(): void
    {
        $this->grantPermissions(['product.view']);
        $wholesale = ['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id];
        $this->checkout([$this->saleLine('BOX', '1', '440.0000')], [], $wholesale);
        $this->checkout([$this->saleLine('BOX', '1', '440.0000')], [], $wholesale);
        $this->checkout([$this->saleLine('BOX', '1', '445.0000')], [], $wholesale);
        $this->checkout([$this->saleLine('BOX', '1', '520.0000')], [['method' => 'CASH', 'amount' => '520.0000']]);

        $response = $this->getJson("/api/products/{$this->amox->id}/insight?store_id={$this->store->id}&customer_id={$this->customer->id}")->assertOk();

        $response->assertJsonPath('usual_price.customer.unit_price', '440.0000')
            ->assertJsonPath('usual_price.customer.uom_code', 'BOX')
            ->assertJsonPath('usual_price.customer.times', 2)
            ->assertJsonPath('usual_price.customer.last_price', '445.0000')
            ->assertJsonPath('usual_price.everyone.unit_price', '440.0000');
    }

    public function test_alternatives_are_in_stock_products_of_the_same_generic_first(): void
    {
        $this->grantPermissions(['product.view']);
        $inStock = $this->otherProduct('AMOX250', 'Amoxil 250mg Capsules', 'Amoxicillin');
        $this->stockOf($inStock, 'B-ALT', '50');
        $this->otherProduct('AMOX-SUSP', 'Amoxicillin Suspension', 'Amoxicillin');
        $this->otherProduct('PCM500', 'Paracetamol 500mg', 'Paracetamol');

        $alternatives = $this->getJson("/api/products/{$this->amox->id}/insight?store_id={$this->store->id}")
            ->assertOk()->json('alternatives');

        $this->assertCount(1, $alternatives, 'only the in-stock substitute, never an empty shelf or a different molecule');
        $this->assertSame('AMOX250', $alternatives[0]['product']['code']);
        $this->assertSame('SAME_GENERIC', $alternatives[0]['match']);
        $this->assertSame('50.0000', $alternatives[0]['free_in_store']);
        $this->assertNotEmpty($alternatives[0]['product']['uoms'], 'enough to add it straight to the cart');
    }

    public function test_the_store_must_belong_to_the_active_branch(): void
    {
        $this->grantPermissions(['product.view']);

        $this->getJson("/api/products/{$this->amox->id}/insight")->assertStatus(422)->assertJsonValidationErrors('store_id');
    }

    public function test_the_master_list_carries_stock_by_store_and_the_last_buying_price(): void
    {
        $this->grantPermissions(['product.view', 'stock.view', 'product.cost.view']);

        $row = collect($this->getJson('/api/products?q=AMOX500')->assertOk()->json('data'))->firstWhere('code', 'AMOX500');

        $this->assertSame('MAIN', $row['stock']['by_store'][0]['store_code']);
        $this->assertSame('1000.0000', $row['stock']['by_store'][0]['on_hand']);
        $this->assertSame('2.0000', $row['last_purchase']['unit_cost_per_base']);

        $this->revokeAllRoles();
        $this->grantPermissions(['product.view', 'stock.view'], 'Cashier');
        $row = collect($this->getJson('/api/products?q=AMOX500')->assertOk()->json('data'))->firstWhere('code', 'AMOX500');
        $this->assertArrayNotHasKey('last_purchase', $row);
    }

    private function priceBox(string $saleMode, string $price): void
    {
        $list = PriceList::create([
            'organisation_id' => $this->org->id, 'code' => $saleMode, 'name' => $saleMode, 'sale_mode' => $saleMode,
            'currency' => 'KES', 'effective_from' => now()->subYear()->toDateString(), 'is_active' => true, 'priority' => 0,
        ]);
        ProductPrice::create([
            'price_list_id' => $list->id, 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id,
            'factor_type' => 'FIXED', 'unit_price' => $price, 'effective_from' => now()->subMonth()->toDateString(),
        ]);
    }

    private function otherProduct(string $code, string $name, string $generic): Product
    {
        $product = Product::create([
            'organisation_id' => $this->org->id, 'code' => $code, 'name' => $name, 'generic_name' => $generic,
            'base_uom_id' => $this->uoms['TAB']->id, 'is_discrete' => true, 'requires_batch' => true,
            'default_price' => '3.0000', 'is_active' => true,
        ]);
        ProductUom::create([
            'product_id' => $product->id, 'uom_id' => $this->uoms['TAB']->id, 'factor_to_base' => 1,
            'is_base' => true, 'is_purchase' => true, 'is_sales' => true, 'is_default_sales' => true,
        ]);

        return $product;
    }

    private function stockOf(Product $product, string $batchNumber, string $qty): void
    {
        $receipt = GoodsReceipt::create([
            'doc_number' => NumberSequence::next($this->org->id, 'GRN', $this->branch->id, 'GRN'),
            'supplier_id' => $this->supplier->id, 'branch_id' => $this->branch->id, 'store_id' => $this->store->id,
            'status' => 'DRAFT', 'is_emergency' => true, 'received_by' => $this->user->id,
        ]);
        GoodsReceiptLine::create([
            'goods_receipt_id' => $receipt->id, 'product_id' => $product->id, 'uom_id' => $this->uoms['TAB']->id,
            'qty_ordered' => $qty, 'qty_delivered' => $qty, 'qty_accepted' => $qty, 'qty_rejected' => '0',
            'batch_number' => $batchNumber, 'expiry_date' => now()->addYear()->toDateString(), 'unit_cost' => '1.5000',
        ]);
        app(GoodsReceiptService::class)->post($receipt);

        ProductBatch::where('product_id', $product->id)->where('batch_number', $batchNumber)
            ->update(['status' => 'RELEASED', 'qc_released_by' => $this->user->id, 'qc_released_at' => now()]);
    }
}
