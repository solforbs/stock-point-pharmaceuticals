<?php

namespace Tests\Feature\Api;

use App\Models\PriceQuoteLog;
use App\Models\Sale;
use App\Models\StockLedger;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 21.4 / 21.6 / 21.15 over HTTP: the POS quotes, checks out against the
 * quote, replays safely, and receives the blueprint's error codes.
 */
class PosHttpFlowTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('B1', now()->addYears(2)->toDateString(), '2000', '2.0000');
        $this->grantPermissions(['sale.create', 'sale.view', 'sale.void', 'stock.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_quote_then_checkout_is_atomic_idempotent_and_voidable(): void
    {
        $quote = $this->postJson('/api/pricing/quote', $this->quotePayload('2'))
            ->assertOk()
            ->assertJsonPath('totals.grand_total', '1000.0000')
            ->assertJsonPath('lines.0.unit_price', '500.0000')
            ->json();

        $sale = $this->postJson('/api/sales/checkout', $this->checkoutPayload($quote), ['Idempotency-Key' => 'pos-1'])
            ->assertCreated()
            ->assertJsonPath('status', 'POSTED')
            ->assertJsonPath('grand_total', '1000.0000')
            ->assertJsonPath('quote_id', $quote['quote_id'])
            ->json();

        $this->assertSame(1, StockLedger::where('source_doc_id', $sale['id'])->count(), 'one ISSUE ledger row');

        $this->postJson('/api/sales/checkout', $this->checkoutPayload($quote), ['Idempotency-Key' => 'pos-1'])
            ->assertOk()
            ->assertHeader('X-Idempotent-Replay', 'true')
            ->assertJsonPath('id', $sale['id']);
        $this->assertSame(1, Sale::count(), 'a replayed Idempotency-Key never creates a second sale');

        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)
            ->assertOk()
            ->assertJsonPath('data.0.on_hand', '1600.0000')
            ->assertJsonPath('data.0.free_to_sell', '1600.0000');

        $this->postJson("/api/sales/{$sale['id']}/void", [])->assertStatus(422);

        $this->postJson("/api/sales/{$sale['id']}/void", ['reason' => 'Customer returned the goods'])
            ->assertOk()
            ->assertJsonPath('status', 'VOIDED');

        $this->assertSame(2, StockLedger::where('source_doc_id', $sale['id'])->count(), 'void appends a reversal; nothing is deleted');
        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->assertJsonPath('data.0.free_to_sell', '2000.0000');
    }

    public function test_checkout_requires_an_idempotency_key(): void
    {
        $quote = $this->postJson('/api/pricing/quote', $this->quotePayload('1'))->assertOk()->json();

        $this->postJson('/api/sales/checkout', $this->checkoutPayload($quote))->assertStatus(400);
    }

    public function test_an_expired_quote_is_refused_with_quote_expired(): void
    {
        $quote = $this->postJson('/api/pricing/quote', $this->quotePayload('1'))->assertOk()->json();
        PriceQuoteLog::where('quote_id', $quote['quote_id'])->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/sales/checkout', $this->checkoutPayload($quote), ['Idempotency-Key' => 'pos-2'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'QUOTE_EXPIRED')
            ->assertJsonPath('error.details.quote_id', $quote['quote_id']);
        $this->assertSame(0, Sale::count());
    }

    public function test_a_price_change_after_quoting_is_refused_with_a_fresh_quote_attached(): void
    {
        $quote = $this->postJson('/api/pricing/quote', $this->quotePayload('2'))->assertOk()->json();
        $this->amox->update(['default_price' => '3.0000']);

        $response = $this->postJson('/api/sales/checkout', $this->checkoutPayload($quote), ['Idempotency-Key' => 'pos-3'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PRICE_CHANGED')
            ->assertJsonPath('error.details.old_total', '1000.0000')
            ->assertJsonPath('error.details.new_total', '1200.0000');

        $this->assertNotSame($quote['quote_id'], $response->json('error.details.new_quote.quote_id'), 'the cashier gets a new quote to re-present');
        $this->assertSame(0, Sale::count());
    }

    public function test_an_unknown_quote_is_a_404(): void
    {
        $this->postJson('/api/sales/checkout', [
            'quote_id' => '00000000-0000-4000-8000-000000000000', 'store_id' => $this->store->id,
        ], ['Idempotency-Key' => 'pos-4'])->assertStatus(404)->assertJsonPath('error.code', 'QUOTE_NOT_FOUND');
    }

    public function test_selling_more_than_is_free_to_sell_is_refused_with_insufficient_stock(): void
    {
        $quote = $this->postJson('/api/pricing/quote', $this->quotePayload('11'))->assertOk()->json();

        $this->postJson('/api/sales/checkout', $this->checkoutPayload($quote), ['Idempotency-Key' => 'pos-5'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, StockLedger::where('txn_type', 'ISSUE')->count());
    }

    public function test_tender_short_of_the_total_is_refused(): void
    {
        $quote = $this->postJson('/api/pricing/quote', $this->quotePayload('1'))->assertOk()->json();

        $this->postJson('/api/sales/checkout', [
            'quote_id' => $quote['quote_id'], 'store_id' => $this->store->id,
            'payments' => [['method' => 'CASH', 'amount' => '100']],
        ], ['Idempotency-Key' => 'pos-6'])->assertStatus(422)->assertJsonPath('error.code', 'PAYMENT_MISMATCH');
    }

    public function test_a_discount_needing_approval_is_held_until_an_approver_signs(): void
    {
        $this->discountPolicy();
        $this->grantAuthority('8.000');

        $quote = $this->postJson('/api/pricing/quote', $this->quotePayload('2', ['requested_discount_pct' => '6', 'requested_discount_reason' => 'Loyal customer']))
            ->assertOk()
            ->assertJsonPath('approval_required', true)
            ->assertJsonPath('lines.0.discount_pct', '6.0000')
            ->json();

        $this->postJson('/api/sales/checkout', $this->checkoutPayload($quote), ['Idempotency-Key' => 'pos-7'])
            ->assertStatus(202)
            ->assertJsonPath('error.code', 'APPROVAL_REQUIRED');

        $this->postJson('/api/sales/checkout', $this->checkoutPayload($quote) + ['approve' => true], ['Idempotency-Key' => 'pos-7'])
            ->assertStatus(403);

        $this->grantPermissions(['sale.create', 'sale.view', 'sale.discount.approve']);

        $this->postJson('/api/sales/checkout', $this->checkoutPayload($quote) + ['approve' => true], ['Idempotency-Key' => 'pos-7'])
            ->assertCreated()
            ->assertJsonPath('grand_total', '940.0000');
    }

    public function test_voiding_needs_the_void_permission(): void
    {
        $quote = $this->postJson('/api/pricing/quote', $this->quotePayload('1'))->assertOk()->json();
        $sale = $this->postJson('/api/sales/checkout', $this->checkoutPayload($quote), ['Idempotency-Key' => 'pos-8'])->assertCreated()->json();

        $this->grantPermissions(['sale.create', 'sale.view']);

        $this->postJson("/api/sales/{$sale['id']}/void", ['reason' => 'Trying it on'])->assertStatus(403);
        $this->assertSame('POSTED', Sale::findOrFail($sale['id'])->status);
    }

    public function test_cost_and_margin_are_hidden_from_users_without_the_cost_permission(): void
    {
        $quote = $this->postJson('/api/pricing/quote', $this->quotePayload('1'))->assertOk()->json();
        $sale = $this->postJson('/api/sales/checkout', $this->checkoutPayload($quote), ['Idempotency-Key' => 'pos-9'])->assertCreated()->json();

        $this->getJson("/api/sales/{$sale['id']}")
            ->assertOk()
            ->assertJsonMissingPath('cost_total')
            ->assertJsonMissingPath('lines.0.unit_cost');

        $this->grantPermissions(['sale.view', 'product.cost.view']);

        $this->getJson("/api/sales/{$sale['id']}")
            ->assertOk()
            ->assertJsonPath('cost_total', '400.0000');
    }

    public function test_a_selling_price_set_at_the_till_is_what_the_sale_posts_at(): void
    {
        $this->grantPermissions(['sale.create', 'sale.view', 'sale.price.override']);
        $catalogPrice = (string) $this->amox->default_price;

        $quote = $this->postJson('/api/pricing/quote', $this->quotePayload('2', ['selling_price' => '600']))
            ->assertOk()
            ->assertJsonPath('lines.0.landing_price', '500.0000')
            ->assertJsonPath('lines.0.unit_price', '600.0000')
            ->assertJsonPath('lines.0.price_source', 'TILL_PRICE')
            ->assertJsonPath('totals.grand_total', '1200.0000')
            ->json();

        $sale = $this->postJson('/api/sales/checkout', $this->checkoutPayload($quote), ['Idempotency-Key' => 'pos-10'])
            ->assertCreated()
            ->assertJsonPath('grand_total', '1200.0000')
            ->json();

        $this->getJson("/api/sales/{$sale['id']}")->assertJsonPath('lines.0.unit_price', '600.0000');
        $this->assertSame($catalogPrice, (string) $this->amox->fresh()->default_price, 'the catalog price is never changed by the till');
    }

    public function test_a_till_price_is_final_with_the_vat_inside_it_not_added_on_top(): void
    {
        $this->vat16();
        $this->grantPermissions(['sale.create', 'sale.price.override']);

        $this->postJson('/api/pricing/quote', $this->quotePayload('2', ['selling_price' => '700']))
            ->assertOk()
            ->assertJsonPath('lines.0.landing_price', '580.0000')
            ->assertJsonPath('lines.0.unit_price', '603.4500')
            ->assertJsonPath('lines.0.tax_amount', '193.1000')
            ->assertJsonPath('totals.grand_total', '1400.0000');
    }

    public function test_a_selling_price_below_the_landing_price_sells_at_the_landing_price(): void
    {
        $this->grantPermissions(['sale.create', 'sale.price.override']);

        $this->postJson('/api/pricing/quote', $this->quotePayload('2', ['selling_price' => '400']))
            ->assertOk()
            ->assertJsonPath('lines.0.unit_price', '500.0000')
            ->assertJsonPath('totals.grand_total', '1000.0000');
    }

    public function test_setting_a_selling_price_needs_the_price_override_permission(): void
    {
        $this->postJson('/api/pricing/quote', $this->quotePayload('2', ['selling_price' => '600']))->assertStatus(403);
    }

    public function test_a_wholesale_quote_needs_a_customer(): void
    {
        $this->grantPermissions(['sale.create', 'sale.mode.switch']);

        $this->postJson('/api/pricing/quote', ['sale_mode' => 'WHOLESALE'] + $this->quotePayload('1'))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CUSTOMER_REQUIRED');
    }

    /**
     * @param  array<string, mixed>  $lineExtra
     * @return array<string, mixed>
     */
    private function quotePayload(string $boxes, array $lineExtra = []): array
    {
        return [
            'sale_mode' => 'RETAIL',
            'store_id' => $this->store->id,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => $boxes] + $lineExtra],
        ];
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function checkoutPayload(array $quote): array
    {
        return [
            'quote_id' => $quote['quote_id'],
            'store_id' => $this->store->id,
            'payments' => [['method' => 'CASH', 'amount' => $quote['totals']['grand_total']]],
        ];
    }
}
