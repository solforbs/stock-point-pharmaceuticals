<?php

namespace Tests\Feature\Api;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * The order book kept in Excel: rows grouped by supplier_code become one
 * draft purchase order each, checked with per-row errors before anything
 * is written, and the normal approve → send flow still stands after.
 */
class PurchaseOrderImportTest extends TestCase
{
    use BuildsBlueprintWorld;

    private Supplier $kemsa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['po.create', 'po.approve', 'supplier.view']);
        Sanctum::actingAs($this->user);

        $this->kemsa = Supplier::create([
            'organisation_id' => $this->org->id,
            'code' => 'KEMSA', 'name' => 'Kenya Medical Supplies Authority', 'status' => 'ACTIVE',
        ]);
    }

    public function test_rows_grouped_by_supplier_become_one_draft_po_each(): void
    {
        // Lower-case codes, a blank uom (the purchase pack fills in), and
        // trade terms on the second supplier's line: 500 less 10% = 450.
        $rows = [
            ['supplier_code' => 'pdl', 'product_code' => 'amox500', 'qty' => '10', 'unit_price' => '420', 'uom_code' => 'BOX', 'expected_date' => '2026-10-01'],
            ['supplier_code' => 'PDL', 'product_code' => 'AMOX500', 'qty' => '2', 'unit_price' => '4000', 'uom_code' => 'CTN'],
            ['supplier_code' => 'KEMSA', 'product_code' => 'AMOX500', 'qty' => '5', 'trade_price' => '500', 'discount_pct' => '10'],
        ];

        // The dry run prices and groups but writes nothing.
        $this->postJson('/api/purchase-orders/import', ['rows' => $rows, 'dry_run' => true])
            ->assertOk()
            ->assertJsonPath('dry_run', true)
            ->assertJsonCount(2, 'purchase_orders')
            ->assertJsonPath('purchase_orders.0.supplier_code', 'PDL')
            ->assertJsonPath('purchase_orders.0.lines', 2)
            ->assertJsonPath('purchase_orders.0.total', '12200.0000')
            ->assertJsonPath('purchase_orders.0.expected_date', '2026-10-01')
            ->assertJsonPath('purchase_orders.1.supplier_code', 'KEMSA')
            ->assertJsonPath('purchase_orders.1.total', '2250.0000');
        $this->assertSame(0, PurchaseOrder::count());

        $imported = $this->postJson('/api/purchase-orders/import', ['rows' => $rows])
            ->assertCreated()->assertJsonCount(2, 'purchase_orders')->json();

        $pdl = PurchaseOrder::with('lines')->findOrFail($imported['purchase_orders'][0]['id']);
        $this->assertSame('DRAFT', $pdl->status);
        $this->assertSame($this->supplier->id, $pdl->supplier_id);
        $this->assertSame('2026-10-01', $pdl->expected_date?->toDateString());
        $this->assertStringStartsWith('PO-', $pdl->doc_number);
        $this->assertCount(2, $pdl->lines);

        $kemsa = PurchaseOrder::with('lines')->findOrFail($imported['purchase_orders'][1]['id']);
        $line = $kemsa->lines->sole();
        $this->assertSame('450.0000', (string) $line->unit_price, 'trade 500 less 10% is the net cost');
        $this->assertSame('500.0000', (string) $line->trade_price);
        $this->assertSame($this->uoms['BOX']->id, $line->uom_id, 'a blank uom_code takes the smallest purchase pack');

        // The import lands in the ordinary flow: it can be approved.
        $this->postJson("/api/purchase-orders/{$pdl->id}/approve")->assertOk()->assertJsonPath('status', 'APPROVED');
    }

    public function test_one_bad_row_creates_nothing_and_every_problem_is_named(): void
    {
        $this->supplier->update(['status' => 'SUSPENDED']);

        $response = $this->postJson('/api/purchase-orders/import', ['rows' => [
            ['supplier_code' => 'PDL', 'product_code' => 'AMOX500', 'qty' => '10', 'unit_price' => '420'],
            ['supplier_code' => 'KEMSA', 'product_code' => 'NOPE', 'qty' => '0', 'unit_price' => '10'],
            ['supplier_code' => 'KEMSA', 'product_code' => 'AMOX500', 'qty' => '5'],
            ['supplier_code' => 'KEMSA', 'product_code' => 'AMOX500', 'qty' => '5', 'unit_price' => '400', 'uom_code' => 'BOX'],
            ['supplier_code' => 'KEMSA', 'product_code' => 'AMOX500', 'qty' => '5', 'unit_price' => '400', 'uom_code' => 'BOX'],
        ]])->assertStatus(422)->assertJsonPath('error.code', 'PO_IMPORT_INVALID');

        $rows = $response->json('error.details.rows');
        $this->assertStringContainsString('SUSPENDED', $rows['1'][0]);
        $this->assertStringContainsString('unknown product code NOPE', implode('; ', $rows['2']));
        $this->assertStringContainsString('qty must be a number above zero', implode('; ', $rows['2']));
        $this->assertStringContainsString('unit_price', implode('; ', $rows['3']));
        $this->assertStringContainsString('appears twice', implode('; ', $rows['5']));
        $this->assertSame(0, PurchaseOrder::count(), 'the import is all-or-nothing');
    }

    public function test_a_file_naming_no_supplier_lands_on_the_one_chosen_at_upload(): void
    {
        // A plain product/qty/price sheet — the buyer picks the supplier in
        // the dialog instead of adding a column to the file.
        $imported = $this->postJson('/api/purchase-orders/import', [
            'supplier_id' => $this->kemsa->id,
            'rows' => [
                ['product_code' => 'AMOX500', 'qty' => '10', 'unit_price' => '420'],
                ['supplier_code' => 'PDL', 'product_code' => 'AMOX500', 'qty' => '2', 'unit_price' => '400'],
            ],
        ])->assertCreated()->assertJsonCount(2, 'purchase_orders')->json();

        $this->assertSame('KEMSA', $imported['purchase_orders'][0]['supplier_code'], 'the blank row takes the chosen supplier');
        $this->assertSame('PDL', $imported['purchase_orders'][1]['supplier_code'], 'a named supplier_code always wins');

        // Without a chosen supplier, a blank supplier_code is an error.
        $this->postJson('/api/purchase-orders/import', ['rows' => [
            ['product_code' => 'AMOX500', 'qty' => '10', 'unit_price' => '420'],
        ]])->assertStatus(422)->assertJsonPath('error.code', 'PO_IMPORT_INVALID');
    }

    public function test_a_draft_can_be_amended_in_full_until_it_is_approved(): void
    {
        $imported = $this->postJson('/api/purchase-orders/import', [
            'supplier_id' => $this->kemsa->id,
            'rows' => [['product_code' => 'AMOX500', 'qty' => '10', 'unit_price' => '420']],
        ])->assertCreated()->json();
        $id = $imported['purchase_orders'][0]['id'];

        // Re-assign the supplier, change the date, replace the lines.
        $this->patchJson("/api/purchase-orders/{$id}", [
            'supplier_id' => $this->supplier->id,
            'expected_date' => '2026-10-15',
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['CTN']->id, 'qty_ordered' => '3', 'trade_price' => '4500', 'discount_pct' => '10']],
        ])->assertOk()
            ->assertJsonPath('supplier_id', $this->supplier->id)
            ->assertJsonPath('status', 'DRAFT')
            ->assertJsonCount(1, 'lines')
            ->assertJsonPath('lines.0.unit_price', '4050.0000');

        $po = PurchaseOrder::with('lines')->findOrFail($id);
        $this->assertSame('2026-10-15', $po->expected_date?->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'PO_UPDATED', 'entity_id' => $id]);

        // Approved, the order is a commitment: no more editing.
        $this->postJson("/api/purchase-orders/{$id}/approve")->assertOk();
        $this->patchJson("/api/purchase-orders/{$id}", [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'qty_ordered' => '1', 'unit_price' => '1']],
        ])->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');
    }

    public function test_the_import_needs_the_po_create_permission(): void
    {
        $this->grantPermissions(['supplier.view']);

        $this->postJson('/api/purchase-orders/import', ['rows' => [
            ['supplier_code' => 'PDL', 'product_code' => 'AMOX500', 'qty' => '1', 'unit_price' => '1'],
        ]])->assertForbidden();
    }
}
