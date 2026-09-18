<?php

namespace Tests\Feature\Api;

use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

class DashboardAndPriceListHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('L1', now()->addYears(2)->toDateString(), '100', '2.0000');
    }

    public function test_the_dashboard_summary_only_exposes_blocks_the_user_may_see(): void
    {
        $this->grantPermissions(['sale.view', 'stock.view']);
        Sanctum::actingAs($this->user);

        $this->getJson('/api/dashboard/summary')->assertOk()
            ->assertJsonPath('sales_today.count', 0)
            ->assertJsonPath('sales_today.total', '0.0000')
            ->assertJsonPath('inventory.pending_qc_batches', 0)
            ->assertJsonPath('inventory.expiring_90d_batches', 0)
            ->assertJsonPath('approvals', null)
            ->assertJsonPath('procurement', null)
            ->assertJsonPath('finance', null);

        $this->grantPermissions(['sale.view', 'stock.view', 'po.approve', 'period.close', 'report.financial.view']);
        $this->getJson('/api/dashboard/summary')->assertOk()
            ->assertJsonPath('approvals.purchase_orders', 0)
            ->assertJsonPath('finance.period_open_for_today', true);

        $this->getJson('/api/finance/chart-of-accounts')->assertOk()->assertJsonStructure(['data' => [['code', 'name', 'account_type', 'balance']]]);
    }

    public function test_price_lists_and_effective_dated_rows(): void
    {
        $this->grantPermissions(['sale.view', 'price.manage']);
        Sanctum::actingAs($this->user);

        $list = $this->postJson('/api/price-lists', ['code' => 'WHS', 'name' => 'Wholesale list', 'sale_mode' => 'WHOLESALE', 'priority' => 10])->assertCreated()->json();
        $this->getJson('/api/price-lists')->assertOk()->assertJsonPath('0.code', 'WHS')->assertJsonPath('0.product_prices_count', 0);

        $this->postJson("/api/price-lists/{$list['id']}/items", ['product_id' => $this->amox->id, 'factor_type' => 'FIXED', 'unit_price' => '4.5', 'effective_from' => now()->subMonth()->toDateString()])->assertCreated();
        $this->postJson("/api/price-lists/{$list['id']}/items", ['product_id' => $this->amox->id, 'factor_type' => 'COST_PLUS_MARKUP', 'factor_value' => '0.35'])->assertCreated()->assertJsonPath('factor_value', '0.3500');
        $this->postJson("/api/price-lists/{$list['id']}/items", ['product_id' => $this->amox->id, 'factor_type' => 'COST_PLUS_MARKUP'])->assertStatus(422)->assertJsonValidationErrors('factor_value');

        $current = $this->getJson("/api/price-lists/{$list['id']}/items")->assertOk()->json('data');
        $this->assertCount(1, $current);
        $this->assertSame('COST_PLUS_MARKUP', $current[0]['factor_type']);

        $history = $this->getJson("/api/price-lists/{$list['id']}/items?include_history=1")->assertOk()->json('data');
        $this->assertCount(2, $history);
        $closed = collect($history)->firstWhere('factor_type', 'FIXED');
        $this->assertSame(now()->subDay()->toDateString(), substr($closed['effective_to'], 0, 10));

        $this->postJson('/api/customer-tiers', ['code' => 'GOLD', 'name' => 'Gold hospitals', 'default_price_list_id' => $list['id'], 'credit_terms_days' => 45])->assertCreated()->assertJsonPath('code', 'GOLD');
        $this->patchJson("/api/price-lists/{$list['id']}", ['is_active' => false])->assertOk()->assertJsonPath('is_active', false);
    }
}
