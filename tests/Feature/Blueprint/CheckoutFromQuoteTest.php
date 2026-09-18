<?php

namespace Tests\Feature\Blueprint;

use App\Models\AuditLog;
use App\Models\Promotion;
use App\Models\PromotionLine;
use App\Models\Sale;
use App\Models\StockLedger;
use App\Services\Pricing\PriceQuoteService;
use App\Services\Sales\ApprovalRequiredException;
use App\Services\Sales\CheckoutService;
use App\Services\Sales\PaymentMismatchException;
use Illuminate\Support\Str;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 4.12 quote binding: the sale posts exactly what the server quoted,
 * bonus lines become SALE_BONUS stock movements, approvals are named, and
 * a FEFO override is audited.
 */
class CheckoutFromQuoteTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('B-2405', now()->addDays(348)->toDateString(), '100', '420.0000', 'BOX');
        $this->tierBWithBreaks();
        $this->discountPolicy();
        $this->grantAuthority('8.000', '5.000');
    }

    public function test_a_quoted_wholesale_sale_posts_the_quoted_figures(): void
    {
        $quote = app(PriceQuoteService::class)->quote($this->quoteRequest([
            $this->quoteLine('BOX', '60', ['requested_discount_pct' => '4.0']),
        ]));

        $sale = app(CheckoutService::class)->checkoutFromQuote($quote['quote_id'], [
            'store_id' => $this->store->id, 'user_id' => $this->user->id, 'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->assertSame($quote['quote_id'], $sale->quote_id);
        $this->assertSame('27720.0000', (string) $sale->subtotal, '60 × 462 break price');
        $this->assertSame('900.0000', (string) $sale->discount_total);
        $this->assertSame('26820.0000', (string) $sale->grand_total);
        $this->assertSame('447.0000', (string) $sale->lines[0]->unit_price);
        $this->assertSame('MANUAL_CAPPED_BY_FLOOR', $sale->lines[0]->discount_source);
        $this->assertSame('25200.0000', (string) $sale->cost_total, 'COGS at batch cost: 12,000 tablets × 2.10');
        $this->assertSame('26820.0000', (string) $this->customer->credit->fresh()->current_balance, 'Unpaid wholesale sale goes to AR');
    }

    public function test_bonus_goods_post_as_their_own_sale_bonus_line(): void
    {
        $promo = Promotion::create([
            'organisation_id' => $this->org->id, 'code' => 'AMOX-10+1', 'name' => '10+1',
            'promo_type' => 'BUY_X_GET_Y', 'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => now()->addMonth()->toDateString(), 'funded_by' => 'SUPPLIER', 'is_active' => true,
        ]);
        PromotionLine::create(['promotion_id' => $promo->id, 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'buy_qty' => '10', 'free_qty' => '1', 'repeat' => true]);

        $quote = app(PriceQuoteService::class)->quote($this->quoteRequest([$this->quoteLine('BOX', '20')]));
        $sale = app(CheckoutService::class)->checkoutFromQuote($quote['quote_id'], [
            'store_id' => $this->store->id, 'user_id' => $this->user->id, 'idempotency_key' => (string) Str::uuid(),
        ]);

        $bonus = $sale->lines->firstWhere('is_bonus', true);
        $this->assertNotNull($bonus);
        $this->assertSame('2.0000', (string) $bonus->qty);
        $this->assertSame('0.0000', (string) $bonus->unit_price);
        $this->assertSame('840.0000', (string) $bonus->line_cost, '2 free boxes still cost 420 each');
        $this->assertSame('-400.0000', number_format((float) StockLedger::where('txn_type', 'SALE_BONUS')->sum('qty_base'), 4, '.', ''), '2 boxes × 200 tablets left as SALE_BONUS');
        $this->assertSame('9600.0000', (string) $sale->grand_total, '20 × 480; the free boxes carry no revenue');
        $this->assertSame('9240.0000', (string) $sale->cost_total, '22 boxes × 420');
    }

    public function test_a_flagged_quote_cannot_post_without_a_named_approver(): void
    {
        $this->discountPolicy(['min_margin_pct' => '0']);
        $quote = app(PriceQuoteService::class)->quote($this->quoteRequest([
            $this->quoteLine('BOX', '60', ['requested_discount_pct' => '6.0']),
        ]));
        $this->assertTrue($quote['approval_required']);

        try {
            app(CheckoutService::class)->checkoutFromQuote($quote['quote_id'], [
                'store_id' => $this->store->id, 'user_id' => $this->user->id, 'idempotency_key' => (string) Str::uuid(),
            ]);
            $this->fail('Expected ApprovalRequiredException');
        } catch (ApprovalRequiredException $e) {
            $this->assertSame(['L1'], $e->lineRefs);
        }
        $this->assertSame(0, Sale::count());

        $sale = app(CheckoutService::class)->checkoutFromQuote($quote['quote_id'], [
            'store_id' => $this->store->id, 'user_id' => $this->user->id, 'idempotency_key' => (string) Str::uuid(),
            'approved_by' => $this->user->id,
        ]);
        $this->assertSame($this->user->id, $sale->lines[0]->approved_by, 'The invoice can always answer "who authorised this price?"');
    }

    public function test_a_fefo_override_takes_the_named_batch_and_is_audited(): void
    {
        $later = $this->receive('B-2409', now()->addDays(500)->toDateString(), '50', '420.0000', 'BOX');

        $quote = app(PriceQuoteService::class)->quote($this->quoteRequest([
            $this->quoteLine('BOX', '5', ['batch_id' => $later->id, 'override_reason' => 'Customer requires batch matching earlier delivery']),
        ]));
        $sale = app(CheckoutService::class)->checkoutFromQuote($quote['quote_id'], [
            'store_id' => $this->store->id, 'user_id' => $this->user->id, 'idempotency_key' => (string) Str::uuid(),
        ]);

        $line = $sale->lines[0];
        $this->assertTrue($line->fefo_overridden);
        $this->assertCount(1, $line->batchAllocations);
        $this->assertSame($later->id, $line->batchAllocations[0]->batch_id, 'FEFO would have chosen B-2405');
        $this->assertTrue(AuditLog::where('action', 'FEFO_OVERRIDDEN')->where('entity_id', $line->id)->exists());
    }

    public function test_the_same_quote_and_key_twice_posts_one_sale(): void
    {
        $quote = app(PriceQuoteService::class)->quote($this->quoteRequest([$this->quoteLine('BOX', '1')]));
        $meta = ['store_id' => $this->store->id, 'user_id' => $this->user->id, 'idempotency_key' => 'T02-0091'];

        $first = app(CheckoutService::class)->checkoutFromQuote($quote['quote_id'], $meta);
        $second = app(CheckoutService::class)->checkoutFromQuote($quote['quote_id'], $meta);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Sale::count());
    }

    public function test_a_walk_in_sale_must_be_paid_in_full(): void
    {
        $quote = app(PriceQuoteService::class)->quote($this->quoteRequest(
            [$this->quoteLine('BOX', '1')], ['sale_mode' => 'RETAIL', 'customer_id' => null],
        ));

        $this->expectException(PaymentMismatchException::class);
        app(CheckoutService::class)->checkoutFromQuote($quote['quote_id'], [
            'store_id' => $this->store->id, 'user_id' => $this->user->id, 'idempotency_key' => (string) Str::uuid(),
            'payments' => [['method' => 'CASH', 'amount' => '100.0000']],
        ]);
    }
}
