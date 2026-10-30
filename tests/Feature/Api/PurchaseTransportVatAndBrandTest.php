<?php

namespace Tests\Feature\Api;

use App\Models\AccountsPayable;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Client feedback, 2026-10-27: the purchase order carries its transport
 * however it is raised; the three-way match expects exactly the order's
 * value including that transport, with no VAT on zero-rated goods; and a
 * generic is received batch by batch under the brand each came as.
 */
class PurchaseTransportVatAndBrandTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['po.create', 'po.approve', 'grn.create', 'invoice.match', 'requisition.view', 'requisition.create', 'requisition.approve', 'stock.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_a_single_sourced_order_keeps_the_transport_typed_with_it(): void
    {
        $requisition = Requisition::create(['doc_number' => 'REQ-T-1', 'branch_id' => $this->branch->id, 'status' => 'APPROVED', 'requested_by' => $this->user->id]);
        $line = RequisitionLine::create(['requisition_id' => $requisition->id, 'product_id' => $this->amox->id, 'qty_requested' => '2000']);

        $po = $this->postJson("/api/requisitions/{$requisition->id}/convert", [
            'supplier_id' => $this->supplier->id, 'single_source_reason' => 'DIRECT_DELIVERY', 'justification' => 'Supplier delivers to the door.',
            'transport_cost' => '1000',
            'lines' => [['requisition_line_id' => $line->id, 'uom_id' => $this->uoms['BOX']->id, 'qty_ordered' => '10', 'unit_price' => '1500']],
        ])->assertCreated()->json();

        $this->assertSame('1000.0000', (string) PurchaseOrder::findOrFail($po['id'])->transport_cost);
    }

    public function test_the_match_expects_exactly_the_goods_plus_the_orders_transport_and_adds_no_tax(): void
    {
        $this->vat16();
        // 10 boxes at 1,500 plus 1,000 transport: the order is worth 16,000.
        $po = $this->receivedOrder(transport: '1000', vat: 'STANDARD');
        $invoiceLines = [['purchase_order_line_id' => $po['lines'][0]['id'], 'product_id' => $this->amox->id, 'qty' => '10', 'unit_price' => '1500']];

        // A tax figure sent with the invoice is not added.
        $invoice = $this->postJson('/api/supplier-invoices', ['supplier_id' => $this->supplier->id, 'invoice_number' => 'INV-OK', 'invoice_date' => now()->toDateString(), 'tax_total' => '8423', 'lines' => $invoiceLines])
            ->assertCreated()->assertJsonPath('tax_total', '0.0000')->assertJsonPath('transport_total', '1000.0000')->assertJsonPath('grand_total', '16000.0000')->json();

        $this->getJson("/api/supplier-invoices/{$invoice['id']}/comparison")->assertOk()
            ->assertJsonPath('would_match', true)
            ->assertJsonPath('totals.expected_total', '16000.0000')
            ->assertJsonPath('totals.invoiced_total', '16000.0000');

        $this->postJson("/api/supplier-invoices/{$invoice['id']}/match")->assertOk()->assertJsonPath('matched', true);

        // The payable is the order's 16,000; the accruals clear and no input VAT is booked.
        $this->assertSame('16000.0000', AccountsPayable::balanceFor($this->supplier->id));
        $this->assertSame('0.0000', $this->accountBalance('FREIGHT_ACCRUAL'));
        $this->assertSame('0.0000', $this->accountBalance('GRN_ACCRUAL'));
        $this->assertSame('0.0000', $this->accountBalance('VAT_INPUT'));
    }

    public function test_an_invoice_above_the_orders_value_does_not_match(): void
    {
        $this->vat16();
        $po = $this->receivedOrder(transport: '0', vat: 'STANDARD');
        // 1,520 against 1,500 is inside the per-line tolerance, but the invoice still comes to more than the order.
        $invoice = $this->postJson('/api/supplier-invoices', ['supplier_id' => $this->supplier->id, 'invoice_number' => 'INV-HI', 'invoice_date' => now()->toDateString(),
            'lines' => [['purchase_order_line_id' => $po['lines'][0]['id'], 'product_id' => $this->amox->id, 'qty' => '10', 'unit_price' => '1520']]])->assertCreated()->json();

        $this->getJson("/api/supplier-invoices/{$invoice['id']}/comparison")->assertOk()->assertJsonPath('would_match', false)->assertJsonPath('totals.checks.total', false);
        $this->postJson("/api/supplier-invoices/{$invoice['id']}/match")->assertOk()->assertJsonPath('matched', false);
        $this->assertSame('0.0000', AccountsPayable::balanceFor($this->supplier->id));
    }

    public function test_a_matched_invoice_can_be_corrected_and_its_payable_follows(): void
    {
        $this->vat16();
        $this->grantPermissions(['po.create', 'po.approve', 'grn.create', 'invoice.match', 'record.delete']);
        $po = $this->receivedOrder(transport: '0', vat: 'STANDARD');
        $line = fn (string $qty) => [['purchase_order_line_id' => $po['lines'][0]['id'], 'product_id' => $this->amox->id, 'qty' => $qty, 'unit_price' => '1500']];
        $invoice = $this->postJson('/api/supplier-invoices', ['supplier_id' => $this->supplier->id, 'invoice_number' => 'INV-1', 'invoice_date' => now()->toDateString(), 'lines' => $line('10')])->json();
        $this->postJson("/api/supplier-invoices/{$invoice['id']}/match")->assertJsonPath('matched', true);

        // Corrected to 8 boxes: the 15,000 payable is reversed and the invoice waits to be matched again.
        $this->putJson("/api/supplier-invoices/{$invoice['id']}", ['supplier_id' => $this->supplier->id, 'invoice_number' => 'INV-1A', 'invoice_date' => now()->toDateString(), 'lines' => $line('8')])
            ->assertOk()->assertJsonPath('invoice_number', 'INV-1A')->assertJsonPath('grand_total', '12000.0000')->assertJsonPath('match_status', 'UNMATCHED');
        $this->assertSame('0.0000', AccountsPayable::balanceFor($this->supplier->id));
        $this->assertSame('-15000.0000', $this->accountBalance('GRN_ACCRUAL'), 'the receipt is owed again until the corrected invoice is matched');

        $this->postJson("/api/supplier-invoices/{$invoice['id']}/match")->assertJsonPath('matched', true);
        $this->assertSame('12000.0000', AccountsPayable::balanceFor($this->supplier->id));
    }

    public function test_an_invoice_can_be_deleted_unless_it_has_already_been_paid(): void
    {
        $this->vat16();
        $this->grantPermissions(['po.create', 'po.approve', 'grn.create', 'invoice.match', 'record.delete', 'payment.record']);
        $po = $this->receivedOrder(transport: '0', vat: 'STANDARD');
        $lines = [['purchase_order_line_id' => $po['lines'][0]['id'], 'product_id' => $this->amox->id, 'qty' => '10', 'unit_price' => '1500']];
        $make = fn (string $number) => $this->postJson('/api/supplier-invoices', ['supplier_id' => $this->supplier->id, 'invoice_number' => $number, 'invoice_date' => now()->toDateString(), 'lines' => $lines])->json('id');

        $unmatched = $make('INV-X');
        $this->deleteJson("/api/supplier-invoices/{$unmatched}", ['reason' => 'Keyed twice'])->assertOk();
        $this->assertDatabaseMissing('supplier_invoices', ['id' => $unmatched]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'SUPPLIER_INVOICE_DELETED', 'entity_id' => $unmatched]);

        $matched = $make('INV-Y');
        $this->postJson("/api/supplier-invoices/{$matched}/match")->assertJsonPath('matched', true);
        $this->postJson('/api/supplier-payments', ['supplier_id' => $this->supplier->id, 'method' => 'BANK', 'reference' => 'RTGS-9', 'amount' => '15000'])->assertCreated();

        $this->deleteJson("/api/supplier-invoices/{$matched}", ['reason' => 'Wrong supplier'])->assertStatus(409)->assertJsonPath('error.code', 'INVOICE_PAID');
        $this->assertDatabaseHas('supplier_invoices', ['id' => $matched, 'match_status' => 'MATCHED']);
    }

    public function test_each_batch_of_a_generic_keeps_the_brand_it_was_received_as(): void
    {
        $po = $this->postJson('/api/purchase-orders', ['supplier_id' => $this->supplier->id, 'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'qty_ordered' => '10', 'unit_price' => '420']]])->assertCreated()->json();
        $this->postJson("/api/purchase-orders/{$po['id']}/approve")->assertOk();

        $batch = fn (string $number, string $brand, string $qty) => [
            'purchase_order_line_id' => $po['lines'][0]['id'], 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id,
            'qty_delivered' => $qty, 'qty_accepted' => $qty, 'batch_number' => $number, 'brand_name' => $brand,
            'expiry_date' => now()->addYears(2)->toDateString(), 'unit_cost' => '420',
        ];
        $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po['id'], 'supplier_id' => $this->supplier->id, 'store_id' => $this->store->id,
            'lines' => [$batch('AMX-A', 'Amoxil', '6'), $batch('AMX-B', 'Moxacil', '4')],
        ])->assertCreated()->assertJsonPath('lines.0.brand_name', 'Amoxil');

        $this->assertSame(['AMX-A' => 'Amoxil', 'AMX-B' => 'Moxacil'], ProductBatch::where('product_id', $this->amox->id)->orderBy('batch_number')->pluck('brand_name', 'batch_number')->all());
        $brands = collect($this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->assertOk()->json('data.0.batches'))->pluck('brand_name')->sort()->values()->all();
        $this->assertSame(['Amoxil', 'Moxacil'], $brands);
    }

    /**
     * @return array<string, mixed>
     */
    private function receivedOrder(string $transport, string $vat): array
    {
        $po = $this->postJson('/api/purchase-orders', [
            'supplier_id' => $this->supplier->id, 'transport_cost' => $transport,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'qty_ordered' => '10', 'unit_price' => '1500']],
        ])->assertCreated()->json();
        $this->postJson("/api/purchase-orders/{$po['id']}/approve")->assertOk();
        $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po['id'], 'supplier_id' => $this->supplier->id, 'store_id' => $this->store->id,
            'lines' => [[
                'purchase_order_line_id' => $po['lines'][0]['id'], 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id,
                'qty_delivered' => '10', 'qty_accepted' => '10', 'batch_number' => 'AMX-'.$vat,
                'expiry_date' => now()->addYears(2)->toDateString(), 'unit_cost' => '1500', 'vat' => $vat,
            ]],
        ])->assertCreated();

        return $po;
    }
}
