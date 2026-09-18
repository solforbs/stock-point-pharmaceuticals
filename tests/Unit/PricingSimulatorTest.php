<?php

namespace Tests\Unit;

use App\Services\Pricing\Money;
use App\Services\Pricing\PricingSimulator;
use PHPUnit\Framework\TestCase;

/**
 * Part 4.10 / 24.4–24.7 — pure arithmetic, checked against the blueprint's
 * numbers. No database.
 */
class PricingSimulatorTest extends TestCase
{
    public function test_the_worked_example_caps_at_the_floor_and_rounds_to_fifty_cents(): void
    {
        $out = (new PricingSimulator)->simulate([
            'cost' => '420', 'list_price' => '462', 'quantity' => '60',
            'discount_pct' => '4', 'min_margin_pct' => '6', 'round_to' => '0.50',
        ]);

        $this->assertSame('446.8100', $out['floor_price']);
        $this->assertTrue($out['floor_breached']);
        $this->assertSame('447.0000', $out['unit_price']);
        $this->assertSame('26820.0000', $out['revenue']);
        $this->assertSame('1620.0000', $out['gross_profit']);
        $this->assertSame('6.0403', $out['margin_pct']);
        $this->assertSame('6.4286', $out['markup_pct']);
        $this->assertSame('3.2879', $out['max_allowable_discount_pct'], '(462 − 446.81) ÷ 462, shown as 3.29% in the blueprint');
    }

    public function test_a_self_funded_ten_plus_one_on_a_sixteen_percent_margin_earns_nothing(): void
    {
        $out = (new PricingSimulator)->simulate([
            'cost' => '420', 'list_price' => '462', 'quantity' => '10',
            'bonus_buy_qty' => '10', 'bonus_free_qty' => '1', 'funded_by' => 'US',
        ]);

        $this->assertSame('11.0000', $out['units_issued']);
        $this->assertSame('4620.0000', $out['revenue']);
        $this->assertSame('4620.0000', bcadd($out['line_cost'], $out['bonus_cost'], 4));
        $this->assertSame('0.0000', $out['effective_margin_pct']);
    }

    public function test_a_supplier_funded_bonus_keeps_the_margin(): void
    {
        $out = (new PricingSimulator)->simulate([
            'cost' => '420', 'list_price' => '462', 'quantity' => '10',
            'bonus_buy_qty' => '10', 'bonus_free_qty' => '1', 'funded_by' => 'SUPPLIER',
        ]);

        $this->assertSame('0.0000', $out['bonus_cost']);
        $this->assertSame('420.0000', $out['gross_profit']);
        $this->assertSame('9.0909', $out['effective_margin_pct']);
    }

    public function test_break_even_volume_for_a_five_percent_discount_on_sixteen_percent_margin(): void
    {
        $out = (new PricingSimulator)->simulate([
            'cost' => '420', 'list_price' => '500', 'quantity' => '1', 'discount_pct' => '5', 'monthly_volume' => '1000',
        ]);

        $this->assertSame('16.0000', $out['undiscounted_margin_pct']);
        $this->assertSame('45.45', $out['break_even_volume_increase_pct']);
        $this->assertSame('-25000.00', $out['monthly_profit_delta'], '1,000 units × KES 25 given away');
    }

    public function test_rounding_helpers_are_half_up_and_step_aware(): void
    {
        $this->assertSame('446.8100', Money::round('446.808', 2));
        $this->assertSame('447.0000', Money::roundToStep('446.81', '0.50'));
        $this->assertSame('446.5000', Money::roundToStep('446.74', '0.50'));
        $this->assertSame('447.0000', Money::ceilToStep('446.81', '0.50'));
        $this->assertSame('2.5000', Money::round('2.49995', 4));
    }
}
