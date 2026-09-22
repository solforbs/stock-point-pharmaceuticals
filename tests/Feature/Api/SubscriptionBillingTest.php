<?php

namespace Tests\Feature\Api;

use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Paying for a plan through Paystack: checkout, verification on return,
 * the signed webhook, renewals, and never applying a charge twice or a
 * charge of the wrong amount.
 */
class SubscriptionBillingTest extends TestCase
{
    use BuildsBlueprintWorld;

    private const SECRET = 'sk_test_secret';

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret_key' => self::SECRET, 'services.paystack.base_url' => 'https://api.paystack.test']);
        $this->buildWorld();
        $this->grantPermissions(['admin.settings', 'product.view', 'product.create']);
        // A lapsed institution that wants to pay.
        $this->org->forceFill(['is_complimentary' => false, 'trial_ends_at' => now()->subDay(), 'contact_email' => 'billing@stockpoint.test'])->save();
        $this->plan = Plan::create(['code' => 'PRO', 'name' => 'Professional', 'price_monthly' => '6000.00', 'price_yearly' => '60000.00', 'currency' => 'KES']);
        Sanctum::actingAs($this->user);
    }

    public function test_checkout_then_verification_activates_the_institution_for_a_month(): void
    {
        Http::fake(['api.paystack.test/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.test/abc', 'access_code' => 'abc', 'reference' => 'ignored']])]);

        $checkout = $this->postJson('/api/billing/checkout', ['plan_id' => $this->plan->id, 'billing_interval' => 'MONTHLY'])
            ->assertCreated()->assertJsonPath('authorization_url', 'https://checkout.paystack.test/abc')->json();

        Http::assertSent(fn ($r) => $r->url() === 'https://api.paystack.test/transaction/initialize'
            && $r['amount'] === 600000 && $r['currency'] === 'KES' && $r['email'] === 'billing@stockpoint.test' && $r['reference'] === $checkout['reference']);
        $this->assertSame('PENDING', SubscriptionPayment::sole()->status);

        Http::fake(['api.paystack.test/transaction/verify/*' => Http::response(['status' => true, 'data' => $this->charge($checkout['reference'], 600000)])]);
        $this->postJson('/api/billing/verify', ['reference' => $checkout['reference']])->assertOk()
            ->assertJsonPath('status', 'SUCCESS')->assertJsonPath('access_state', 'ACTIVE');

        $subscription = Subscription::sole();
        $this->assertTrue($subscription->current_period_end->between(now()->addMonth()->subMinute(), now()->addMonth()->addMinute()));
        $this->assertSame('CUS_1', $subscription->paystack_customer_code);
        $this->postJson('/api/products', ['code' => 'NEW', 'name' => 'New', 'base_uom_id' => $this->uoms['TAB']->id])->assertCreated();

        // The webhook for the same charge arriving afterwards changes nothing.
        $this->webhook('charge.success', $this->charge($checkout['reference'], 600000))->assertOk();
        $this->assertTrue($subscription->fresh()->current_period_end->equalTo($subscription->current_period_end), 'settled once');
    }

    public function test_an_unreachable_paystack_is_reported_plainly_and_charges_nothing(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->postJson('/api/billing/checkout', ['plan_id' => $this->plan->id, 'billing_interval' => 'MONTHLY'])
            ->assertStatus(502)->assertJsonPath('error.code', 'PAYMENT_GATEWAY');
        $this->assertSame(Organisation::ACCESS_LAPSED, $this->org->fresh()->accessState());
    }

    public function test_the_webhook_needs_a_valid_signature(): void
    {
        $this->postJson('/api/public/paystack/webhook', ['event' => 'charge.success', 'data' => []], ['x-paystack-signature' => 'forged'])->assertStatus(401);
    }

    public function test_a_charge_of_the_wrong_amount_is_never_applied(): void
    {
        $payment = $this->pendingPayment('REF-LOW');

        $this->webhook('charge.success', $this->charge('REF-LOW', 100))->assertOk();

        $this->assertSame('FAILED', $payment->fresh()->status);
        $this->assertSame(Organisation::ACCESS_LAPSED, $this->org->fresh()->accessState());
    }

    public function test_paying_early_extends_from_the_end_of_the_paid_period(): void
    {
        $this->webhook('charge.success', $this->charge($this->pendingPayment('REF-1')->reference, 600000))->assertOk();
        $firstEnd = Subscription::sole()->current_period_end;

        $this->webhook('charge.success', $this->charge($this->pendingPayment('REF-2')->reference, 600000))->assertOk();

        $this->assertTrue(Subscription::sole()->current_period_end->equalTo($firstEnd->copy()->addMonth()), 'no days lost by paying early');
    }

    public function test_a_recurring_renewal_is_matched_to_the_institution_by_its_paystack_customer(): void
    {
        $this->webhook('charge.success', $this->charge($this->pendingPayment('REF-FIRST')->reference, 600000))->assertOk();
        $this->webhook('subscription.create', ['subscription_code' => 'SUB_abc', 'email_token' => 'tok', 'customer' => ['customer_code' => 'CUS_1']])->assertOk();
        $firstEnd = Subscription::sole()->current_period_end;

        // Paystack charges the saved card on its own; no checkout, no metadata.
        $this->webhook('charge.success', $this->charge('PAYSTACK-RENEWAL-1', 600000, withMetadata: false))->assertOk();

        $this->assertSame(2, SubscriptionPayment::where('status', 'SUCCESS')->count());
        $this->assertSame('SUB_abc', Subscription::sole()->paystack_subscription_code);
        $this->assertTrue(Subscription::sole()->current_period_end->equalTo($firstEnd->copy()->addMonth()));
    }

    public function test_billing_is_for_institution_administrators(): void
    {
        $this->revokeAllRoles();
        $this->grantPermissions(['product.view'], 'Cashier');

        $this->getJson('/api/billing')->assertForbidden();
        $this->postJson('/api/billing/checkout', ['plan_id' => $this->plan->id, 'billing_interval' => 'MONTHLY'])->assertForbidden();
    }

    private function pendingPayment(string $reference): SubscriptionPayment
    {
        return SubscriptionPayment::create([
            'organisation_id' => $this->org->id, 'plan_id' => $this->plan->id, 'billing_interval' => 'MONTHLY',
            'reference' => $reference, 'amount' => '6000.00', 'currency' => 'KES', 'status' => 'PENDING',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function charge(string $reference, int $amount, bool $withMetadata = true): array
    {
        return array_filter([
            'reference' => $reference, 'status' => 'success', 'amount' => $amount, 'currency' => 'KES', 'channel' => 'mobile_money',
            'paid_at' => now()->toIso8601String(), 'customer' => ['customer_code' => 'CUS_1', 'email' => 'billing@stockpoint.test'],
            'metadata' => $withMetadata ? ['organisation_id' => $this->org->id] : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function webhook(string $event, array $data): TestResponse
    {
        $body = json_encode(['event' => $event, 'data' => $data]);

        return $this->call('POST', '/api/public/paystack/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', (string) $body, self::SECRET),
        ], $body);
    }
}
