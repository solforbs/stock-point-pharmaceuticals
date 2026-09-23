<?php

namespace Tests\Feature\Api;

use App\Models\AccountsPayable;
use App\Models\GoodsReceiptLine;
use App\Models\ProductBatch;
use App\Models\StockLedger;
use App\Models\TaxCode;
use App\Models\TaxRate;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 21.9 over HTTP: PO → GRN → three-way match → supplier payment, with
 * the Part 9 guards (licensed supplier, PO-backed receipt, batch + expiry).
 */
class ProcurementHttpFlowTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions([
            'po.create', 'po.approve', 'grn.create', 'invoice.match', 'payment.record',
            'supplier.view', 'quality.release', 'stock.view', 'product.cost.view',
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_a_suspended_or_unlicensed_supplier_cannot_receive_a_purchase_order(): void
    {
        $this->supplier->update(['status' => 'SUSPENDED']);
        $this->postJson('/api/purchase-orders', $this->poPayload())->assertStatus(422)->assertJsonPath('error.code', 'SUPPLIER_BLOCKED');

        $this->supplier->update(['status' => 'ACTIVE', 'licence_expiry' => now()->subDay()->toDateString()]);
        $this->postJson('/api/purchase-orders', $this->poPayload())->assertStatus(422)->assertJsonPath('error.code', 'SUPPLIER_LICENCE_EXPIRED');
    }

    public function test_the_full_procure_to_pay_chain(): void
    {
        // 1. Purchase order: DRAFT → APPROVED → SENT.
        $po = $this->postJson('/api/purchase-orders', $this->poPayload())
            ->assertCreated()->assertJsonPath('status', 'DRAFT')->assertJsonCount(1, 'lines')->json();

        $this->postJson("/api/purchase-orders/{$po['id']}/send")->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');
        $this->postJson("/api/purchase-orders/{$po['id']}/approve")->assertOk()->assertJsonPath('status', 'APPROVED');
        $this->postJson("/api/purchase-orders/{$po['id']}/send")->assertOk()->assertJsonPath('status', 'SENT');

        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->assertOk()->assertJsonCount(0, 'data');

        // 2. Goods receipt: PO-backed, batch + expiry mandatory, cost in the purchase UOM.
        $grnPayload = [
            'purchase_order_id' => $po['id'],
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'lines' => [[
                'purchase_order_line_id' => $po['lines'][0]['id'],
                'product_id' => $this->amox->id,
                'uom_id' => $this->uoms['BOX']->id,
                'qty_delivered' => '10', 'qty_accepted' => '10',
                'batch_number' => 'AMX-2026-01', 'expiry_date' => now()->addYears(2)->toDateString(),
                'unit_cost' => '420',
            ]],
        ];

        $noPo = $grnPayload;
        unset($noPo['purchase_order_id'], $noPo['lines'][0]['purchase_order_line_id']);
        $this->postJson('/api/goods-receipts', $noPo)->assertStatus(422)->assertJsonPath('error.code', 'PO_REQUIRED');

        $pastExpiry = $grnPayload;
        $pastExpiry['lines'][0]['expiry_date'] = now()->subDay()->toDateString();
        $this->postJson('/api/goods-receipts', $pastExpiry)->assertStatus(422)->assertJsonPath('error.code', 'BATCH_EXPIRED');

        $grn = $this->postJson('/api/goods-receipts', $grnPayload)
            ->assertCreated()
            ->assertJsonPath('status', 'POSTED')
            ->assertJsonPath('lines.0.batch.status', 'PENDING_QC')
            ->assertJsonPath('lines.0.batch.landed_unit_cost', '2.1000')
            ->json();

        $batch = ProductBatch::findOrFail($grn['lines'][0]['batch']['id']);
        $this->assertSame('2000.0000', (string) StockLedger::where('batch_id', $batch->id)->sum('qty_base'), 'ten boxes are 2,000 base units in the ledger');

        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)
            ->assertOk()
            ->assertJsonPath('data.0.on_hand', '2000.0000')
            ->assertJsonPath('data.0.pending_qc', '2000.0000')
            ->assertJsonPath('data.0.free_to_sell', '0.0000');

        // 3. QC release makes it sellable.
        $this->postJson("/api/batches/{$batch->id}/release")->assertOk()->assertJsonPath('status', 'RELEASED');
        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->assertJsonPath('data.0.free_to_sell', '2000.0000');

        // 4. Supplier invoice at the PO price matches and creates the payable.
        $invoice = $this->postJson('/api/supplier-invoices', [
            'supplier_id' => $this->supplier->id, 'invoice_number' => 'INV-778', 'invoice_date' => now()->toDateString(),
            'lines' => [['purchase_order_line_id' => $po['lines'][0]['id'], 'product_id' => $this->amox->id, 'qty' => '10', 'unit_price' => '420']],
        ])->assertCreated()->assertJsonPath('grand_total', '4200.0000')->json();

        $this->postJson("/api/supplier-invoices/{$invoice['id']}/match")
            ->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonPath('invoice.match_status', 'MATCHED');
        $this->assertSame('4200.0000', AccountsPayable::balanceFor($this->supplier->id));

        $this->getJson('/api/suppliers')->assertOk()->assertJsonPath('data.0.payable_balance', '4200.0000');

        // 5. Paying more than is owed is refused; paying what is owed clears it.
        $this->postJson('/api/supplier-payments', ['supplier_id' => $this->supplier->id, 'method' => 'BANK', 'amount' => '5000'])
            ->assertStatus(422)->assertJsonPath('error.code', 'OVERPAYMENT');

        $this->postJson('/api/supplier-payments', ['supplier_id' => $this->supplier->id, 'method' => 'BANK', 'reference' => 'RTGS-1', 'amount' => '4200'])
            ->assertCreated()
            ->assertJsonPath('payable_balance', '0.0000');
    }

    /**
     * Part 13 — the receiving bay reads the VAT treatment off the supplier's
     * invoice: charged at 16%, or not charged at all. The answer is kept on
     * the receipt line and becomes the product's treatment, because that is
     * what the till will charge the customer.
     */
    public function test_the_vat_treatment_on_the_suppliers_invoice_is_captured_as_goods_are_received(): void
    {
        $standard = $this->vat16();
        $zero = TaxCode::create(['organisation_id' => $this->org->id, 'code' => 'VAT_ZERO', 'name' => 'VAT zero-rated', 'tax_type' => 'VAT']);
        TaxRate::create(['tax_code_id' => $zero->id, 'rate_pct' => '0.000', 'effective_from' => now()->subYear()->toDateString()]);

        $this->assertSame($standard->id, $this->amox->fresh()->tax_code_id, 'the product starts out standard-rated');

        $po = $this->postJson('/api/purchase-orders', $this->poPayload())->assertCreated()->json();
        $this->postJson("/api/purchase-orders/{$po['id']}/approve")->assertOk();

        $grn = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po['id'], 'supplier_id' => $this->supplier->id, 'store_id' => $this->store->id,
            'lines' => [[
                'purchase_order_line_id' => $po['lines'][0]['id'], 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id,
                'qty_delivered' => '10', 'qty_accepted' => '10', 'batch_number' => 'AMX-VAT-01',
                'expiry_date' => now()->addYears(2)->toDateString(), 'unit_cost' => '420', 'vat' => 'ZERO',
            ]],
        ])->assertCreated()->json();

        $this->assertSame($zero->id, GoodsReceiptLine::findOrFail($grn['lines'][0]['id'])->tax_code_id, 'the receipt line records what the invoice said');
        $this->assertSame($zero->id, $this->amox->fresh()->tax_code_id, 'and the product now sells zero-rated');
        $this->assertDatabaseHas('audit_logs', ['action' => 'PRODUCT_TAX_CODE_SET_ON_RECEIPT', 'entity_id' => $this->amox->id]);
    }

    public function test_a_receipt_that_says_nothing_about_vat_leaves_the_product_alone(): void
    {
        $standard = $this->vat16();
        $po = $this->postJson('/api/purchase-orders', $this->poPayload())->assertCreated()->json();
        $this->postJson("/api/purchase-orders/{$po['id']}/approve")->assertOk();

        $grn = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po['id'], 'supplier_id' => $this->supplier->id, 'store_id' => $this->store->id,
            'lines' => [[
                'purchase_order_line_id' => $po['lines'][0]['id'], 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id,
                'qty_delivered' => '10', 'qty_accepted' => '10', 'batch_number' => 'AMX-VAT-02',
                'expiry_date' => now()->addYears(2)->toDateString(), 'unit_cost' => '420',
            ]],
        ])->assertCreated()->json();

        $this->assertNull(GoodsReceiptLine::findOrFail($grn['lines'][0]['id'])->tax_code_id);
        $this->assertSame($standard->id, $this->amox->fresh()->tax_code_id);
    }

    public function test_an_invoice_priced_beyond_tolerance_goes_to_the_exception_queue(): void
    {
        $po = $this->postJson('/api/purchase-orders', $this->poPayload())->assertCreated()->json();
        $this->postJson("/api/purchase-orders/{$po['id']}/approve")->assertOk();

        $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po['id'], 'supplier_id' => $this->supplier->id, 'store_id' => $this->store->id,
            'lines' => [[
                'purchase_order_line_id' => $po['lines'][0]['id'], 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id,
                'qty_delivered' => '10', 'qty_accepted' => '10', 'batch_number' => 'AMX-2026-02',
                'expiry_date' => now()->addYears(2)->toDateString(), 'unit_cost' => '420',
            ]],
        ])->assertCreated();

        // 480 vs 420 is 14% and KES 600 on the line: beyond both tolerances.
        $invoice = $this->postJson('/api/supplier-invoices', [
            'supplier_id' => $this->supplier->id, 'invoice_number' => 'INV-779', 'invoice_date' => now()->toDateString(),
            'lines' => [['purchase_order_line_id' => $po['lines'][0]['id'], 'product_id' => $this->amox->id, 'qty' => '10', 'unit_price' => '480']],
        ])->assertCreated()->json();

        // The comparison shows why before anyone runs the match.
        $this->getJson("/api/supplier-invoices/{$invoice['id']}/comparison")
            ->assertOk()
            ->assertJsonPath('would_match', false)
            ->assertJsonPath('lines.0.qty_ordered', '10.0000')
            ->assertJsonPath('lines.0.qty_accepted', '10.0000')
            ->assertJsonPath('lines.0.checks.quantity', true)
            ->assertJsonPath('lines.0.checks.price', false)
            ->assertJsonPath('lines.0.price_variance_pct', '14.28')
            ->assertJsonPath('lines.0.po_number', $po['doc_number']);

        $this->postJson("/api/supplier-invoices/{$invoice['id']}/match")
            ->assertOk()
            ->assertJsonPath('matched', false)
            ->assertJsonPath('invoice.match_status', 'EXCEPTION');
        $this->assertSame('0.0000', AccountsPayable::balanceFor($this->supplier->id), 'no payable until the exception is resolved');
    }

    public function test_procurement_endpoints_need_their_permissions(): void
    {
        $this->grantPermissions(['stock.view']);

        $this->postJson('/api/purchase-orders', $this->poPayload())->assertStatus(403);
        $this->postJson('/api/supplier-payments', ['supplier_id' => $this->supplier->id, 'method' => 'BANK', 'amount' => '1'])->assertStatus(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function poPayload(): array
    {
        return [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'qty_ordered' => '10', 'unit_price' => '420']],
        ];
    }
}
