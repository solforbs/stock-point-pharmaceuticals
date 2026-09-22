<?php

namespace Tests\Feature\Blueprint;

use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleLineBatchAllocation;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Store;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Inventory\StockTransferService;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Correction #2 (real FEFO allocation) and acceptance test AT-4: every sale
 * line's batch allocations sum exactly to qty_base, and the Part 7.4 worked
 * example allocates the way the blueprint says it must.
 */
class BatchAllocationAndFefoTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();

        // Part 7.4 worked example. B-2312 expires inside the 90-day minimum
        // shelf life and must be excluded; B-2405 has 600 reserved.
        $this->receive('B-2312', now()->addDays(75)->toDateString(), '400', '2.0500');
        $this->receive('B-2401', now()->addDays(195)->toDateString(), '150', '2.0500');
        $b2405 = $this->receive('B-2405', now()->addDays(348)->toDateString(), '1800', '2.1800');
        $this->balance($b2405->id)->update(['qty_reserved' => '600.0000']);
    }

    public function test_fefo_splits_one_box_across_two_batches_when_pack_integrity_is_off(): void
    {
        $sale = $this->checkout([$this->saleLine('BOX', '1', '500.0000')], [['method' => 'CASH', 'amount' => '500.0000']]);

        $allocations = $sale->lines[0]->batchAllocations->sortBy(fn ($a) => $a->batch->expiry_date)->values();

        $this->assertCount(2, $allocations);
        $this->assertSame('B-2401', $allocations[0]->batch->batch_number);
        $this->assertSame('150.0000', (string) $allocations[0]->qty_base);
        $this->assertSame('B-2405', $allocations[1]->batch->batch_number);
        $this->assertSame('50.0000', (string) $allocations[1]->qty_base);

        // Part 24.3 — batch-level COGS: 150 × 2.05 + 50 × 2.18 = 416.50.
        $this->assertSame('416.5000', (string) $sale->lines[0]->line_cost);
        $this->assertSame('2.0825', (string) $sale->lines[0]->unit_cost);
        $this->assertSame('416.5000', (string) $sale->cost_total);
    }

    public function test_pack_integrity_takes_the_whole_box_from_one_batch(): void
    {
        $sale = $this->checkout(
            [$this->saleLine('BOX', '1', '500.0000', ['pack_integrity_override' => true])],
            [['method' => 'CASH', 'amount' => '500.0000']],
        );

        $allocations = $sale->lines[0]->batchAllocations;

        $this->assertCount(1, $allocations);
        $this->assertSame('B-2405', $allocations[0]->batch->batch_number);
        $this->assertSame('200.0000', (string) $allocations[0]->qty_base);
    }

    public function test_every_sale_line_is_fully_identified_by_batch(): void
    {
        $this->checkout([
            $this->saleLine('BOX', '1', '500.0000'),
            $this->saleLine('STR', '7', '28.0000'),
        ], [['method' => 'CASH', 'amount' => '696.0000']]);
        $this->checkout([$this->saleLine('TAB', '33', '2.7500')], [['method' => 'CASH', 'amount' => '90.7500']]);

        foreach (Sale::with('lines.batchAllocations')->get() as $sale) {
            foreach ($sale->lines as $line) {
                $allocated = $line->batchAllocations->reduce(fn ($c, $a) => bcadd($c, (string) $a->qty_base, 4), '0.0000');
                $this->assertSame((string) $line->qty_base, $allocated, "Line {$line->id} has unallocated quantity");
            }
        }

        $this->assertSame(SaleLineBatchAllocation::count(), StockLedger::where('txn_type', 'SALE')->count(), 'One ledger row per allocation');
    }

    public function test_short_dated_and_unreleased_batches_are_never_sold(): void
    {
        $this->receive('B-QC', now()->addDays(400)->toDateString(), '5000', '2.0000', 'TAB', release: false);

        // Sellable stock: B-2401 150 + B-2405 (1800 − 600 reserved) = 1,350.
        // B-2312 (400, short-dated) and B-QC (5,000, PENDING_QC) must not count.
        try {
            $this->checkout([$this->saleLine('TAB', '1351', '2.7500')], [['method' => 'CASH', 'amount' => '3715.2500']]);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame('1350.0000', $e->available);
            $this->assertSame('1.0000', $e->shortfall);
        }

        $this->assertSame(0, Sale::count(), 'A failed allocation must leave no partial sale behind');
        $this->assertSame(0, StockLedger::where('txn_type', 'SALE')->count());
    }

    /**
     * Part 7.4 — allocation is scoped to the selling store. A released batch
     * in the warehouse is not sellable at the counter until it is transferred
     * there, however healthy its QC status looks on the batch screen.
     */
    public function test_released_stock_in_another_store_is_not_sellable_until_it_is_transferred(): void
    {
        $counter = Store::create([
            'branch_id' => $this->branch->id,
            'code' => 'RETAIL',
            'name' => 'Retail Counter',
            'store_type' => 'RETAIL',
            'is_sellable' => true,
        ]);

        try {
            $this->checkout([$this->saleLine('TAB', '100', '2.7500')], [['method' => 'CASH', 'amount' => '275.0000']], ['store_id' => $counter->id]);
            $this->fail('Expected InsufficientStockException: the stock is in MAIN, not at the counter');
        } catch (InsufficientStockException $e) {
            $this->assertSame('0.0000', $e->available);
        }

        $transfers = app(StockTransferService::class);
        $b2401 = ProductBatch::where('batch_number', 'B-2401')->firstOrFail();
        $transfer = $transfers->create([
            'from_store_id' => $this->store->id,
            'to_store_id' => $counter->id,
            'user_id' => $this->user->id,
            'lines' => [['product_id' => $this->amox->id, 'batch_id' => $b2401->id, 'qty_base' => '150.0000']],
        ]);
        // Segregation of duties: the requester cannot approve their own transfer.
        $approver = $this->colleague(['name' => 'Transfer Approver', 'username' => 'trf-approver', 'email' => 'trf-approver@example.test', 'password' => 'password-long-enough']);
        $transfers->approve($transfer, $approver->id);
        $transfers->dispatch($transfer, $this->user->id);
        $transfers->receive($transfer, $this->user->id);

        $sale = $this->checkout([$this->saleLine('TAB', '100', '2.7500')], [['method' => 'CASH', 'amount' => '275.0000']], ['store_id' => $counter->id]);

        $this->assertSame('B-2401', $sale->lines[0]->batchAllocations[0]->batch->batch_number);
        $this->assertSame('100.0000', (string) $sale->lines[0]->batchAllocations[0]->qty_base);
        $this->assertSame('50.0000', (string) StockBalance::where('store_id', $counter->id)->where('product_id', $this->amox->id)->sum('qty_on_hand'));
    }
}
