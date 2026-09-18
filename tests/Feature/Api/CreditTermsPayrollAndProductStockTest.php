<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\CustomerCredit;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\SalesOrder;
use Database\Seeders\PayrollBandsSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Fixes of 2026-09-18: a customer without a credit account can still order
 * (cash on delivery); a manager can override the limit for one order; the
 * owner may approve their own payroll; the product list carries its stock.
 */
class CreditTermsPayrollAndProductStockTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('L1', now()->addYears(2)->toDateString(), '5000', '2.0000');
        Sanctum::actingAs($this->user);
    }

    public function test_a_customer_with_no_credit_limit_orders_cash_on_delivery(): void
    {
        $this->grantPermissions(['sale.create', 'sale.view', 'sale.mode.switch']);
        CustomerCredit::where('customer_id', $this->customer->id)->update(['credit_limit' => '0']);

        $order = $this->postJson('/api/sales-orders', $this->orderPayload(), ['Idempotency-Key' => 'cod-1'])
            ->assertCreated()->assertJsonPath('payment_terms', 'CASH_ON_DELIVERY')->json();

        $this->postJson("/api/sales-orders/{$order['id']}/confirm")->assertOk()->assertJsonPath('status', 'CONFIRMED');
    }

    public function test_an_account_order_over_the_limit_is_refused_with_advice_then_overridden_by_a_manager(): void
    {
        $this->grantPermissions(['sale.create', 'sale.view', 'sale.mode.switch']);
        CustomerCredit::where('customer_id', $this->customer->id)->update(['credit_limit' => '0']);

        $order = $this->postJson('/api/sales-orders', $this->orderPayload() + ['payment_terms' => 'ACCOUNT'], ['Idempotency-Key' => 'acc-1'])->assertCreated()->json();

        $this->postJson("/api/sales-orders/{$order['id']}/confirm")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CREDIT_LIMIT_EXCEEDED')
            ->assertJsonPath('error.message', 'This customer has no credit account (limit 0.00). Choose cash on delivery, set a credit limit in Credit Control, or ask a manager to override.');

        // Without the override permission the reason is refused outright.
        $this->postJson("/api/sales-orders/{$order['id']}/confirm", ['credit_override_reason' => 'Known hospital, pays monthly'])->assertStatus(403);

        $this->grantPermissions(['sale.create', 'sale.view', 'sale.mode.switch', 'customer.credit.override']);
        $this->postJson("/api/sales-orders/{$order['id']}/confirm", ['credit_override_reason' => 'Known hospital, pays monthly'])
            ->assertOk()->assertJsonPath('status', 'CONFIRMED');
        $this->assertSame('Known hospital, pays monthly', SalesOrder::find($order['id'])->credit_override_reason);
        $this->assertSame(1, AuditLog::where('action', 'CREDIT_LIMIT_OVERRIDDEN')->count());
    }

    public function test_switching_a_draft_order_to_cash_on_delivery_at_confirm(): void
    {
        $this->grantPermissions(['sale.create', 'sale.view', 'sale.mode.switch']);
        CustomerCredit::where('customer_id', $this->customer->id)->update(['credit_limit' => '1']);

        $order = $this->postJson('/api/sales-orders', $this->orderPayload(), ['Idempotency-Key' => 'sw-1'])->assertCreated()->assertJsonPath('payment_terms', 'ACCOUNT')->json();
        $this->postJson("/api/sales-orders/{$order['id']}/confirm")->assertStatus(409);
        $this->postJson("/api/sales-orders/{$order['id']}/confirm", ['payment_terms' => 'CASH_ON_DELIVERY'])->assertOk()->assertJsonPath('payment_terms', 'CASH_ON_DELIVERY');
    }

    public function test_the_owner_may_approve_their_own_payroll_and_others_may_not(): void
    {
        (new PayrollBandsSeeder)->run();
        Employee::create(['organisation_id' => $this->org->id, 'branch_id' => $this->branch->id, 'employee_no' => 'E1', 'name' => 'Jane', 'basic_salary' => '50000', 'is_active' => true, 'date_joined' => now()->subYear()->toDateString()]);

        $this->grantPermissions(['payroll.view', 'payroll.process']);
        $run = $this->postJson('/api/payroll/runs', ['period_year' => (int) now()->format('Y'), 'period_month' => (int) now()->format('n')])->assertCreated()->json();
        $this->postJson("/api/payroll/runs/{$run['id']}/compute")->assertOk();
        $this->postJson("/api/payroll/runs/{$run['id']}/approve")->assertStatus(422);

        $this->grantPermissions(['payroll.view', 'payroll.process', 'payroll.approve.own']);
        $this->postJson("/api/payroll/runs/{$run['id']}/approve")->assertOk()->assertJsonPath('status', 'APPROVED');
        $this->assertTrue(AuditLog::where('action', 'PAYROLL_APPROVED')->firstOrFail()->after_json['self_approved']);
        $this->assertSame('APPROVED', PayrollRun::find($run['id'])->status);
    }

    public function test_the_product_list_carries_branch_stock_and_filters_by_it(): void
    {
        $this->grantPermissions(['product.view', 'stock.view']);

        $this->getJson('/api/products?q=AMOX')->assertOk()
            ->assertJsonPath('data.0.stock.on_hand', '5000.0000')
            ->assertJsonPath('data.0.stock.free_to_sell', '5000.0000')
            ->assertJsonPath('data.0.stock.nearest_expiry', now()->addYears(2)->toDateString());
        $this->getJson('/api/products?stock=in_stock')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/products?stock=out_of_stock')->assertOk()->assertJsonPath('total', 0);

        $this->amox->update(['reorder_point' => '6000']);
        $this->getJson('/api/products?stock=below_reorder')->assertOk()->assertJsonPath('data.0.code', 'AMOX500');

        $this->grantPermissions(['product.view']);
        $this->getJson('/api/products?q=AMOX')->assertOk()->assertJsonMissingPath('data.0.stock');
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(): array
    {
        return ['customer_id' => $this->customer->id, 'store_id' => $this->store->id, 'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => '2']]];
    }
}
