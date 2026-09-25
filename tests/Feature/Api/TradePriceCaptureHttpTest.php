<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\PriceList;
use App\Models\ProductBatch;
use App\Models\ProductPrice;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Client item 11: the distributor's trade price and purchase discount are
 * captured on the order and the receipt, the net cost is derived from them,
 * selling prices can be reset as the stock arrives, and the terms show up
 * again beside the buying price at the counter and on the product.
 */
class TradePriceCaptureHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    private PriceList $retail;

    private PriceList $wholesale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->retail = $this->priceListWithBoxPrice('RETAIL', '520');
        $this->wholesale = $this->priceListWithBoxPrice('WHOLESALE', '470');
        Sanctum::actingAs($this->user);
    }

    public function test_the_net_cost_is_derived_from_trade_price_less_discount_on_order_and_receipt(): void
    {
        $this->grantPermissions(['po.create', 'po.approve', 'grn.create']);

        $po = $this->postJson('/api/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'qty_ordered' => '10', 'trade_price' => '450', 'discount_pct' => '7']],
        ])->assertCreated()
            ->assertJsonPath('lines.0.trade_price', '450.0000')
            ->assertJsonPath('lines.0.discount_pct', '7.000')
            ->assertJsonPath('lines.0.unit_price', '418.5000')
            ->json();
        $this->postJson("/api/purchase-orders/{$po['id']}/approve")->assertOk();

        $grn = $this->postJson('/api/goods-receipts', $this->grnPayload($po, ['trade_price' => '450', 'discount_pct' => '7']))
            ->assertCreated()
            ->assertJsonPath('lines.0.unit_cost', '418.5000')
            ->assertJsonPath('lines.0.trade_price', '450.0000')
            ->assertJsonPath('lines.0.discount_pct', '7.000')
            ->json();

        $batch = ProductBatch::findOrFail($grn['lines'][0]['batch']['id']);
        $this->assertSame('2.0925', (string) $batch->unit_cost, '418.50 a box of 200 tablets');
    }

    public function test_a_keyed_unit_cost_overrides_the_derived_one(): void
    {
        $this->grantPermissions(['po.create', 'po.approve', 'grn.create']);
        $po = $this->approvedPo();

        $this->postJson('/api/goods-receipts', $this->grnPayload($po, ['trade_price' => '450', 'discount_pct' => '7', 'unit_cost' => '415']))
            ->assertCreated()
            ->assertJsonPath('lines.0.unit_cost', '415.0000')
            ->assertJsonPath('lines.0.trade_price', '450.0000');

        $this->postJson('/api/goods-receipts', $this->grnPayload($po, []))
            ->assertStatus(422)
            ->assertJsonValidationErrors('lines.0.unit_cost');
    }

    public function test_selling_prices_entered_on_the_receipt_are_applied_when_it_posts(): void
    {
        $this->grantPermissions(['po.create', 'po.approve', 'grn.create', 'price.manage']);
        $po = $this->approvedPo();

        $panel = $this->getJson("/api/products/{$this->amox->id}/selling-prices?uom_id={$this->uoms['BOX']->id}")->assertOk();
        $retailRow = collect($panel->json('lists'))->firstWhere('price_list.code', 'RETAIL');
        $this->assertSame('520.0000', $retailRow['current']['unit_price']);

        $grn = $this->postJson('/api/goods-receipts', $this->grnPayload($po, [
            'trade_price' => '450', 'discount_pct' => '7',
            'selling_prices' => [
                ['price_list_id' => $this->retail->id, 'unit_price' => '560'],
                ['price_list_id' => $this->wholesale->id, 'unit_price' => '495'],
            ],
        ]))->assertCreated()->json();

        $this->assertSame('560.0000', (string) $this->currentBoxPrice($this->retail)->unit_price);
        $this->assertSame('495.0000', (string) $this->currentBoxPrice($this->wholesale)->unit_price);
        $this->assertSame(
            now()->subDay()->toDateString(),
            ProductPrice::where('price_list_id', $this->retail->id)->where('unit_price', '520')->first()->effective_to->toDateString(),
            'the old retail price is closed, not overwritten',
        );

        $audits = AuditLog::where('action', 'PRICE_CHANGED')->where('reference', 'RETAIL')->get();
        $this->assertCount(4, $audits, 'one audited row per selling unit of the product');
        $audit = $audits->first(fn (AuditLog $a) => $a->after_json['unit_price'] === '560.0000');
        $this->assertSame('goods_receipt', $audit->after_json['source']);
        $this->assertSame($grn['doc_number'], $audit->after_json['goods_receipt']);
        $this->assertSame('418.5000', $audit->after_json['buying_cost']);
    }

    /**
     * The price set at receiving IS the price at the till, whatever unit
     * the sale is rung up in: the received-unit price is written pro-rata
     * for every selling unit, and the product's fallback default price
     * follows, so no stale number can undercut or overcharge it.
     */
    public function test_the_receipt_price_carries_to_every_selling_unit_and_the_fallback(): void
    {
        $this->grantPermissions(['po.create', 'po.approve', 'grn.create', 'price.manage']);
        $this->amox->update(['default_price' => '9.9900']);
        $po = $this->approvedPo();

        $this->postJson('/api/goods-receipts', $this->grnPayload($po, [
            'trade_price' => '450', 'discount_pct' => '7',
            'selling_prices' => [
                ['price_list_id' => $this->retail->id, 'unit_price' => '560'],
                ['price_list_id' => $this->wholesale->id, 'unit_price' => '495'],
            ],
        ]))->assertCreated();

        // 560 a box of 200: 2.80 a tablet, 28 a strip of 10, 5,600 a carton.
        foreach (['TAB' => '2.8000', 'STR' => '28.0000', 'BOX' => '560.0000', 'CTN' => '5600.0000'] as $uom => $price) {
            $row = ProductPrice::where('price_list_id', $this->retail->id)
                ->where('uom_id', $this->uoms[$uom]->id)->whereNull('effective_to')->orderByDesc('effective_from')->firstOrFail();
            $this->assertSame($price, (string) $row->unit_price, "the {$uom} retail price follows the receipt");
        }

        // The fallback follows the cheapest priced list (wholesale 495 a
        // box = 2.475 a tablet), so rank 6 can never overcharge a walk-in.
        $this->assertSame('2.4750', (string) $this->amox->fresh()->default_price);
        $audit = AuditLog::where('action', 'PRODUCT_DEFAULT_PRICE_SET_ON_RECEIPT')->sole();
        $this->assertSame('9.9900', $audit->before_json['default_price']);
    }

    public function test_selling_prices_need_the_price_permission(): void
    {
        $this->grantPermissions(['po.create', 'po.approve', 'grn.create']);
        $po = $this->approvedPo();

        $this->getJson("/api/products/{$this->amox->id}/selling-prices?uom_id={$this->uoms['BOX']->id}")->assertForbidden();

        $this->postJson('/api/goods-receipts', $this->grnPayload($po, [
            'unit_cost' => '418.50',
            'selling_prices' => [['price_list_id' => $this->retail->id, 'unit_price' => '560']],
        ]))->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertSame(0, GoodsReceipt::count(), 'nothing posts when the prices are refused');
        $this->assertSame('520.0000', (string) $this->currentBoxPrice($this->retail)->unit_price);
        $this->assertSame(0, AuditLog::where('action', 'PRICE_CHANGED')->count());
    }

    public function test_the_insight_and_product_detail_show_the_last_trade_price_and_discount(): void
    {
        $this->grantPermissions(['po.create', 'po.approve', 'grn.create', 'product.view', 'product.cost.view']);
        $po = $this->approvedPo();
        $this->postJson('/api/goods-receipts', $this->grnPayload($po, ['trade_price' => '450', 'discount_pct' => '7']))->assertCreated();

        $this->getJson("/api/products/{$this->amox->id}/insight?store_id={$this->store->id}")
            ->assertOk()
            ->assertJsonPath('buying.last_purchase.trade_price', '450.0000')
            ->assertJsonPath('buying.last_purchase.discount_pct', '7.000')
            ->assertJsonPath('buying.last_purchase.unit_cost', '418.5000')
            ->assertJsonPath('buying.last_purchase.supplier', 'Pharma Distributors Ltd');

        $this->getJson("/api/products/{$this->amox->id}")
            ->assertOk()
            ->assertJsonPath('last_purchase.trade_price', '450.0000')
            ->assertJsonPath('last_purchase.discount_pct', '7.000')
            ->assertJsonPath('last_purchase.supplier', 'Pharma Distributors Ltd');
    }

    public function test_the_product_detail_hides_the_last_purchase_without_the_cost_permission(): void
    {
        $this->grantPermissions(['product.view']);

        $this->getJson("/api/products/{$this->amox->id}")->assertOk()->assertJsonMissingPath('last_purchase');
    }

    private function priceListWithBoxPrice(string $saleMode, string $price): PriceList
    {
        $list = PriceList::create([
            'organisation_id' => $this->org->id, 'code' => $saleMode, 'name' => $saleMode, 'sale_mode' => $saleMode,
            'currency' => 'KES', 'effective_from' => now()->subYear()->toDateString(), 'is_active' => true, 'priority' => 0,
        ]);
        ProductPrice::create([
            'price_list_id' => $list->id, 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id,
            'factor_type' => 'FIXED', 'unit_price' => $price, 'effective_from' => now()->subMonth()->toDateString(),
        ]);

        return $list;
    }

    private function currentBoxPrice(PriceList $list): ProductPrice
    {
        return ProductPrice::where('price_list_id', $list->id)->where('uom_id', $this->uoms['BOX']->id)
            ->whereNull('effective_to')->orderByDesc('effective_from')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function approvedPo(): array
    {
        $po = $this->postJson('/api/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'qty_ordered' => '10', 'trade_price' => '450', 'discount_pct' => '7']],
        ])->assertCreated()->json();
        $this->postJson("/api/purchase-orders/{$po['id']}/approve")->assertOk();

        return $po;
    }

    /**
     * @param  array<string, mixed>  $po
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function grnPayload(array $po, array $line): array
    {
        return [
            'purchase_order_id' => $po['id'],
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'lines' => [[
                'purchase_order_line_id' => $po['lines'][0]['id'],
                'product_id' => $this->amox->id,
                'uom_id' => $this->uoms['BOX']->id,
                'qty_delivered' => '5', 'qty_accepted' => '5',
                'batch_number' => 'AMX-'.uniqid(), 'expiry_date' => now()->addYears(2)->toDateString(),
            ] + $line],
        ];
    }
}
