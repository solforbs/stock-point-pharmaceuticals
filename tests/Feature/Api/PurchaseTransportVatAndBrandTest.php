<?php

namespace Tests\Feature\Api;

use App\Models\AccountsPayable;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\TaxCode;
use App\Models\TaxRate;
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

    public function test_the_match_expects_the_order_value_including_transport_and_no_vat_on_zero_rated_goods(): void
    {
        $this->zeroRated();
        // 10 boxes at 1,500 plus 1,000 transport: the order is worth 16,000.
        $po = $this->receivedOrder(transport: '1000', vat: 'ZERO');

        $invoiceLines = [['purchase_order_line_id' => $po['lines'][0]['id'], 'product_id' => $this->amox->id, 'qty' => '10', 'unit_price' => '1500']];

        $this->postJson('/api/supplier-invoices', ['supplier_id' => $this->supplier->id, 'invoice_number' => 'INV-VAT', 'invoice_date' => now()->toDateString(), 'tax_total' => '2400', 'lines' => $invoiceLines])
            ->assertUnprocessable()->assertJsonValidationErrors('tax_total');

        $invoice = $this->postJson('/api/supplier-invoices', ['supplier_id' => $this->supplier->id, 'invoice_number' => 'INV-OK', 'invoice_date' => now()->toDateString(), 'lines' => $invoiceLines])
            ->assertCreated()->assertJsonPath('transport_total', '1000.0000')->assertJsonPath('grand_total', '16000.0000')->json();

        $this->getJson("/api/supplier-invoices/{$invoice['id']}/comparison")->assertOk()
            ->assertJsonPath('would_match', true)
            ->assertJsonPath('totals.vat_allowed', '0.0000')
            ->assertJsonPath('totals.expected_total', '16000.0000')
            ->assertJsonPath('totals.invoiced_total', '16000.0000');

        $this->postJson("/api/supplier-invoices/{$invoice['id']}/match")->assertOk()->assertJsonPath('matched', true);

        // The payable is the order's 16,000, and the transport accrued at receipt is cleared.
        $this->assertSame('16000.0000', AccountsPayable::balanceFor($this->supplier->id));
        $this->assertSame('0.0000', $this->accountBalance('FREIGHT_ACCRUAL'));
        $this->assertSame('0.0000', $this->accountBalance('GRN_ACCRUAL'));
        $this->assertSame('0.0000', $this->accountBalance('VAT_INPUT'));
    }

    public function test_vat_is_limited_to_the_standard_rated_goods(): void
    {
        $this->vat16();
        $po = $this->receivedOrder(transport: '0', vat: 'STANDARD');
        $lines = [['purchase_order_line_id' => $po['lines'][0]['id'], 'product_id' => $this->amox->id, 'qty' => '10', 'unit_price' => '1500']];

        // 16% of 15,000 is 2,400; more than that is refused.
        $this->postJson('/api/supplier-invoices', ['supplier_id' => $this->supplier->id, 'invoice_number' => 'INV-HI', 'invoice_date' => now()->toDateString(), 'tax_total' => '3000', 'lines' => $lines])
            ->assertUnprocessable()->assertJsonValidationErrors('tax_total');
        $this->postJson('/api/supplier-invoices', ['supplier_id' => $this->supplier->id, 'invoice_number' => 'INV-STD', 'invoice_date' => now()->toDateString(), 'tax_total' => '2400', 'lines' => $lines])
            ->assertCreated()->assertJsonPath('grand_total', '17400.0000');
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

    private function zeroRated(): TaxCode
    {
        $this->vat16();
        $zero = TaxCode::create(['organisation_id' => $this->org->id, 'code' => 'VAT_ZERO', 'name' => 'VAT zero-rated', 'tax_type' => 'VAT']);
        TaxRate::create(['tax_code_id' => $zero->id, 'rate_pct' => '0.000', 'effective_from' => now()->subYear()->toDateString()]);

        return $zero;
    }
}
