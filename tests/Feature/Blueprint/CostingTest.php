<?php

namespace Tests\Feature\Blueprint;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\LandedCost;
use App\Models\NumberSequence;
use App\Services\Inventory\StockLedgerService;
use App\Services\Procurement\GoodsReceiptService;
use App\Services\Procurement\LandedCostAllocator;
use Illuminate\Support\Str;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 24.2 weighted average cost and Part 24.9 landed cost — the two cost
 * figures every margin in the system depends on.
 */
class CostingTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_weighted_average_cost_matches_the_worked_example(): void
    {
        // Existing 1,200 @ 2.05, receipt 5,000 @ 2.18 landed → 2.1548.
        $batch = $this->receive('B-2405', now()->addDays(348)->toDateString(), '1200', '2.0500');

        app(StockLedgerService::class)->post([
            'txn_type' => 'GRN_RECEIPT',
            'product_id' => $this->amox->id,
            'batch_id' => $batch->id,
            'store_id' => $this->store->id,
            'qty_base' => '5000.0000',
            'unit_cost' => '2.1800',
            'source_doc_type' => 'goods_receipt',
            'source_doc_id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->assertSame('2.1548', (string) $this->balance($batch->id)->wac);
        $this->assertSame('6200.0000', (string) $this->balance($batch->id)->qty_on_hand);
    }

    public function test_outgoing_stock_does_not_move_the_average_cost(): void
    {
        $batch = $this->receive('B-2405', now()->addDays(348)->toDateString(), '1200', '2.0500');
        $this->checkout([$this->saleLine('BOX', '1', '500.0000')], [['method' => 'CASH', 'amount' => '500.0000']]);

        $this->assertSame('2.0500', (string) $this->balance($batch->id)->wac);
        $this->assertSame('1000.0000', (string) $this->balance($batch->id)->qty_on_hand);
    }

    public function test_landed_cost_is_allocated_by_value_and_becomes_the_batch_cost(): void
    {
        // Part 24.9: freight + clearing 64,000 on an 800,000 shipment → 8% uplift on every unit.
        $receipt = GoodsReceipt::create([
            'doc_number' => NumberSequence::next($this->org->id, 'GRN', $this->branch->id, 'GRN'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'store_id' => $this->store->id,
            'status' => 'DRAFT',
            'is_emergency' => true,
            'received_by' => $this->user->id,
        ]);
        $lineA = GoodsReceiptLine::create($this->line($receipt, 'B-A', '4000', '100.0000')); // 400,000
        $lineB = GoodsReceiptLine::create($this->line($receipt, 'B-B', '2500', '100.0000')); // 250,000
        $lineC = GoodsReceiptLine::create($this->line($receipt, 'B-C', '1500', '100.0000')); // 150,000

        app(GoodsReceiptService::class)->post($receipt);

        $landed = LandedCost::create([
            'goods_receipt_id' => $receipt->id,
            'allocation_basis' => 'VALUE',
            'freight' => '40000.0000',
            'clearing' => '24000.0000',
        ]);
        app(LandedCostAllocator::class)->allocate($landed);

        $this->assertSame('32000.0000', (string) $landed->allocations()->where('goods_receipt_line_id', $lineA->id)->value('allocated_amount'));
        $this->assertSame('20000.0000', (string) $landed->allocations()->where('goods_receipt_line_id', $lineB->id)->value('allocated_amount'));
        $this->assertSame('12000.0000', (string) $landed->allocations()->where('goods_receipt_line_id', $lineC->id)->value('allocated_amount'));

        foreach ([$lineA, $lineB, $lineC] as $line) {
            $this->assertSame('108.0000', (string) $line->fresh()->landed_unit_cost);
            $this->assertSame('108.0000', (string) $line->fresh()->batch->landed_unit_cost, 'Landed cost must flow onto the batch');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function line(GoodsReceipt $receipt, string $batch, string $qty, string $cost): array
    {
        return [
            'goods_receipt_id' => $receipt->id,
            'product_id' => $this->amox->id,
            'uom_id' => $this->uoms['TAB']->id,
            'qty_ordered' => $qty,
            'qty_delivered' => $qty,
            'qty_accepted' => $qty,
            'batch_number' => $batch,
            'expiry_date' => now()->addYear()->toDateString(),
            'unit_cost' => $cost,
        ];
    }
}
