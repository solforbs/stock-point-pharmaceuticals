<?php

namespace Tests\Feature\Blueprint;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\NumberSequence;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockLedger;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceLine;
use App\Services\Procurement\GoodsReceiptService;
use App\Services\Procurement\ThreeWayMatchService;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Correction #3 (GRN with lines that create batches and post stock) and
 * Part 9.4 three-way match with the Part 9.3 tolerances.
 */
class GoodsReceiptAndMatchTest extends TestCase
{
    use BuildsBlueprintWorld;

    private PurchaseOrder $po;

    private PurchaseOrderLine $poLine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();

        $this->po = PurchaseOrder::create([
            'doc_number' => NumberSequence::next($this->org->id, 'PO', $this->branch->id, 'PO'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'status' => 'SENT',
            'created_by' => $this->user->id,
        ]);
        $this->poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $this->po->id,
            'product_id' => $this->amox->id,
            'uom_id' => $this->uoms['BOX']->id,
            'qty_ordered' => '100',
            'unit_price' => '420.0000',
        ]);
    }

    public function test_only_accepted_quantity_creates_a_batch_and_stock(): void
    {
        // Trace 6: 60 delivered, 58 accepted, 2 rejected, in boxes of 200.
        $receipt = $this->receiptFor($this->po);
        $line = GoodsReceiptLine::create([
            'goods_receipt_id' => $receipt->id,
            'purchase_order_line_id' => $this->poLine->id,
            'product_id' => $this->amox->id,
            'uom_id' => $this->uoms['BOX']->id,
            'qty_ordered' => '100',
            'qty_delivered' => '60',
            'qty_accepted' => '58',
            'qty_rejected' => '2',
            'rejection_reason' => 'damaged',
            'batch_number' => 'B-2609',
            'expiry_date' => now()->addMonths(20)->toDateString(),
            'unit_cost' => '420.0000',
        ]);

        app(GoodsReceiptService::class)->post($receipt);

        $batch = ProductBatch::where('batch_number', 'B-2609')->firstOrFail();
        $this->assertSame('PENDING_QC', $batch->status, 'A new batch is not sellable until QC releases it');
        $this->assertSame($line->id, $batch->grn_line_id, 'The batch is created from the GRN line');
        $this->assertSame('2.1000', (string) $batch->unit_cost, 'Batch cost is per base unit (420 / 200)');

        $ledger = StockLedger::where('txn_type', 'GRN_RECEIPT')->where('batch_id', $batch->id)->firstOrFail();
        $this->assertSame('11600.0000', (string) $ledger->qty_base, '58 boxes × 200 tablets; rejected boxes never touch inventory');
        $this->assertSame('2.1000', (string) $ledger->unit_cost);
        $this->assertSame('11600.0000', (string) $this->balance($batch->id)->qty_on_hand);

        $this->assertSame('42.0000', $this->poLine->qtyOutstanding());
        $this->assertSame('PARTIALLY_RECEIVED', $this->po->fresh()->status);
        $this->assertSame('POSTED', $receipt->fresh()->status);
    }

    public function test_an_invoice_within_tolerance_matches(): void
    {
        $this->receiveAgainstPo('58');

        // 1.8% above PO price — inside the 2% tolerance.
        $result = app(ThreeWayMatchService::class)->match($this->invoice('58', '427.5600'));

        $this->assertTrue($result->matched, implode(' | ', $result->failures));
        $this->assertSame('MATCHED', SupplierInvoice::first()->match_status);
    }

    public function test_a_price_variance_beyond_tolerance_blocks_the_match(): void
    {
        $this->receiveAgainstPo('58');

        // Trace 10: KES 432 vs PO 420 = 2.86%, KES 696 in total — exception queue.
        $result = app(ThreeWayMatchService::class)->match($this->invoice('58', '432.0000'));

        $this->assertFalse($result->matched);
        $this->assertStringContainsString('price variance', implode(' | ', $result->failures));
        $this->assertSame('EXCEPTION', SupplierInvoice::first()->match_status);
    }

    public function test_a_small_absolute_variance_does_not_block_on_percentage_alone(): void
    {
        // A 3% variance worth KES 4 in total is exactly what the absolute floor exists for.
        $cheapLine = PurchaseOrderLine::create([
            'purchase_order_id' => $this->po->id,
            'product_id' => $this->amox->id,
            'uom_id' => $this->uoms['STR']->id,
            'qty_ordered' => '20',
            'unit_price' => '6.5000',
        ]);
        $receipt = $this->receiptFor($this->po);
        GoodsReceiptLine::create([
            'goods_receipt_id' => $receipt->id,
            'purchase_order_line_id' => $cheapLine->id,
            'product_id' => $this->amox->id,
            'uom_id' => $this->uoms['STR']->id,
            'qty_ordered' => '20', 'qty_delivered' => '20', 'qty_accepted' => '20',
            'batch_number' => 'B-STR', 'expiry_date' => now()->addYear()->toDateString(),
            'unit_cost' => '6.5000',
        ]);
        app(GoodsReceiptService::class)->post($receipt);

        $invoice = SupplierInvoice::create([
            'doc_number' => 'SI-CHEAP', 'supplier_id' => $this->supplier->id, 'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-CHEAP', 'invoice_date' => now()->toDateString(),
        ]);
        SupplierInvoiceLine::create([
            'supplier_invoice_id' => $invoice->id, 'purchase_order_line_id' => $cheapLine->id,
            'product_id' => $this->amox->id, 'qty' => '20', 'unit_price' => '6.7000', 'line_total' => '134.0000',
        ]);

        $this->assertTrue(app(ThreeWayMatchService::class)->match($invoice)->matched);
    }

    public function test_over_invoicing_blocks_the_match(): void
    {
        $this->receiveAgainstPo('58');

        $result = app(ThreeWayMatchService::class)->match($this->invoice('60', '420.0000'));

        $this->assertFalse($result->matched);
        $this->assertStringContainsString('over-invoicing', implode(' | ', $result->failures));
    }

    private function receiptFor(PurchaseOrder $po): GoodsReceipt
    {
        return GoodsReceipt::create([
            'doc_number' => NumberSequence::next($this->org->id, 'GRN', $this->branch->id, 'GRN'),
            'purchase_order_id' => $po->id,
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'store_id' => $this->store->id,
            'status' => 'DRAFT',
            'received_by' => $this->user->id,
        ]);
    }

    private function receiveAgainstPo(string $accepted): void
    {
        $receipt = $this->receiptFor($this->po);
        GoodsReceiptLine::create([
            'goods_receipt_id' => $receipt->id,
            'purchase_order_line_id' => $this->poLine->id,
            'product_id' => $this->amox->id,
            'uom_id' => $this->uoms['BOX']->id,
            'qty_ordered' => '100', 'qty_delivered' => $accepted, 'qty_accepted' => $accepted,
            'batch_number' => 'B-2609', 'expiry_date' => now()->addMonths(20)->toDateString(),
            'unit_cost' => '420.0000',
        ]);
        app(GoodsReceiptService::class)->post($receipt);
    }

    private function invoice(string $qty, string $unitPrice): SupplierInvoice
    {
        $invoice = SupplierInvoice::create([
            'doc_number' => 'SI-0001', 'supplier_id' => $this->supplier->id, 'branch_id' => $this->branch->id,
            'invoice_number' => 'PDL-8812', 'invoice_date' => now()->toDateString(),
        ]);
        SupplierInvoiceLine::create([
            'supplier_invoice_id' => $invoice->id, 'purchase_order_line_id' => $this->poLine->id,
            'product_id' => $this->amox->id, 'qty' => $qty, 'unit_price' => $unitPrice,
            'line_total' => bcmul($qty, $unitPrice, 4),
        ]);

        return $invoice->fresh(['lines']);
    }
}
