<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\OfflineSale;
use App\Models\Sale;
use App\Models\Store;
use App\Services\Pricing\Money;
use App\Services\Sales\CheckoutService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 16.7 / 17.5 — the counter keeps selling through an internet outage
 * from a price pack, and replays its outbox on reconnect. A replayed sale
 * is a fact: it posts at the price charged, or waits as a conflict.
 */
class OfflineSaleHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('B-OFF', now()->addYear()->toDateString(), '100', '1.0000');
        $this->grantPermissions(['sale.view', 'sale.create', 'sale.void']);
        Sanctum::actingAs($this->user);
    }

    public function test_the_price_pack_lists_what_the_store_can_sell_at_walk_in_prices(): void
    {
        $pack = $this->getJson("/api/pos/offline-pack?store_id={$this->store->id}")->assertOk()
            ->assertJsonPath('store.code', 'MAIN')
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.code', 'AMOX500')
            ->assertJsonPath('products.0.free_to_sell', '100.0000')
            ->json();

        $tab = $pack['products'][0]['prices'][$this->uoms['TAB']->id];
        $this->assertSame(1, bccomp($tab['unit_price'], '0', 4), 'priced by the quote engine');
        $this->assertNotNull($pack['valid_until']);
    }

    public function test_pack_prices_come_from_a_cache_the_scheduler_refreshes(): void
    {
        $before = $this->packPrice();

        $this->amox->update(['default_price' => bcadd((string) $this->amox->default_price, '1', 4)]);
        $this->assertSame($before, $this->packPrice(), 'a pack request reads the cached price');

        $this->artisan('pos:warm-price-packs')->assertSuccessful();
        $this->assertSame(1, bccomp($this->packPrice(), $before, 4), 'the warm-up re-prices from the engine');
    }

    public function test_no_pack_is_built_for_a_store_that_may_not_sell(): void
    {
        $this->store->update(['is_sellable' => false]);

        $this->getJson("/api/pos/offline-pack?store_id={$this->store->id}")->assertStatus(422)->assertJsonPath('error.code', 'STORE_NOT_SELLABLE');
    }

    public function test_a_replayed_sale_posts_once_at_the_pack_price_with_no_variance(): void
    {
        $this->vat16();
        $tab = $this->getJson("/api/pos/offline-pack?store_id={$this->store->id}")->json('products.0.prices.'.$this->uoms['TAB']->id);
        $this->assertSame('16.0000', $tab['tax_rate']);
        $sale = $this->offlineSale('7', $tab['unit_price'], $tab['tax_rate']);

        $response = $this->postJson('/api/pos/offline-sales', $sale)->assertCreated()
            ->assertJsonPath('status', 'POSTED')
            ->assertJsonPath('price_variance', '0.0000');

        $posted = Sale::findOrFail($response->json('sale_id'));
        $this->assertSame($sale['id'], $posted->idempotency_key);
        $this->assertSame('RETAIL', $posted->sale_mode);
        $this->assertSame(0, bccomp((string) $posted->grand_total, $sale['total'], 4));
        $this->assertSame(1, bccomp((string) $posted->tax_total, '0', 4), 'VAT charged offline is VAT in the books');
        $this->assertSame('93.0000', $this->ledgerSum(), 'the goods left the shelf');

        $this->postJson('/api/pos/offline-sales', $sale)->assertOk()->assertHeader('X-Idempotent-Replay', 'true')->assertJsonPath('sale_id', $posted->id);
        $this->assertSame(1, Sale::count(), 'a replay never posts twice');
        $this->assertSame(1, AuditLog::where('action', 'OFFLINE_SALE_POSTED')->count());
    }

    public function test_a_stale_price_still_posts_as_charged_and_the_variance_is_recorded(): void
    {
        $charged = bcadd($this->packPrice(), '1.0000', 4);
        $sale = $this->offlineSale('2', $charged);

        $response = $this->postJson('/api/pos/offline-sales', $sale)->assertCreated()->json();

        $this->assertSame('POSTED', $response['status']);
        $this->assertSame(0, bccomp((string) Sale::findOrFail($response['sale_id'])->grand_total, $sale['total'], 4), 'the books match the till');
        $this->assertSame(1, bccomp($response['price_variance'], '0', 4), 'charged more than the server would have');
    }

    public function test_a_sale_the_stock_cannot_cover_is_kept_as_a_conflict_and_can_be_retried(): void
    {
        $sale = $this->offlineSale('150', $this->packPrice());

        $response = $this->postJson('/api/pos/offline-sales', $sale)->assertStatus(202)
            ->assertJsonPath('status', 'CONFLICT')
            ->assertJsonPath('error_code', 'INSUFFICIENT_STOCK')
            ->assertJsonPath('sale_id', null);
        $this->assertSame(0, Sale::count());

        $this->getJson('/api/pos/offline-sales?status=CONFLICT')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/admin/sync-status')->assertOk()->assertJsonPath('offline_queue.conflicts', 1);

        $this->receive('B-OFF-2', now()->addYear()->toDateString(), '100', '1.0000');

        $this->travel(2)->hours();
        $this->postJson("/api/pos/offline-sales/{$response->json('id')}/retry")->assertOk()
            ->assertJsonPath('status', 'POSTED')
            ->assertJsonPath('attempts', 2)
            ->assertJsonPath('resolved_by', $this->user->id);
        $this->assertSame(1, Sale::count());
        $this->assertTrue(OfflineSale::findOrFail($response->json('id'))->sold_at->equalTo(Carbon::parse($sale['sold_at'])), 'resolving a conflict never moves when the sale was made');
    }

    public function test_a_conflict_can_be_dismissed_with_a_reason_by_someone_who_may_void(): void
    {
        $id = $this->postJson('/api/pos/offline-sales', $this->offlineSale('150', $this->packPrice()))->assertStatus(202)->json('id');

        $this->postJson("/api/pos/offline-sales/{$id}/dismiss", [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->postJson("/api/pos/offline-sales/{$id}/dismiss", ['reason' => 'Refunded the customer from the till'])->assertOk()->assertJsonPath('status', 'DISMISSED');
        $this->postJson("/api/pos/offline-sales/{$id}/retry")->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
        $this->assertSame(1, AuditLog::where('action', 'OFFLINE_SALE_DISMISSED')->count());

        $this->revokeAllRoles();
        $this->grantPermissions(['sale.view', 'sale.create'], 'Cashier');
        $other = $this->postJson('/api/pos/offline-sales', $this->offlineSale('150', $this->packPrice()))->json('id');
        $this->postJson("/api/pos/offline-sales/{$other}/dismiss", ['reason' => 'Not mine to decide'])->assertStatus(403);
    }

    public function test_a_sale_that_already_landed_online_is_linked_not_posted_again(): void
    {
        $sale = $this->offlineSale('1', $this->packPrice());
        $online = $this->checkout([$this->saleLine('TAB', '1', $sale['lines'][0]['unit_price'])], [['method' => 'CASH', 'amount' => $sale['total']]], ['idempotency_key' => $sale['id']]);

        $this->postJson('/api/pos/offline-sales', $sale)->assertCreated()->assertJsonPath('sale_id', $online->id);
        $this->assertSame(1, Sale::count());
    }

    public function test_a_replay_that_fails_outright_keeps_nothing_so_the_terminal_sends_it_again(): void
    {
        $sale = $this->offlineSale('1', $this->packPrice());
        $this->mock(CheckoutService::class)->shouldReceive('checkout')->once()->andThrow(new \RuntimeException('Lost the database mid-sale'));

        $this->postJson('/api/pos/offline-sales', $sale)->assertStatus(500);

        $this->assertSame(0, OfflineSale::count(), 'no bare conflict is left behind to answer the retry');
    }

    public function test_a_sale_for_a_store_closed_to_sales_since_becomes_a_conflict(): void
    {
        $sale = $this->offlineSale('1', $this->packPrice());
        Store::whereKey($this->store->id)->update(['is_sellable' => false]);

        $this->postJson('/api/pos/offline-sales', $sale)->assertStatus(202)->assertJsonPath('error_code', 'STORE_NOT_SELLABLE');
    }

    public function test_a_total_that_disagrees_with_its_own_lines_is_a_conflict(): void
    {
        $sale = $this->offlineSale('1', $this->packPrice());
        $sale['total'] = bcadd($sale['total'], '5', 4);

        $this->postJson('/api/pos/offline-sales', $sale)->assertStatus(202)->assertJsonPath('error_code', 'INVALID_INPUT');
        $this->assertSame(0, Sale::count());
    }

    public function test_offline_sales_are_cash_mpesa_or_card_and_never_from_the_future(): void
    {
        $sale = $this->offlineSale('1', $this->packPrice());

        $this->postJson('/api/pos/offline-sales', ['payments' => [['method' => 'CHEQUE', 'amount' => $sale['total']]]] + $sale)
            ->assertStatus(422)->assertJsonValidationErrors('payments.0.method');
        $this->postJson('/api/pos/offline-sales', ['sold_at' => now()->addDay()->toIso8601String()] + $sale)
            ->assertStatus(422)->assertJsonValidationErrors('sold_at');
    }

    public function test_selling_offline_needs_permission_to_sell(): void
    {
        $this->revokeAllRoles();
        $this->grantPermissions(['sale.view'], 'Viewer');

        $this->getJson("/api/pos/offline-pack?store_id={$this->store->id}")->assertStatus(403);
        $this->postJson('/api/pos/offline-sales', $this->offlineSale('1', '2.0000'))->assertStatus(403);
    }

    private function packPrice(): string
    {
        $pack = $this->getJson("/api/pos/offline-pack?store_id={$this->store->id}")->assertOk()->json();

        return $pack['products'][0]['prices'][$this->uoms['TAB']->id]['unit_price'];
    }

    /**
     * @return array<string, mixed>
     */
    private function offlineSale(string $qty, string $unitPrice, string $taxRate = '0'): array
    {
        $net = Money::round(bcmul($unitPrice, $qty, 6), 2);
        $total = bcadd($net, Money::round(bcmul($net, bcdiv($taxRate, '100', 6), 6), 2), 4);

        return [
            'id' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'terminal_id' => 'T-01',
            'sold_at' => now()->subMinutes(10)->toIso8601String(),
            'total' => $total,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['TAB']->id, 'qty' => $qty, 'unit_price' => $unitPrice, 'tax_rate' => $taxRate]],
            'payments' => [['method' => 'CASH', 'amount' => $total]],
        ];
    }
}
