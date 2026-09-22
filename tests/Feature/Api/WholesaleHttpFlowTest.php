<?php

namespace Tests\Feature\Api;

use App\Models\CustomerCredit;
use App\Models\SalesOrder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 21.7 / 21.10 over HTTP: quotation → order (reserving stock under the
 * credit check) → pick → dispatch (stock OUT, revenue, AR) → proof of
 * delivery → receipt against AR, with the trial balance still balancing.
 */
class WholesaleHttpFlowTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('W1', now()->addYears(2)->toDateString(), '5000', '2.0000');
        $this->grantPermissions([
            'sale.create', 'sale.view', 'stock.view', 'warehouse.pick', 'warehouse.dispatch',
            'payment.record', 'finance.ar.view', 'report.financial.view',
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_quotation_to_cash(): void
    {
        $quotation = $this->postJson('/api/quotations', $this->quotationPayload())
            ->assertCreated()
            ->assertJsonPath('status', 'DRAFT')
            ->assertJsonPath('grand_total', '5000.0000')
            ->assertJsonPath('lines.0.unit_price', '500.0000')
            ->json();

        // Accepting reserves stock: free-to-sell drops, on-hand does not.
        $order = $this->postJson("/api/quotations/{$quotation['id']}/accept", [], ['Idempotency-Key' => 'wo-1'])
            ->assertCreated()
            ->assertJsonPath('status', 'CONFIRMED')
            ->assertJsonPath('grand_total', '5000.0000')
            ->json();

        $this->postJson("/api/quotations/{$quotation['id']}/accept", [], ['Idempotency-Key' => 'wo-1'])
            ->assertOk()->assertHeader('X-Idempotent-Replay', 'true')->assertJsonPath('id', $order['id']);
        $this->assertSame(1, SalesOrder::count());

        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)
            ->assertJsonPath('data.0.on_hand', '5000.0000')
            ->assertJsonPath('data.0.reserved', '2000.0000')
            ->assertJsonPath('data.0.free_to_sell', '3000.0000');

        $this->getJson("/api/customers/{$this->customer->id}")
            ->assertOk()
            ->assertJsonPath('open_order_exposure', '5000.0000')
            ->assertJsonPath('available_credit', '995000.0000');

        // Pick every line, then complete the list.
        $this->postJson("/api/sales-orders/{$order['id']}/dispatch", [], ['Idempotency-Key' => 'dn-0'])
            ->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');

        $list = $this->postJson("/api/sales-orders/{$order['id']}/pick")->assertCreated()->json();
        $this->assertNotEmpty($list['lines']);
        foreach ($list['lines'] as $line) {
            $this->postJson("/api/picking-lists/{$list['id']}/lines/{$line['id']}/pick", ['qty_picked_base' => $line['qty_to_pick_base']])
                ->assertOk()->assertJsonPath('status', 'PICKED');
        }
        $this->postJson("/api/picking-lists/{$list['id']}/complete")->assertOk()->assertJsonPath('status', 'COMPLETED');

        // Dispatch posts stock OUT, COGS, revenue and AR in one transaction.
        $note = $this->postJson("/api/sales-orders/{$order['id']}/dispatch", ['delivery_mode' => 'MOTORBIKE', 'vehicle_reg' => 'KMEA 123A', 'driver_name' => 'J. Ekai'], ['Idempotency-Key' => 'dn-1'])
            ->assertCreated()
            ->assertJsonPath('status', 'DISPATCHED')
            ->assertJsonPath('delivery_mode', 'MOTORBIKE')
            ->json();
        $this->assertNotNull($note['sale_id']);

        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)
            ->assertJsonPath('data.0.on_hand', '3000.0000')
            ->assertJsonPath('data.0.reserved', '0.0000')
            ->assertJsonPath('data.0.free_to_sell', '3000.0000');

        $this->getJson("/api/sales/{$note['sale_id']}")->assertOk()->assertJsonPath('status', 'POSTED')->assertJsonPath('sale_mode', 'WHOLESALE');

        $this->postJson("/api/delivery-notes/{$note['id']}/pod", ['received_by_name' => 'Sister Akai, Pharmacy Stores'])
            ->assertOk()->assertJsonPath('status', 'DELIVERED');

        // AR: the whole invoice is current; a receipt clears it.
        $this->getJson('/api/finance/ar-ageing')
            ->assertOk()
            ->assertJsonPath('data.0.customer_id', $this->customer->id)
            ->assertJsonPath('data.0.current', '5000.0000')
            ->assertJsonPath('totals.total', '5000.0000');

        $this->postJson('/api/payments', ['customer_id' => $this->customer->id, 'method' => 'MPESA', 'amount' => '5000'])
            ->assertStatus(422)->assertJsonPath('error.code', 'REFERENCE_REQUIRED');

        $this->postJson('/api/payments', ['customer_id' => $this->customer->id, 'method' => 'MPESA', 'reference' => 'QGH7X1', 'amount' => '5000'])
            ->assertCreated()->assertJsonPath('status', 'CLEARED');

        $this->postJson('/api/payments', ['customer_id' => $this->customer->id, 'method' => 'MPESA', 'reference' => 'QGH7X1', 'amount' => '5000'])
            ->assertOk()->assertHeader('X-Idempotent-Replay', 'true');

        $this->getJson('/api/finance/ar-ageing?customer_id='.$this->customer->id)
            ->assertOk()->assertJsonPath('data.0.total', '0.0000');

        $this->getJson('/api/finance/trial-balance')
            ->assertOk()
            ->assertJsonPath('balanced', true);
    }

    public function test_an_order_beyond_the_credit_limit_is_refused_and_leaves_nothing_behind(): void
    {
        CustomerCredit::where('customer_id', $this->customer->id)->update(['credit_limit' => '1000.0000']);
        $quotation = $this->postJson('/api/quotations', $this->quotationPayload())->assertCreated()->json();

        $this->postJson("/api/quotations/{$quotation['id']}/accept", [], ['Idempotency-Key' => 'wo-2'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CREDIT_LIMIT_EXCEEDED')
            ->assertJsonPath('error.details.limit', '1000.0000');

        $this->assertSame(0, SalesOrder::count(), 'no DRAFT order survives a failed acceptance');
        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->assertJsonPath('data.0.reserved', '0.0000');
    }

    public function test_the_warehouse_endpoints_need_their_permissions(): void
    {
        $quotation = $this->postJson('/api/quotations', $this->quotationPayload())->assertCreated()->json();
        $order = $this->postJson("/api/quotations/{$quotation['id']}/accept", [], ['Idempotency-Key' => 'wo-3'])->assertCreated()->json();

        $this->grantPermissions(['sale.create', 'sale.view']);

        $this->postJson("/api/sales-orders/{$order['id']}/pick")->assertStatus(403);
        $this->postJson("/api/sales-orders/{$order['id']}/dispatch", [], ['Idempotency-Key' => 'dn-3'])->assertStatus(403);
        $this->getJson('/api/finance/ar-ageing')->assertStatus(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function quotationPayload(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'valid_until' => now()->addDays(7)->toDateString(),
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => '10']],
        ];
    }
}
