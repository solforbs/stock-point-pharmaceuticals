<?php

namespace Tests\Feature\Blueprint;

use App\Models\AccountsReceivable;
use App\Models\CustomerCredit;
use App\Models\Sale;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\Sales\CreditHoldException;
use App\Services\Sales\CreditLimitExceededException;
use Illuminate\Support\Str;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 10.4 / V6 13.2 — credit_available = credit_limit − outstanding
 * invoices − open sales orders − current cart. Decision 5: block, never
 * silently allow.
 */
class CreditControlTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('B-2405', now()->addDays(348)->toDateString(), '100000', '2.1000');
    }

    public function test_a_credit_sale_within_the_limit_raises_receivables(): void
    {
        $sale = $this->checkout(
            [$this->saleLine('BOX', '60', '447.0000')],
            [],
            ['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id],
        );

        $this->assertSame('26820.0000', (string) $sale->grand_total);
        $this->assertSame('26820.0000', (string) $this->customer->credit->fresh()->current_balance);
        $this->assertSame('26820.0000', (string) AccountsReceivable::where('sale_id', $sale->id)->value('amount'));
        $this->assertSame('26820.0000', $this->accountBalance('AR_CONTROL'));
    }

    public function test_a_sale_that_breaches_the_limit_is_blocked_and_nothing_persists(): void
    {
        // Limit 1,000,000 · invoices 620,000 · open orders 240,000 · cart 180,000 → over by 40,000.
        CustomerCredit::where('customer_id', $this->customer->id)->update(['current_balance' => '620000.0000']);
        $this->openSalesOrder('240000.0000');

        try {
            $this->checkout(
                [$this->saleLine('BOX', '400', '450.0000')],
                [],
                ['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id],
            );
            $this->fail('Expected CreditLimitExceededException');
        } catch (CreditLimitExceededException $e) {
            $this->assertSame('1000000.0000', $e->limit);
            $this->assertSame('860000.0000', $e->exposure, 'Exposure counts invoices and open orders');
            $this->assertSame('40000.0000', $e->shortfall);
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame('620000.0000', (string) $this->customer->credit->fresh()->current_balance);
    }

    public function test_a_customer_on_credit_hold_cannot_buy_on_credit(): void
    {
        CustomerCredit::where('customer_id', $this->customer->id)->update(['on_hold' => true, 'hold_reason' => 'Overdue 90+ days']);

        $this->expectException(CreditHoldException::class);

        $this->checkout(
            [$this->saleLine('TAB', '1', '2.7500')],
            [],
            ['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id],
        );
    }

    public function test_a_cash_sale_never_touches_credit(): void
    {
        CustomerCredit::where('customer_id', $this->customer->id)->update(['current_balance' => '999999.0000']);

        $sale = $this->checkout(
            [$this->saleLine('BOX', '1', '500.0000')],
            [['method' => 'CASH', 'amount' => '500.0000']],
            ['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id],
        );

        $this->assertSame('POSTED', $sale->status);
        $this->assertSame('999999.0000', (string) $this->customer->credit->fresh()->current_balance);
    }

    private function openSalesOrder(string $value): void
    {
        $order = SalesOrder::create([
            'organisation_id' => $this->org->id,
            'branch_id' => $this->branch->id,
            'store_id' => $this->store->id,
            'sale_mode' => 'WHOLESALE',
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'doc_number' => 'SO-TEST-'.Str::random(6),
            'status' => 'CONFIRMED',
            'idempotency_key' => (string) Str::uuid(),
            'subtotal' => $value, 'grand_total' => $value,
        ]);

        SalesOrderLine::create([
            'sales_order_id' => $order->id,
            'line_number' => 1,
            'product_id' => $this->amox->id,
            'uom_id' => $this->uoms['BOX']->id,
            'qty' => '1', 'qty_base' => '200',
            'list_price' => $value, 'unit_price' => $value,
            'line_total' => $value,
        ]);
    }
}
