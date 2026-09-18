<?php

namespace Tests\Feature\Blueprint;

use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Services\Sales\SaleAlreadyVoidedException;
use App\Services\Sales\VoidSaleService;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Acceptance tests AT-7 (void reverses stock and finance exactly, never
 * deletes) and AT-8 (a duplicated checkout creates one sale).
 */
class VoidAndIdempotencyTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('B-2401', now()->addDays(195)->toDateString(), '150', '2.0500');
        $this->receive('B-2405', now()->addDays(348)->toDateString(), '1800', '2.1800');
    }

    public function test_void_restores_stock_and_trial_balance_and_keeps_both_records(): void
    {
        $balancesBefore = $this->snapshotBalances();
        $trialBefore = $this->trialBalance();

        $sale = $this->checkout(
            [$this->saleLine('BOX', '1', '500.0000', ['tax_rate' => '16'])],
            [['method' => 'CASH', 'amount' => '580.0000']],
            ['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id],
        );
        $docNumber = $sale->doc_number;

        $this->assertNotSame($balancesBefore, $this->snapshotBalances());

        app(VoidSaleService::class)->void($sale, 'Posted to wrong customer', $this->user->id);

        $this->assertSame($balancesBefore, $this->snapshotBalances(), 'Stock must return to the same batches');
        $this->assertSame($trialBefore, $this->trialBalance(), 'Trial balance must be identical to pre-sale');

        $voided = Sale::findOrFail($sale->id);
        $this->assertSame('VOIDED', $voided->status);
        $this->assertSame($docNumber, $voided->doc_number, 'Document number is retained permanently');
        $this->assertSame('Posted to wrong customer', $voided->void_reason);
        $this->assertSame($this->user->id, $voided->voided_by);

        $this->assertSame(2, JournalEntry::whereIn('source_doc_type', ['sale', 'sale_void'])->count(), 'Original and reversing journal both remain');
        // The box was FEFO-split across B-2401 and B-2405, so there are two
        // SALE rows; each must have exactly one compensating SALE_VOID row
        // pointing back at it, and all four remain.
        $saleRows = StockLedger::where('txn_type', 'SALE')->get();
        $this->assertCount(2, $saleRows);
        foreach ($saleRows as $row) {
            $this->assertSame(1, StockLedger::where('txn_type', 'SALE_VOID')->where('reverses_ledger_id', $row->id)->count());
            $this->assertSame(bcmul((string) $row->qty_base, '-1', 4), (string) StockLedger::where('reverses_ledger_id', $row->id)->value('qty_base'));
        }
        $this->assertTrue(AuditLog::where('action', 'SALE_VOIDED')->where('entity_id', $sale->id)->exists());
    }

    public function test_a_sale_cannot_be_voided_twice(): void
    {
        $sale = $this->checkout([$this->saleLine('TAB', '2', '2.7500')], [['method' => 'CASH', 'amount' => '5.5000']]);
        app(VoidSaleService::class)->void($sale, 'first', $this->user->id);

        $this->expectException(SaleAlreadyVoidedException::class);
        app(VoidSaleService::class)->void($sale->fresh(), 'second', $this->user->id);
    }

    public function test_a_duplicated_checkout_request_creates_exactly_one_sale(): void
    {
        $key = 'terminal-T02-0001';

        $first = $this->checkout([$this->saleLine('BOX', '1', '500.0000')], [['method' => 'CASH', 'amount' => '500.0000']], ['idempotency_key' => $key]);
        $ledgerAfterFirst = StockLedger::count();

        $second = $this->checkout([$this->saleLine('BOX', '1', '500.0000')], [['method' => 'CASH', 'amount' => '500.0000']], ['idempotency_key' => $key]);

        $this->assertSame($first->id, $second->id, 'Second response is the original sale');
        $this->assertSame(1, Sale::count());
        $this->assertSame($ledgerAfterFirst, StockLedger::count(), 'No duplicate stock movement');
        $this->assertSame(1, JournalEntry::where('source_doc_type', 'sale')->count());
    }

    /**
     * @return array<string, string>
     */
    private function snapshotBalances(): array
    {
        return StockBalance::orderBy('batch_id')->get()
            ->mapWithKeys(fn (StockBalance $b) => [$b->batch_id => (string) $b->qty_on_hand])
            ->all();
    }
}
