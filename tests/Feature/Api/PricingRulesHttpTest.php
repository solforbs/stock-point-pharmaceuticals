<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\PriceList;
use App\Models\ProductPrice;
use App\Models\RoleDiscountAuthority;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 6 — the pricing rules screens write exactly what PricingEngine and
 * PriceQuoteService read, and the rule tester shows the engine's verdict.
 * Amoxicillin: default 2.50/tablet (500/box), landed cost 2.00/tablet.
 */
class PricingRulesHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('L1', now()->addYears(2)->toDateString(), '2000', '2.0000');
        $this->grantPermissions(['price.manage', 'price.simulate', 'sale.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_a_percent_off_promotion_is_created_tested_deactivated_and_validated(): void
    {
        $this->assertSame('LIST_PRICE', $this->priceLine()['price_source']);

        $promotion = $this->postJson('/api/pricing-rules/promotions', [
            'code' => 'OCT10', 'name' => 'October 10% off', 'promo_type' => 'PERCENT_OFF',
            'effective_from' => now()->subDay()->toDateString(), 'effective_to' => now()->addMonth()->toDateString(),
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'discount_pct' => '10']],
        ])->assertCreated()->assertJsonPath('code', 'OCT10')->assertJsonPath('lines_count', 1)->assertJsonPath('lines.0.product.code', 'AMOX500')->json();

        $line = $this->priceLine();
        $this->assertSame('PROMOTION', $line['price_source']);
        $this->assertSame('450.0000', $line['break_price']);
        $this->assertTrue(collect($line['explain'])->contains(fn ($e) => str_contains($e, 'promotion OCT10')));

        $this->getJson('/api/pricing-rules/promotions?status=CURRENT')->assertOk()->assertJsonPath('total', 1);
        $this->postJson("/api/pricing-rules/promotions/{$promotion['id']}/deactivate")->assertOk()->assertJsonPath('is_active', false);
        $this->assertSame('LIST_PRICE', $this->priceLine()['price_source']);
        $this->postJson("/api/pricing-rules/promotions/{$promotion['id']}/activate")->assertOk()->assertJsonPath('is_active', true);

        // Switching to a price override replaces the lines, which must then carry a promo price.
        $this->patchJson("/api/pricing-rules/promotions/{$promotion['id']}", ['promo_type' => 'PRICE_OVERRIDE'])->assertStatus(422)->assertJsonValidationErrors('lines.0.promo_price');
        $this->patchJson("/api/pricing-rules/promotions/{$promotion['id']}", ['promo_type' => 'PRICE_OVERRIDE', 'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'promo_price' => '420']]])
            ->assertOk()->assertJsonPath('promo_type', 'PRICE_OVERRIDE');
        $this->assertSame('420.0000', $this->priceLine()['break_price']);

        $this->patchJson("/api/pricing-rules/promotions/{$promotion['id']}", ['effective_to' => now()->subDays(2)->toDateString()])->assertStatus(422)->assertJsonValidationErrors('effective_to');
        $this->patchJson("/api/pricing-rules/promotions/{$promotion['id']}", ['effective_from' => now()->subDays(10)->toDateString(), 'effective_to' => now()->subDays(2)->toDateString(), 'is_active' => false])->assertOk();
        $this->postJson("/api/pricing-rules/promotions/{$promotion['id']}/activate")->assertStatus(422)->assertJsonPath('error.code', 'PROMOTION_EXPIRED');

        $this->postJson('/api/pricing-rules/promotions', ['code' => 'OCT10', 'name' => 'dup', 'promo_type' => 'BUY_X_GET_Y', 'effective_from' => '2026-10-01', 'effective_to' => '2026-10-31',
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'buy_qty' => '10']]])
            ->assertStatus(422)->assertJsonValidationErrors(['code']);
        $this->postJson('/api/pricing-rules/promotions', ['code' => 'B10', 'name' => 'Buy ten', 'promo_type' => 'BUY_X_GET_Y', 'effective_from' => '2026-10-01', 'effective_to' => '2026-10-31', 'funded_by' => 'SUPPLIER',
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'buy_qty' => '10', 'free_qty' => '1']]])
            ->assertStatus(422)->assertJsonValidationErrors(['supplier_id']);
        $this->assertSame(1, AuditLog::where('action', 'PROMOTION_CREATED')->count());
    }

    public function test_a_buy_x_get_y_promotion_gives_bonus_goods_in_the_quote(): void
    {
        $this->postJson('/api/pricing-rules/promotions', [
            'code' => 'B10G1', 'name' => 'Buy 10 get 1', 'promo_type' => 'BUY_X_GET_Y', 'funded_by' => 'SUPPLIER', 'supplier_id' => $this->supplier->id,
            'effective_from' => now()->toDateString(), 'effective_to' => now()->addMonth()->toDateString(),
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'buy_qty' => '10', 'free_qty' => '1', 'max_free_per_order' => '3']],
        ])->assertCreated()->assertJsonPath('supplier.code', 'PDL');

        $this->assertSame('2.0000', $this->priceLine(['quantity' => 20])['bonus_qty']);
        $this->assertSame('3.0000', $this->priceLine(['quantity' => 50])['bonus_qty'], 'capped by max free per order');
    }

    public function test_quantity_breaks_on_a_price_row_are_applied_and_may_not_overlap_or_mix_types(): void
    {
        $list = PriceList::create(['organisation_id' => $this->org->id, 'code' => 'RTL', 'name' => 'Retail', 'sale_mode' => 'RETAIL', 'currency' => 'KES', 'effective_from' => now()->subYear()->toDateString(), 'is_active' => true, 'priority' => 0]);
        $row = ProductPrice::create(['price_list_id' => $list->id, 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'factor_type' => 'FIXED', 'unit_price' => '520', 'effective_from' => now()->subMonth()->toDateString()]);

        $break = $this->postJson('/api/pricing-rules/price-breaks', ['product_price_id' => $row->id, 'min_qty' => '10', 'max_qty' => '49', 'unit_price' => '480'])
            ->assertCreated()->assertJsonPath('break_type', 'STEP')->assertJsonPath('product_price.price_list.code', 'RTL')->json();
        $this->postJson('/api/pricing-rules/price-breaks', ['product_price_id' => $row->id, 'min_qty' => '50', 'unit_price' => '450'])->assertCreated();

        $this->assertSame('520.0000', $this->priceLine(['quantity' => 5])['break_price']);
        $this->assertSame('480.0000', $this->priceLine(['quantity' => 12])['break_price']);
        $this->assertSame('450.0000', $this->priceLine(['quantity' => 60])['break_price']);

        $this->postJson('/api/pricing-rules/price-breaks', ['product_price_id' => $row->id, 'min_qty' => '40', 'max_qty' => '60', 'unit_price' => '470'])->assertStatus(422)->assertJsonValidationErrors('min_qty');
        $this->postJson('/api/pricing-rules/price-breaks', ['product_price_id' => $row->id, 'min_qty' => '5', 'max_qty' => '9', 'unit_price' => '500', 'break_type' => 'MARGINAL'])->assertStatus(422)->assertJsonValidationErrors('break_type');
        $this->postJson('/api/pricing-rules/price-breaks', ['product_price_id' => $row->id, 'min_qty' => '9', 'max_qty' => '5', 'unit_price' => '500'])->assertStatus(422)->assertJsonValidationErrors('max_qty');

        $this->patchJson("/api/pricing-rules/price-breaks/{$break['id']}", ['unit_price' => '475'])->assertOk()->assertJsonPath('unit_price', '475.0000');
        $this->getJson("/api/pricing-rules/price-breaks?price_list_id={$list->id}")->assertOk()->assertJsonCount(2, 'data');
        $this->deleteJson("/api/pricing-rules/price-breaks/{$break['id']}")->assertOk();
        $this->assertSame('520.0000', $this->priceLine(['quantity' => 12])['break_price']);
    }

    public function test_discount_policy_and_role_authority_bound_a_requested_discount(): void
    {
        $role = Role::where('name', 'Test role')->firstOrFail();
        $this->getJson('/api/pricing-rules/discount-authorities')->assertOk()->assertJsonFragment(['role' => 'Test role', 'authority' => null]);
        $this->putJson("/api/pricing-rules/discount-authorities/{$role->id}", ['max_line_discount_pct' => '20', 'max_header_discount_pct' => '5', 'may_override_floor' => false])
            ->assertOk()->assertJsonPath('authority.max_line_discount_pct', '20.000');

        $policy = $this->postJson('/api/pricing-rules/discount-policies', ['product_id' => $this->amox->id, 'max_discount_pct' => '5', 'min_margin_pct' => '10', 'round_to' => 'WHOLE'])
            ->assertCreated()->assertJsonPath('product.code', 'AMOX500')->json();
        $this->postJson('/api/pricing-rules/discount-policies', ['product_id' => $this->amox->id, 'max_discount_pct' => '5', 'min_margin_pct' => '10'])->assertStatus(422)->assertJsonValidationErrors('product_id');

        $line = $this->priceLine(['requested_discount_pct' => '10']);
        $this->assertSame('475.0000', $line['unit_price'], 'capped at the product policy 5%');
        $this->assertStringContainsString('product policy', (string) $line['discount_capped_by']);

        $this->patchJson("/api/pricing-rules/discount-policies/{$policy['id']}", ['max_discount_pct' => '15'])->assertOk()->assertJsonPath('max_discount_pct', '15.000');
        $this->assertSame('450.0000', $this->priceLine(['requested_discount_pct' => '10'])['unit_price']);

        $this->deleteJson("/api/pricing-rules/discount-authorities/{$role->id}")->assertOk()->assertJsonPath('authority', null);
        $this->assertFalse(RoleDiscountAuthority::where('role_id', $role->id)->exists());
        $this->assertSame('500.0000', $this->priceLine(['requested_discount_pct' => '10'])['unit_price'], 'no authority, no discount');

        $this->deleteJson("/api/pricing-rules/discount-policies/{$policy['id']}")->assertOk();
        $this->getJson('/api/pricing-rules/discount-policies')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_customer_contract_prices_are_dated_non_overlapping_and_win_in_the_quote(): void
    {
        $contract = $this->postJson('/api/pricing-rules/customer-prices', [
            'customer_id' => $this->customer->id, 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'unit_price' => '430',
            'contract_ref' => 'TCRH-2026', 'effective_from' => now()->subDay()->toDateString(), 'effective_to' => now()->addMonths(6)->toDateString(),
        ])->assertCreated()->assertJsonPath('approver.id', $this->user->id)->assertJsonPath('customer.code', 'TCRH')->json();

        $line = $this->priceLine(['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id]);
        $this->assertSame('CUSTOMER_CONTRACT', $line['price_source']);
        $this->assertSame('430.0000', $line['break_price']);

        $base = ['customer_id' => $this->customer->id, 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'unit_price' => '420', 'contract_ref' => 'X'];
        $this->postJson('/api/pricing-rules/customer-prices', $base + ['effective_from' => now()->addMonth()->toDateString(), 'effective_to' => now()->addYear()->toDateString()])->assertStatus(422)->assertJsonValidationErrors('effective_from');
        $this->postJson('/api/pricing-rules/customer-prices', $base + ['effective_from' => now()->addYear()->toDateString()])->assertStatus(422)->assertJsonValidationErrors('effective_to');

        $this->deleteJson("/api/pricing-rules/customer-prices/{$contract['id']}")->assertStatus(422)->assertJsonPath('error.code', 'CONTRACT_IN_EFFECT');
        $this->patchJson("/api/pricing-rules/customer-prices/{$contract['id']}", ['effective_to' => now()->addMonth()->toDateString()])->assertOk();
        $future = $this->postJson('/api/pricing-rules/customer-prices', $base + ['effective_from' => now()->addMonths(2)->toDateString(), 'effective_to' => now()->addYear()->toDateString()])->assertCreated()->json();
        $this->getJson("/api/pricing-rules/customer-prices?customer_id={$this->customer->id}&status=FUTURE")->assertOk()->assertJsonPath('total', 1);
        $this->deleteJson("/api/pricing-rules/customer-prices/{$future['id']}")->assertOk();
    }

    public function test_reading_needs_a_pricing_or_sales_permission_and_writing_needs_price_manage(): void
    {
        $this->grantPermissions(['sale.view']);
        $this->getJson('/api/pricing-rules/promotions')->assertOk();
        $this->postJson('/api/pricing-rules/discount-policies', ['product_id' => $this->amox->id, 'max_discount_pct' => '5', 'min_margin_pct' => '10'])->assertStatus(403);
        $this->postJson('/api/pricing-rules/test', $this->pricePayload())->assertStatus(403);

        $this->grantPermissions(['stock.view']);
        $this->getJson('/api/pricing-rules/customer-prices')->assertStatus(403);
        $this->getJson('/api/pricing-rules/discount-authorities')->assertStatus(403);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function priceLine(array $overrides = []): array
    {
        return $this->postJson('/api/pricing-rules/test', $this->pricePayload($overrides))->assertOk()->assertJsonPath('quote_id', null)->json('lines.0');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function pricePayload(array $overrides = []): array
    {
        return $overrides + ['sale_mode' => 'RETAIL', 'store_id' => $this->store->id, 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => 1];
    }
}
