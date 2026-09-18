<?php

namespace Tests\Feature\Blueprint;

use App\Models\PriceList;
use App\Models\PriceQuoteLog;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUom;
use App\Models\Promotion;
use App\Models\PromotionLine;
use App\Services\Pricing\PriceChangedException;
use App\Services\Pricing\PriceQuoteService;
use App\Services\Pricing\QuoteExpiredException;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 4.5–4.9 / V6 8.2 — the seven-step quote, verified against the
 * blueprint's worked example: Turkana County Referral Hospital, Tier B,
 * Amoxicillin 500mg box, WAC 420, list 500, 60 boxes, 4% requested.
 */
class PriceQuoteTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();

        // 100 boxes at KES 420/box → WAC 2.10/tablet → 420/box.
        $this->receive('B-2405', now()->addDays(348)->toDateString(), '100', '420.0000', 'BOX');
        $this->tierBWithBreaks();
        $this->discountPolicy();
    }

    public function test_the_worked_example_resolves_to_447_capped_by_the_margin_floor(): void
    {
        $this->grantAuthority('8.000', '5.000');

        $quote = app(PriceQuoteService::class)->quote($this->quoteRequest([
            $this->quoteLine('BOX', '60', ['requested_discount_pct' => '4.0', 'requested_discount_reason' => 'Institutional buyer']),
        ]));

        $line = $quote['lines'][0];
        $this->assertSame('500.0000', $line['list_price']);
        $this->assertSame('462.0000', $line['break_price'], 'Quantity break 50–199');
        $this->assertSame('446.8100', $line['floor_price'], '420 ÷ 0.94');
        $this->assertSame('447.0000', $line['unit_price'], 'Capped at the floor, rounded to 0.50');
        $this->assertSame('MANUAL_CAPPED_BY_FLOOR', $line['discount_source']);
        $this->assertSame('900.0000', $line['discount_amount'], '60 × (462 − 447)');
        $this->assertSame('26820.0000', $line['line_total']);
        $this->assertSame('420.0000', $line['unit_cost']);
        $this->assertSame('1620.0000', $line['gross_profit']);
        $this->assertSame('6.0403', $line['margin_pct']);
        $this->assertSame('6.4286', $line['markup_pct']);
        $this->assertFalse($line['floor_breached']);
        $this->assertFalse($line['approval_required']);
        $this->assertStringContainsString('Margin floor 6.000% requires minimum 446.81', implode("\n", $line['explain']));

        $this->assertSame('26820.0000', $quote['totals']['grand_total']);
        $this->assertSame('1620.0000', $quote['totals']['gross_profit']);
        $this->assertNotNull($quote['quote_id']);
        $this->assertSame(180, $quote['min_shelf_life_days'], 'Hospitals get the institutional shelf-life rule');
    }

    public function test_the_most_restrictive_limit_wins_a_cashier_gets_two_percent(): void
    {
        $this->grantAuthority('2.000', '0', false, 'Cashier');

        $line = app(PriceQuoteService::class)->quote($this->quoteRequest([
            $this->quoteLine('BOX', '60', ['requested_discount_pct' => '4.0']),
        ]))['lines'][0];

        $this->assertSame('MANUAL_CAPPED_BY_AUTHORITY', $line['discount_source']);
        $this->assertSame('your authority', $line['discount_capped_by']);
        $this->assertSame('453.0000', $line['unit_price'], '462 × 0.98 = 452.76 → nearest 0.50');
    }

    public function test_a_user_with_no_authority_cannot_discount_at_all(): void
    {
        $line = app(PriceQuoteService::class)->quote($this->quoteRequest([
            $this->quoteLine('BOX', '60', ['requested_discount_pct' => '4.0']),
        ]))['lines'][0];

        $this->assertSame('462.0000', $line['unit_price']);
        $this->assertSame('0.0000', $line['discount_amount']);
    }

    public function test_a_discount_above_the_approval_threshold_is_flagged(): void
    {
        $this->discountPolicy(['min_margin_pct' => '0']);
        $this->grantAuthority('8.000');

        $line = app(PriceQuoteService::class)->quote($this->quoteRequest([
            $this->quoteLine('BOX', '60', ['requested_discount_pct' => '6.0']),
        ]))['lines'][0];

        $this->assertSame('434.5000', $line['unit_price'], '462 × 0.94 = 434.28 → 434.50');
        $this->assertTrue($line['approval_required']);
        $this->assertFalse($line['floor_breached']);
    }

    public function test_a_manager_may_override_the_floor_with_a_reason_and_it_is_flagged(): void
    {
        $this->grantAuthority('15.000', '10.000', true, 'Sales manager');

        $line = app(PriceQuoteService::class)->quote($this->quoteRequest([
            $this->quoteLine('BOX', '60', ['requested_discount_pct' => '8.0', 'requested_discount_reason' => 'Tender price match']),
        ]))['lines'][0];

        $this->assertSame('425.0000', $line['unit_price'], '462 × 0.92 = 425.04 → 425.00, below the 446.81 floor');
        $this->assertTrue($line['floor_breached']);
        $this->assertTrue($line['approval_required'], 'A floor override still needs a named approver on the sale');
        $this->assertSame('MANUAL_FLOOR_OVERRIDE', $line['discount_source']);
    }

    public function test_the_same_request_without_a_reason_is_capped_not_overridden(): void
    {
        $this->grantAuthority('15.000', '10.000', true, 'Sales manager');

        $line = app(PriceQuoteService::class)->quote($this->quoteRequest([
            $this->quoteLine('BOX', '60', ['requested_discount_pct' => '8.0']),
        ]))['lines'][0];

        $this->assertSame('447.0000', $line['unit_price']);
        $this->assertFalse($line['floor_breached']);
    }

    public function test_tax_is_resolved_from_effective_dated_rates_and_customer_status(): void
    {
        $this->vat16();

        $standard = app(PriceQuoteService::class)->quote($this->quoteRequest([$this->quoteLine('BOX', '60')]))['lines'][0];
        $this->assertSame('16.000', $standard['tax_rate']);
        $this->assertSame('VAT_STD', $standard['tax_code']);
        $this->assertSame('4435.2000', $standard['tax_amount'], '27,720 net × 16%');
        $this->assertSame('32155.2000', $standard['line_total']);

        $this->customer->update(['tax_status' => 'EXEMPT', 'exemption_ref' => 'KRA/EX/2026/17']);
        $exempt = app(PriceQuoteService::class)->quote($this->quoteRequest([$this->quoteLine('BOX', '60')]))['lines'][0];
        $this->assertSame('0.000', $exempt['tax_rate']);
        $this->assertSame('VAT-EX', $exempt['tax_code']);
        $this->assertSame('27720.0000', $exempt['line_total']);
    }

    public function test_a_tax_inclusive_price_list_is_netted_before_discounting(): void
    {
        $this->vat16();
        // Retail list: shelf price 580 includes 16% VAT → 500 net.
        $list = PriceList::create([
            'organisation_id' => $this->org->id, 'code' => 'RETAIL-STD', 'name' => 'Retail',
            'sale_mode' => 'RETAIL', 'prices_include_tax' => true, 'effective_from' => now()->subMonth()->toDateString(), 'is_active' => true,
        ]);
        ProductPrice::create([
            'price_list_id' => $list->id, 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id,
            'factor_type' => 'FIXED', 'unit_price' => '580.0000', 'effective_from' => now()->subMonth()->toDateString(),
        ]);

        $line = app(PriceQuoteService::class)->quote($this->quoteRequest(
            [$this->quoteLine('BOX', '1')], ['sale_mode' => 'RETAIL', 'customer_id' => null],
        ))['lines'][0];

        $this->assertSame('500.0000', $line['break_price']);
        $this->assertSame('80.0000', $line['tax_amount']);
        $this->assertSame('580.0000', $line['line_total'], 'The customer pays the shelf price');
    }

    public function test_bonus_goods_are_a_quantity_with_a_cost_not_a_discount(): void
    {
        $promo = Promotion::create([
            'organisation_id' => $this->org->id, 'code' => 'AMOX-10+1', 'name' => 'Amoxil 10+1',
            'promo_type' => 'BUY_X_GET_Y', 'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => now()->addMonth()->toDateString(), 'funded_by' => 'US', 'is_active' => true,
        ]);
        PromotionLine::create([
            'promotion_id' => $promo->id, 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id,
            'buy_qty' => '10', 'free_qty' => '1', 'repeat' => true,
        ]);

        $quote = app(PriceQuoteService::class)->quote($this->quoteRequest([$this->quoteLine('BOX', '60')]));
        $line = $quote['lines'][0];

        $this->assertSame('6.0000', $line['bonus_qty']);
        $this->assertSame('US', $line['bonus_funded_by']);
        $this->assertSame('462.0000', $line['unit_price'], 'Bonus does not touch the unit price');
        $this->assertSame('2520.0000', $line['bonus_cost'], '6 free boxes × 420 leave the warehouse at full cost');
        $this->assertSame('9.0909', $line['margin_pct']);
        $this->assertSame('0.0000', $line['effective_margin_pct'], 'Part 4.6: 10+1 at 462 on a 420 cost wipes out the entire profit');
        $this->assertSame('27720.0000', $quote['totals']['total_cost'], '66 boxes × 420');
    }

    public function test_a_header_discount_is_allocated_pro_rata_and_the_last_line_absorbs_rounding(): void
    {
        $this->grantAuthority('15.000', '15.000', false, 'Sales manager');
        $products = [];
        foreach (['26820', '12400', '8780'] as $i => $qty) {
            $p = Product::create([
                'organisation_id' => $this->org->id, 'code' => "LINE{$i}", 'name' => "Line {$i}",
                'base_uom_id' => $this->uoms['TAB']->id, 'is_discrete' => true, 'default_price' => '1.0000', 'is_active' => true,
            ]);
            ProductUom::create(['product_id' => $p->id, 'uom_id' => $this->uoms['TAB']->id, 'factor_to_base' => 1, 'is_base' => true, 'is_sales' => true]);
            $products[] = ['product_id' => $p->id, 'uom_id' => $this->uoms['TAB']->id, 'quantity' => $qty];
        }

        $quote = app(PriceQuoteService::class)->quote($this->quoteRequest($products, ['header_discount' => '5000']));

        $allocated = array_column($quote['lines'], 'header_discount_allocated');
        $this->assertSame(['2793.7500', '1291.6700', '914.5800'], $allocated);
        $this->assertSame('5000.0000', $quote['totals']['discount']);
        $this->assertSame('43000.0000', $quote['totals']['grand_total']);
    }

    public function test_every_quote_is_logged_and_revalidates_unchanged(): void
    {
        $quote = app(PriceQuoteService::class)->quote($this->quoteRequest([$this->quoteLine('BOX', '60')]));

        $log = PriceQuoteLog::where('quote_id', $quote['quote_id'])->firstOrFail();
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame('27720.0000', $log->response_json['totals']['grand_total'] ?? null, '60 × 462, no discount requested');

        ['quote' => $again] = app(PriceQuoteService::class)->revalidate($quote['quote_id']);
        $this->assertSame($quote['totals'], $again['totals']);
    }

    public function test_an_expired_quote_is_refused(): void
    {
        $quote = app(PriceQuoteService::class)->quote($this->quoteRequest([$this->quoteLine('BOX', '60')]));
        DB::table('price_quote_logs')->where('quote_id', $quote['quote_id'])->update(['expires_at' => now()->subMinute()]);

        $this->expectException(QuoteExpiredException::class);
        app(PriceQuoteService::class)->revalidate($quote['quote_id']);
    }

    public function test_a_quote_whose_cost_basis_moved_is_refused_with_both_totals(): void
    {
        $this->grantAuthority('8.000');
        $quote = app(PriceQuoteService::class)->quote($this->quoteRequest([
            $this->quoteLine('BOX', '60', ['requested_discount_pct' => '4.0']),
        ]));

        // A dearer receipt lifts WAC, so the floor (and the capped price) rises.
        $this->receive('B-2406', now()->addDays(400)->toDateString(), '100', '460.0000', 'BOX');

        try {
            app(PriceQuoteService::class)->revalidate($quote['quote_id']);
            $this->fail('Expected PriceChangedException');
        } catch (PriceChangedException $e) {
            $this->assertSame('26820.0000', $e->oldTotal);
            $this->assertSame('28110.0000', $e->newTotal, 'WAC 440 → floor 468.09 → rounds up to 468.50 × 60');
            $this->assertSame('unit_price', $e->changedLines[0]['field']);
        }
    }

    public function test_fractional_quantities_of_discrete_products_are_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(PriceQuoteService::class)->quote($this->quoteRequest([$this->quoteLine('STR', '2.5')]));
    }
}
