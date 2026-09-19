<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\PayrollBand;
use App\Models\Store;
use App\Services\Sales\StoreNotSellableException;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Two rules the business set: nothing is sold from a store marked not
 * sellable, and statutory payroll rates are kept by the System
 * Administrator rather than fixed in a seed.
 */
class PayrollBandsAndSellableStoresTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_the_till_refuses_to_sell_from_a_store_that_is_not_sellable(): void
    {
        $this->receive('B-SELL', now()->addYear()->toDateString(), '100', '2.0000');
        $this->store->update(['is_sellable' => false]);

        try {
            $this->checkout([$this->saleLine('TAB', '1', '2.7500')], [['method' => 'CASH', 'amount' => '2.7500']]);
            $this->fail('A non-sellable store must refuse the sale');
        } catch (StoreNotSellableException $e) {
            $this->assertSame($this->store->code, $e->storeCode);
            $this->assertStringContainsString('Transfer the stock to a sellable store', $e->getMessage());
        }

        // And once the store is marked sellable, the same sale goes through.
        $this->store->update(['is_sellable' => true]);
        $sale = $this->checkout([$this->saleLine('TAB', '1', '2.7500')], [['method' => 'CASH', 'amount' => '2.7500']]);
        $this->assertSame('POSTED', $sale->status);
    }

    public function test_a_sales_order_cannot_reserve_stock_in_a_store_that_is_not_sellable(): void
    {
        $this->receive('B-SO', now()->addYear()->toDateString(), '100', '2.0000');
        $this->grantPermissions(['sale.create', 'sale.view']);
        Sanctum::actingAs($this->user);

        $warehouse = Store::create(['branch_id' => $this->branch->id, 'code' => 'WH', 'name' => 'Warehouse', 'store_type' => 'MAIN', 'is_sellable' => false]);

        $order = $this->postJson('/api/sales-orders', [
            'customer_id' => $this->customer->id,
            'store_id' => $warehouse->id,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['TAB']->id, 'quantity' => '10']],
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json();

        $this->postJson("/api/sales-orders/{$order['id']}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'STORE_NOT_SELLABLE')
            ->assertJsonPath('error.details.store', 'WH');
    }

    public function test_the_system_administrator_keeps_payroll_rates_current(): void
    {
        $this->grantPermissions(['admin.settings']);
        Sanctum::actingAs($this->user);

        // A new rate from a date: add the new band ...
        $created = $this->postJson('/api/payroll-bands', [
            'band_type' => 'SHIF', 'sequence' => 1, 'effective_from' => '2027-01-01',
            'lower' => 0, 'rate_pct' => 3.0, 'source' => 'Hypothetical 2027 amendment',
        ])->assertCreated()->assertJsonPath('band_type', 'SHIF')->json();

        // ... correct it ...
        $this->patchJson("/api/payroll-bands/{$created['id']}", ['rate_pct' => 2.9])->assertOk()
            ->assertJsonPath('rate_pct', '2.9000');

        // ... and see it among the bands in force on that date.
        $inForce = $this->getJson('/api/payroll-bands?band_type=SHIF&in_force_on=2027-02-01')->assertOk()->json('data');
        $this->assertContains($created['id'], array_column($inForce, 'id'));

        $this->deleteJson("/api/payroll-bands/{$created['id']}")->assertOk();
        $this->assertNull(PayrollBand::find($created['id']));

        foreach (['PAYROLL_BAND_CREATED', 'PAYROLL_BAND_UPDATED', 'PAYROLL_BAND_DELETED'] as $action) {
            $this->assertSame(1, AuditLog::where('action', $action)->where('entity_id', $created['id'])->count(), "{$action} is audited");
        }
    }

    public function test_a_band_the_calculator_would_silently_ignore_is_refused(): void
    {
        $this->grantPermissions(['admin.settings']);
        Sanctum::actingAs($this->user);

        $valid = ['band_type' => 'PAYE', 'sequence' => 1, 'effective_from' => '2027-01-01', 'lower' => 0, 'rate_pct' => 10];

        // An unknown type would never be used by payroll.
        $this->postJson('/api/payroll-bands', ['band_type' => 'NHIF'] + $valid)->assertStatus(422)->assertJsonValidationErrors('band_type');
        // An upper limit at or below the lower one is not a band.
        $this->postJson('/api/payroll-bands', ['lower' => 5000, 'upper' => 5000] + $valid)->assertStatus(422)->assertJsonValidationErrors('upper');
        // Ending before it starts.
        $this->postJson('/api/payroll-bands', ['effective_to' => '2026-12-31'] + $valid)->assertStatus(422)->assertJsonValidationErrors('effective_to');
        // Neither a rate nor a fixed amount.
        $this->postJson('/api/payroll-bands', array_diff_key($valid, ['rate_pct' => 1]))->assertStatus(422)->assertJsonValidationErrors('rate_pct');
        // A percentage over 100.
        $this->postJson('/api/payroll-bands', ['rate_pct' => 150] + $valid)->assertStatus(422)->assertJsonValidationErrors('rate_pct');
    }

    public function test_only_the_system_administrator_may_change_payroll_rates(): void
    {
        $this->grantPermissions(['payroll.view', 'payroll.process']);
        Sanctum::actingAs($this->user);

        $this->getJson('/api/payroll-bands')->assertStatus(403);
        $this->postJson('/api/payroll-bands', ['band_type' => 'PAYE'])->assertStatus(403);
    }
}
