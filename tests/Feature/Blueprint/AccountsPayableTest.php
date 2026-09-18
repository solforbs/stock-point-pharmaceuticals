<?php

namespace Tests\Feature\Blueprint;

use App\Models\AccountsPayable;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\JournalEntry;
use App\Models\NumberSequence;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceLine;
use App\Services\Procurement\GoodsReceiptService;
use App\Services\Procurement\SupplierOverpaymentException;
use App\Services\Procurement\SupplierPaymentService;
use App\Services\Procurement\ThreeWayMatchService;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 9.4 / 12.3 — "Only a matched invoice creates an accounts_payable
 * entry": Dr GRN accrual / Cr AP on match, Dr AP / Cr Bank on payment, and
 * the GRN accrual nets to zero once invoice and receipt agree.
 */
class AccountsPayableTest extends TestCase
{
    use BuildsBlueprintWorld;

    private SupplierInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();

        $po = PurchaseOrder::create([
            'doc_number' => NumberSequence::next($this->org->id, 'PO', $this->branch->id, 'PO'),
            'supplier_id' => $this->supplier->id, 'branch_id' => $this->branch->id, 'status' => 'SENT', 'created_by' => $this->user->id,
        ]);
        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id, 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id,
            'qty_ordered' => '60', 'unit_price' => '420.0000',
        ]);

        $receipt = GoodsReceipt::create([
            'doc_number' => NumberSequence::next($this->org->id, 'GRN', $this->branch->id, 'GRN'),
            'purchase_order_id' => $po->id, 'supplier_id' => $this->supplier->id, 'branch_id' => $this->branch->id,
            'store_id' => $this->store->id, 'status' => 'DRAFT', 'received_by' => $this->user->id,
        ]);
        GoodsReceiptLine::create([
            'goods_receipt_id' => $receipt->id, 'purchase_order_line_id' => $poLine->id, 'product_id' => $this->amox->id,
            'uom_id' => $this->uoms['BOX']->id, 'qty_ordered' => '60', 'qty_delivered' => '60', 'qty_accepted' => '60',
            'batch_number' => 'B-2609', 'expiry_date' => now()->addMonths(20)->toDateString(), 'unit_cost' => '420.0000',
        ]);
        app(GoodsReceiptService::class)->post($receipt);

        $this->invoice = SupplierInvoice::create([
            'doc_number' => 'SI-0001', 'supplier_id' => $this->supplier->id, 'branch_id' => $this->branch->id,
            'invoice_number' => 'PDL-8812', 'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => '25200.0000', 'tax_total' => '4032.0000', 'grand_total' => '29232.0000',
        ]);
        SupplierInvoiceLine::create([
            'supplier_invoice_id' => $this->invoice->id, 'purchase_order_line_id' => $poLine->id,
            'product_id' => $this->amox->id, 'qty' => '60', 'unit_price' => '420.0000', 'line_total' => '25200.0000',
        ]);
    }

    public function test_a_matched_invoice_creates_the_payable_and_clears_the_grn_accrual(): void
    {
        $this->assertSame('-25200.0000', $this->accountBalance('GRN_ACCRUAL'), 'Receipt accrued 60 × 420');

        $result = app(ThreeWayMatchService::class)->match($this->invoice->fresh(['lines']), $this->user->id);

        $this->assertTrue($result->matched);
        $this->assertSame('29232.0000', AccountsPayable::balanceFor($this->supplier->id));
        $this->assertSame('0.0000', $this->accountBalance('GRN_ACCRUAL'), 'Invoice and receipt agree, so the accrual nets to zero');
        $this->assertSame('4032.0000', $this->accountBalance('VAT_INPUT'));
        $this->assertSame('-29232.0000', $this->accountBalance('AP_CONTROL'));
        $this->assertTrue(JournalEntry::where('source_doc_type', 'supplier_invoice')->firstOrFail()->isBalanced());
    }

    public function test_matching_twice_does_not_post_twice(): void
    {
        app(ThreeWayMatchService::class)->match($this->invoice->fresh(['lines']), $this->user->id);
        app(ThreeWayMatchService::class)->match($this->invoice->fresh(['lines']), $this->user->id);

        $this->assertSame(1, AccountsPayable::where('supplier_invoice_id', $this->invoice->id)->count());
        $this->assertSame(1, JournalEntry::where('source_doc_type', 'supplier_invoice')->count());
    }

    public function test_a_supplier_payment_reduces_the_payable_and_credits_the_bank(): void
    {
        app(ThreeWayMatchService::class)->match($this->invoice->fresh(['lines']), $this->user->id);

        app(SupplierPaymentService::class)->pay([
            'organisation_id' => $this->org->id, 'branch_id' => $this->branch->id, 'supplier_id' => $this->supplier->id,
            'method' => 'BANK', 'reference' => 'RTGS-77812', 'amount' => '20000.0000', 'paid_by' => $this->user->id,
        ]);

        $this->assertSame('9232.0000', AccountsPayable::balanceFor($this->supplier->id));
        $this->assertSame('-9232.0000', $this->accountBalance('AP_CONTROL'));
        $this->assertSame('-20000.0000', $this->accountBalance('BANK'));
    }

    public function test_a_supplier_cannot_be_paid_more_than_is_owed(): void
    {
        app(ThreeWayMatchService::class)->match($this->invoice->fresh(['lines']), $this->user->id);

        $this->expectException(SupplierOverpaymentException::class);
        app(SupplierPaymentService::class)->pay([
            'organisation_id' => $this->org->id, 'branch_id' => $this->branch->id, 'supplier_id' => $this->supplier->id,
            'method' => 'BANK', 'amount' => '30000.0000', 'paid_by' => $this->user->id,
        ]);
    }

    public function test_an_unmatched_invoice_creates_no_payable(): void
    {
        SupplierInvoiceLine::query()->update(['unit_price' => '440.0000', 'line_total' => '26400.0000']);

        $result = app(ThreeWayMatchService::class)->match($this->invoice->fresh(['lines']), $this->user->id);

        $this->assertFalse($result->matched);
        $this->assertSame('0.0000', AccountsPayable::balanceFor($this->supplier->id));
        $this->assertSame(0, JournalEntry::where('source_doc_type', 'supplier_invoice')->count());
    }
}
