<?php

namespace Tests\Feature\Blueprint;

use App\Models\JournalEntry;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\StockReservation;
use App\Services\Sales\DispatchService;
use App\Services\Sales\OrderCreditLimitExceededException;
use App\Services\Sales\PickingService;
use App\Services\Sales\QuotationService;
use App\Services\Sales\SalesOrderService;
use App\Services\Sales\VoidSaleService;
use Illuminate\Support\Str;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 10.2 / V6 13.1 — QUOTATION → SALES ORDER (reserves stock) → PICK
 * LIST → DISPATCH (ledger OUT, revenue, AR) → PROOF OF DELIVERY, with
 * Trace 5's numbers, and a void of the dispatch-created sale.
 */
class WholesaleOrderFlowTest extends TestCase
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

    public function test_quotation_to_order_to_dispatch_posts_revenue_and_receivables_once(): void
    {
        $quotation = app(QuotationService::class)->create([
            'organisation_id' => $this->org->id, 'branch_id' => $this->branch->id, 'store_id' => $this->store->id,
            'customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'valid_until' => now()->addDays(14)->toDateString(),
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'qty' => '60', 'requested_discount_pct' => '4.0']],
        ]);
        $this->assertSame('26820.0000', (string) $quotation->grand_total, 'A quotation is a persisted seven-step quote');
        $this->assertSame('447.0000', (string) $quotation->lines[0]->unit_price);

        // Accept → sales order → confirm reserves stock by FEFO.
        $order = app(QuotationService::class)->convertToSalesOrder($quotation, ['user_id' => $this->user->id, 'idempotency_key' => (string) Str::uuid()]);
        $order = app(SalesOrderService::class)->confirm($order, $this->user->id);

        $this->assertSame('CONFIRMED', $order->status);
        $this->assertSame('12000.0000', (string) StockReservation::where('status', 'ACTIVE')->sum('qty_base'));
        $this->assertSame('12000.0000', (string) $this->balanceForFirstBatch()->qty_reserved);
        $this->assertSame(0, StockLedger::where('txn_type', 'SALE')->count(), 'Confirmation moves no stock');
        $this->assertSame('0.0000', (string) $this->customer->credit->fresh()->current_balance, 'No AR until dispatch');
        $this->assertSame('26820.0000', $this->customer->credit->fresh()->exposure(), 'But the open order already counts against credit');

        // Pick everything, then dispatch: stock leaves and revenue + AR post here.
        $pickList = app(PickingService::class)->generate($order);
        app(PickingService::class)->start($pickList, $this->user->id);
        foreach ($pickList->lines as $line) {
            app(PickingService::class)->completeLine($line, (string) $line->qty_to_pick_base);
        }
        $pickList = app(PickingService::class)->complete($pickList);

        $note = app(DispatchService::class)->createFromPickingList($pickList, ['idempotency_key' => (string) Str::uuid()]);
        $note = app(DispatchService::class)->dispatch($note, ['user_id' => $this->user->id, 'vehicle_reg' => 'KDA 123A', 'driver_name' => 'J. Ekai']);

        $this->assertSame('DISPATCHED', $note->status);
        $this->assertSame('-12000.0000', number_format((float) StockLedger::where('txn_type', 'SALE')->sum('qty_base'), 4, '.', ''));
        $this->assertSame('0.0000', (string) $this->balanceForFirstBatch()->qty_reserved, 'Reservations consumed');
        $this->assertSame('8000.0000', (string) $this->balanceForFirstBatch()->qty_on_hand);

        $sale = Sale::findOrFail($note->sale_id);
        $this->assertSame('26820.0000', (string) $sale->grand_total);
        $this->assertSame('25200.0000', (string) $sale->cost_total);
        $this->assertSame('26820.0000', (string) $this->customer->credit->fresh()->current_balance);
        $this->assertSame('26820.0000', $this->customer->credit->fresh()->exposure(), 'The order is fulfilled, so it no longer double-counts');
        $this->assertSame('FULFILLED', $order->fresh()->status);
        $this->assertSame('26820.0000', $this->accountBalance('AR_CONTROL'));

        // Proof of delivery never touches stock or the ledger again.
        $ledgerRows = StockLedger::count();
        app(DispatchService::class)->confirmDelivery($note, 'Stores officer, TCRH');
        $this->assertSame('DELIVERED', $note->fresh()->status);
        $this->assertSame($ledgerRows, StockLedger::count());
    }

    public function test_confirming_an_order_beyond_the_credit_limit_is_blocked(): void
    {
        $this->customer->credit->update(['credit_limit' => '10000.0000']);

        $order = app(SalesOrderService::class)->create([
            'organisation_id' => $this->org->id, 'branch_id' => $this->branch->id, 'store_id' => $this->store->id,
            'customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'idempotency_key' => (string) Str::uuid(),
            'lines' => [$this->saleLine('BOX', '60', '447.0000')],
        ]);

        $this->expectException(OrderCreditLimitExceededException::class);
        app(SalesOrderService::class)->confirm($order, $this->user->id);
    }

    public function test_voiding_a_dispatch_created_sale_reverses_its_stock_journal_and_receivable(): void
    {
        $order = app(SalesOrderService::class)->create([
            'organisation_id' => $this->org->id, 'branch_id' => $this->branch->id, 'store_id' => $this->store->id,
            'customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'idempotency_key' => (string) Str::uuid(),
            'lines' => [$this->saleLine('BOX', '10', '462.0000')],
        ]);
        app(SalesOrderService::class)->confirm($order, $this->user->id);
        $pickList = app(PickingService::class)->generate($order->fresh());
        app(PickingService::class)->start($pickList, $this->user->id);
        foreach ($pickList->lines as $line) {
            app(PickingService::class)->completeLine($line, (string) $line->qty_to_pick_base);
        }
        $note = app(DispatchService::class)->createFromPickingList(app(PickingService::class)->complete($pickList), ['idempotency_key' => (string) Str::uuid()]);
        $note = app(DispatchService::class)->dispatch($note, ['user_id' => $this->user->id]);
        $sale = Sale::findOrFail($note->sale_id);

        $stockBefore = (string) $this->balanceForFirstBatch()->qty_on_hand;
        $arBefore = $this->accountBalance('AR_CONTROL');

        app(VoidSaleService::class)->void($sale, 'Truck never left — order cancelled at the gate', $this->user->id);

        $this->assertSame(bcadd($stockBefore, '2000', 4), (string) $this->balanceForFirstBatch()->qty_on_hand, '10 boxes back on the shelf');
        $this->assertSame(bcsub($arBefore, '4620', 4), $this->accountBalance('AR_CONTROL'));
        $this->assertSame('0.0000', (string) $this->customer->credit->fresh()->current_balance);
        $this->assertSame('CANCELLED', $note->fresh()->status);

        $reversal = JournalEntry::where('source_doc_type', 'sale_void')->firstOrFail();
        $this->assertSame(JournalEntry::where('source_doc_type', 'delivery_note')->value('id'), $reversal->reverses_journal_id, 'Part 12.5: the reversal points at the original');
    }

    private function balanceForFirstBatch(): StockBalance
    {
        return StockBalance::where('product_id', $this->amox->id)->firstOrFail();
    }
}
