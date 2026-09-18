<?php

namespace Tests\Feature\Blueprint;

use App\Models\CustomerPrice;
use App\Models\CustomerTier;
use App\Models\PriceBreak;
use App\Models\PriceList;
use App\Models\ProductPrice;
use App\Models\Promotion;
use App\Models\PromotionLine;
use App\Services\Pricing\PricingEngine;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 4.2 / V6 8.2 — the six-rank price hierarchy resolves
 * deterministically, quantity breaks apply, and contract vs promotion
 * settles in the customer's favour.
 */
class PricingHierarchyTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_rank_6_falls_back_to_the_product_default_price_derived_per_uom(): void
    {
        $quote = $this->quote('60');

        // default_price is per base unit (2.50/tablet); Part 5.6 derives the box price.
        $this->assertSame('500.0000', $quote['unit_price']);
        $this->assertSame('LIST_PRICE', $quote['source']);
    }

    public function test_rank_4_mode_price_list_with_a_step_quantity_break(): void
    {
        $row = $this->modeList('500.0000');
        PriceBreak::create(['product_price_id' => $row->id, 'min_qty' => '1', 'max_qty' => '9', 'unit_price' => '500.0000', 'break_type' => 'STEP']);
        PriceBreak::create(['product_price_id' => $row->id, 'min_qty' => '10', 'max_qty' => '49', 'unit_price' => '480.0000', 'break_type' => 'STEP']);
        PriceBreak::create(['product_price_id' => $row->id, 'min_qty' => '50', 'max_qty' => '199', 'unit_price' => '462.0000', 'break_type' => 'STEP']);
        PriceBreak::create(['product_price_id' => $row->id, 'min_qty' => '200', 'max_qty' => null, 'unit_price' => '445.0000', 'break_type' => 'STEP']);

        $this->assertSame('500.0000', $this->quote('5')['unit_price']);
        $this->assertSame('462.0000', $this->quote('60')['unit_price']);
        $this->assertSame('445.0000', $this->quote('250')['unit_price']);
    }

    public function test_rank_3_customer_tier_list_beats_the_mode_list(): void
    {
        $this->modeList('500.0000');
        $this->tierList('480.0000');

        $quote = $this->quote('60');

        $this->assertSame('480.0000', $quote['unit_price']);
        $this->assertStringContainsString('Rank 3', implode(' ', $quote['explain']));
    }

    public function test_rank_2_promotion_beats_the_tier_list(): void
    {
        $this->tierList('480.0000');
        $this->promotion('460.0000');

        $quote = $this->quote('60');

        $this->assertSame('460.0000', $quote['unit_price']);
        $this->assertSame('PROMOTION', $quote['source']);
    }

    public function test_rank_1_contract_wins_when_it_is_cheaper_than_the_promotion(): void
    {
        $this->tierList('480.0000');
        $this->promotion('460.0000');
        $this->contract('372.0000');

        $quote = $this->quote('60');

        $this->assertSame('372.0000', $quote['unit_price']);
        $this->assertSame('CUSTOMER_CONTRACT', $quote['source']);
    }

    public function test_the_cheaper_of_contract_and_promotion_is_applied_for_the_customer(): void
    {
        $this->tierList('480.0000');
        $this->promotion('460.0000');
        $this->contract('470.0000');

        $quote = $this->quote('60');

        $this->assertSame('460.0000', $quote['unit_price']);
        $this->assertSame('PROMOTION', $quote['source']);
    }

    public function test_an_expired_contract_is_ignored(): void
    {
        $this->modeList('500.0000');
        $this->contract('372.0000', from: now()->subYear()->toDateString(), to: now()->subDay()->toDateString());

        $this->assertSame('500.0000', $this->quote('60')['unit_price']);
    }

    /**
     * @return array{unit_price: string, source: string, list_price: string, explain: list<string>}
     */
    private function quote(string $qty): array
    {
        return app(PricingEngine::class)->quote(
            $this->amox, $this->uom('BOX'), $qty, $this->customer->fresh(), 'WHOLESALE', $this->branch->id, '420.0000',
        );
    }

    private function modeList(string $boxPrice): ProductPrice
    {
        $list = PriceList::create([
            'organisation_id' => $this->org->id, 'code' => 'WHOLESALE-STD', 'name' => 'Wholesale standard',
            'sale_mode' => 'WHOLESALE', 'effective_from' => now()->subMonth()->toDateString(), 'is_active' => true,
        ]);

        return ProductPrice::create([
            'price_list_id' => $list->id, 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id,
            'factor_type' => 'FIXED', 'unit_price' => $boxPrice, 'effective_from' => now()->subMonth()->toDateString(),
        ]);
    }

    private function tierList(string $boxPrice): void
    {
        $tier = CustomerTier::create(['organisation_id' => $this->org->id, 'code' => 'B', 'name' => 'Tier B']);
        $this->customer->update(['tier_id' => $tier->id]);

        $list = PriceList::create([
            'organisation_id' => $this->org->id, 'code' => 'WHOLESALE-TIER-B', 'name' => 'Tier B',
            'sale_mode' => 'WHOLESALE', 'tier_id' => $tier->id, 'effective_from' => now()->subMonth()->toDateString(), 'is_active' => true,
        ]);
        ProductPrice::create([
            'price_list_id' => $list->id, 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id,
            'factor_type' => 'FIXED', 'unit_price' => $boxPrice, 'effective_from' => now()->subMonth()->toDateString(),
        ]);
    }

    private function promotion(string $boxPrice): void
    {
        $promo = Promotion::create([
            'organisation_id' => $this->org->id, 'code' => 'AMOX-SEP', 'name' => 'Amoxil September',
            'promo_type' => 'PRICE_OVERRIDE', 'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => now()->addMonth()->toDateString(), 'funded_by' => 'SUPPLIER', 'is_active' => true,
        ]);
        PromotionLine::create([
            'promotion_id' => $promo->id, 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id,
            'promo_price' => $boxPrice,
        ]);
    }

    private function contract(string $boxPrice, ?string $from = null, ?string $to = null): void
    {
        CustomerPrice::create([
            'organisation_id' => $this->org->id, 'customer_id' => $this->customer->id,
            'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id,
            'unit_price' => $boxPrice, 'contract_ref' => 'TCRH/2026/01',
            'effective_from' => $from ?? now()->subMonth()->toDateString(),
            'effective_to' => $to ?? now()->addMonths(6)->toDateString(),
        ]);
    }
}
