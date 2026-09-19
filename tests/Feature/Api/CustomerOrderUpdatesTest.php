<?php

namespace Tests\Feature\Api;

use App\Mail\CustomerOrderUpdateMail;
use App\Models\DeliveryNote;
use App\Models\SalesOrder;
use App\Models\SalesOrderUpdate;
use App\Services\Sales\CustomerOrderNotifier;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 7 — the customer is kept in the picture. A wholesale customer's
 * question is "where is my order?", and the warehouse already knows.
 */
class CustomerOrderUpdatesTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('B-ORD', now()->addYear()->toDateString(), '1000', '2.0000');
        $this->grantPermissions(['sale.create', 'sale.view', 'warehouse.pick', 'warehouse.dispatch', 'stock.view', 'product.view']);
        $this->customer->update(['email' => 'buyer@turkanahospital.test']);
        Sanctum::actingAs($this->user);
    }

    private function order(): array
    {
        return $this->postJson('/api/sales-orders', [
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => '2']],
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json();
    }

    public function test_the_customer_is_told_at_every_step_from_confirmation_to_delivery(): void
    {
        Mail::fake();
        $order = $this->order();

        $this->postJson("/api/sales-orders/{$order['id']}/confirm")->assertOk();
        Mail::assertSent(CustomerOrderUpdateMail::class, function (CustomerOrderUpdateMail $mail) use ($order) {
            $body = $mail->render();
            $this->assertStringContainsString($order['doc_number'], $body);
            $this->assertStringContainsString('Order confirmed and stock reserved', $body);
            // A customer is shown what they ordered, in the unit they ordered
            // it in — not an internal product code.
            $this->assertStringContainsString('Amoxicillin 500mg Capsules', $body);
            $this->assertStringContainsString('2 BOX', $body);
            $this->assertStringNotContainsString('AMOX500', $body);

            return $mail->hasTo('buyer@turkanahospital.test')
                && str_contains($mail->envelope()->subject, 'confirmed');
        });

        $this->postJson("/api/sales-orders/{$order['id']}/pick")->assertCreated();
        $list = SalesOrder::find($order['id'])->pickingLists()->firstOrFail();

        foreach ($list->lines as $line) {
            $this->postJson("/api/picking-lists/{$list->id}/lines/{$line->id}/pick", ['qty_picked_base' => (string) $line->qty_to_pick_base])->assertOk();
        }
        $this->postJson("/api/picking-lists/{$list->id}/complete")->assertOk();

        $this->postJson("/api/sales-orders/{$order['id']}/dispatch", [
            'vehicle_reg' => 'KDA 123X', 'driver_name' => 'Joseph Ekiru', 'driver_phone' => '0712345678',
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

        $note = DeliveryNote::where('sales_order_id', $order['id'])->firstOrFail();
        $this->postJson("/api/delivery-notes/{$note->id}/pod", ['received_by_name' => 'Pharmacy Storekeeper'])->assertOk();

        $milestones = SalesOrderUpdate::where('sales_order_id', $order['id'])->orderBy('created_at')->pluck('milestone')->all();
        $this->assertSame(['CONFIRMED', 'PICKING', 'PACKED', 'DISPATCHED', 'DELIVERED'], $milestones);

        $this->assertNull(SalesOrderUpdate::whereNull('sent_at')->first(), 'every step actually reached the customer');
        Mail::assertSentCount(5);

        // The dispatch note tells them who is arriving.
        $dispatched = SalesOrderUpdate::where('milestone', 'DISPATCHED')->firstOrFail();
        $this->assertStringContainsString('KDA 123X', (string) $dispatched->note);
        $this->assertStringContainsString('Joseph Ekiru', (string) $dispatched->note);
    }

    public function test_a_cancelled_order_says_so_and_stops_the_journey_there(): void
    {
        Mail::fake();
        $order = $this->order();
        $this->postJson("/api/sales-orders/{$order['id']}/confirm")->assertOk();

        $this->postJson("/api/sales-orders/{$order['id']}/cancel", ['reason' => 'Customer changed the delivery date'])->assertOk();

        Mail::assertSent(CustomerOrderUpdateMail::class, function (CustomerOrderUpdateMail $mail) {
            if ($mail->milestone !== 'CANCELLED') {
                return false;
            }
            $body = $mail->render();
            $this->assertStringContainsString('Customer changed the delivery date', $body);
            $this->assertStringContainsString('Nothing will be delivered', $body);
            $this->assertStringNotContainsString('On its way to you', $body, 'a cancelled order shows no delivery steps');

            return true;
        });
    }

    public function test_the_same_step_is_never_announced_twice(): void
    {
        Mail::fake();
        $order = SalesOrder::findOrFail($this->order()['id']);
        $notifier = app(CustomerOrderNotifier::class);

        $notifier->announce($order, 'CONFIRMED');
        $notifier->announce($order, 'CONFIRMED');
        $notifier->announce($order, 'CONFIRMED');

        $this->assertSame(1, SalesOrderUpdate::where('sales_order_id', $order->id)->count());
        Mail::assertSentCount(1);
    }

    public function test_a_customer_without_an_address_is_recorded_rather_than_silently_skipped(): void
    {
        Mail::fake();
        $this->customer->update(['email' => null]);
        $order = $this->order();

        $this->postJson("/api/sales-orders/{$order['id']}/confirm")->assertOk();

        Mail::assertNothingSent();
        $update = SalesOrderUpdate::where('sales_order_id', $order['id'])->firstOrFail();
        $this->assertNull($update->sent_at);
        $this->assertSame('The customer has no email address on file.', $update->failure_reason);
    }

    public function test_staff_can_see_what_the_customer_was_told(): void
    {
        Mail::fake();
        $order = $this->order();
        $this->postJson("/api/sales-orders/{$order['id']}/confirm")->assertOk();

        $this->getJson("/api/sales-orders/{$order['id']}/updates")->assertOk()
            ->assertJsonPath('data.0.milestone', 'CONFIRMED')
            ->assertJsonPath('customer_email', 'buyer@turkanahospital.test')
            ->assertJsonPath('milestones.DISPATCHED', 'On its way to you');
    }
}
