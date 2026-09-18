<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * V6 Part 10.1 and Part 24.2 — modes are branch configuration; leaving the
 * terminal's default mode is a permission-gated switch.
 */
class SaleModeTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('M1', now()->addYears(2)->toDateString(), '2000', '2.0000');
        $this->grantPermissions(['sale.create', 'sale.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_the_user_endpoint_tells_the_spa_which_modes_the_branch_trades_in(): void
    {
        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('active_branch.code', 'LDW')
            ->assertJsonPath('sale_modes', ['RETAIL', 'WHOLESALE'])
            ->assertJsonPath('default_sale_mode', 'RETAIL')
            ->assertJsonCount(1, 'branches');
    }

    public function test_switching_off_the_default_mode_needs_the_switch_permission(): void
    {
        $this->postJson('/api/pricing/quote', $this->quotePayload('RETAIL'))->assertOk();

        $this->postJson('/api/pricing/quote', $this->quotePayload('WHOLESALE'))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MODE_SWITCH_FORBIDDEN')
            ->assertJsonPath('error.details.default_mode', 'RETAIL');

        $this->grantPermissions(['sale.create', 'sale.view', 'sale.mode.switch']);
        $this->postJson('/api/pricing/quote', $this->quotePayload('WHOLESALE'))->assertOk()->assertJsonPath('sale_mode', 'WHOLESALE');
    }

    public function test_the_default_mode_is_a_branch_setting(): void
    {
        Setting::create([
            'organisation_id' => $this->org->id, 'branch_id' => $this->branch->id, 'scope' => 'pos', 'key' => 'default_sale_mode',
            'value_json' => 'WHOLESALE', 'effective_from' => now()->subDay()->toDateString(), 'set_by' => $this->user->id, 'set_at' => now(),
        ]);

        $this->getJson('/api/user')->assertJsonPath('default_sale_mode', 'WHOLESALE');
        $this->postJson('/api/pricing/quote', $this->quotePayload('WHOLESALE'))->assertOk();
        $this->postJson('/api/pricing/quote', $this->quotePayload('RETAIL'))->assertStatus(403)->assertJsonPath('error.code', 'MODE_SWITCH_FORBIDDEN');
    }

    public function test_a_mode_the_branch_does_not_trade_in_is_refused_everywhere(): void
    {
        $this->branch->update(['wholesale_enabled' => false]);
        $this->grantPermissions(['sale.create', 'sale.view', 'sale.mode.switch']);

        $this->postJson('/api/pricing/quote', $this->quotePayload('WHOLESALE'))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MODE_DISABLED')
            ->assertJsonPath('error.details.enabled_modes', ['RETAIL']);

        $this->postJson('/api/quotations', [
            'customer_id' => $this->customer->id, 'store_id' => $this->store->id, 'valid_until' => now()->addWeek()->toDateString(),
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => '1']],
        ])->assertStatus(422)->assertJsonPath('error.code', 'MODE_DISABLED');

        $this->postJson('/api/pricing/quote', $this->quotePayload('DISPENSING'))->assertStatus(422)->assertJsonPath('error.code', 'MODE_DISABLED');
    }

    /**
     * @return array<string, mixed>
     */
    private function quotePayload(string $mode): array
    {
        return [
            'sale_mode' => $mode,
            'store_id' => $this->store->id,
            'customer_id' => $mode === 'WHOLESALE' ? $this->customer->id : null,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => '1']],
        ];
    }
}
