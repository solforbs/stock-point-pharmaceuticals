<?php

namespace Tests\Feature\Api;

use App\Models\ProductBatch;
use App\Models\ProductCategory;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * The read side the SPA needs: every posting document can be listed and
 * opened by id within the active branch.
 */
class DocumentListsHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('L1', now()->addYears(2)->toDateString(), '5000', '2.0000');
        $this->grantPermissions([
            'sale.create', 'sale.view', 'stock.view', 'stock.adjust', 'stock.adjust.approve', 'product.view', 'product.cost.view',
            'po.create', 'po.approve', 'grn.create', 'invoice.match', 'warehouse.pick', 'warehouse.dispatch',
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_procurement_documents_can_be_listed_and_opened(): void
    {
        $po = $this->postJson('/api/purchase-orders', ['supplier_id' => $this->supplier->id, 'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'qty_ordered' => '5', 'unit_price' => '420']]])->assertCreated()->json();
        $this->postJson("/api/purchase-orders/{$po['id']}/approve")->assertOk();

        $this->getJson("/api/purchase-orders/{$po['id']}")->assertOk()
            ->assertJsonPath('doc_number', $po['doc_number'])
            ->assertJsonPath('lines.0.uom.code', 'BOX')
            ->assertJsonPath('lines.0.product.code', 'AMOX500')
            ->assertJsonPath('supplier.code', 'PDL');

        $grn = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po['id'], 'supplier_id' => $this->supplier->id, 'store_id' => $this->store->id,
            'lines' => [['purchase_order_line_id' => $po['lines'][0]['id'], 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'qty_delivered' => '5', 'qty_accepted' => '5', 'batch_number' => 'G1', 'expiry_date' => now()->addYears(2)->toDateString(), 'unit_cost' => '420']],
        ])->assertCreated()->json();
        $this->getJson('/api/goods-receipts')->assertOk()->assertJsonPath('data.0.id', $grn['id'])->assertJsonPath('data.0.supplier.code', 'PDL')->assertJsonPath('data.0.purchase_order.doc_number', $po['doc_number']);

        $invoice = $this->postJson('/api/supplier-invoices', [
            'supplier_id' => $this->supplier->id, 'invoice_number' => 'INV-1', 'invoice_date' => now()->toDateString(),
            'lines' => [['purchase_order_line_id' => $po['lines'][0]['id'], 'product_id' => $this->amox->id, 'qty' => '5', 'unit_price' => '420']],
        ])->assertCreated()->json();
        $this->getJson('/api/supplier-invoices?match_status=UNMATCHED')->assertOk()->assertJsonPath('data.0.id', $invoice['id']);
        $this->getJson("/api/supplier-invoices/{$invoice['id']}")->assertOk()->assertJsonPath('lines.0.product.code', 'AMOX500')->assertJsonPath('lines.0.purchase_order_line.unit_price', '420.0000');
    }

    public function test_wholesale_and_inventory_documents_can_be_listed_and_opened(): void
    {
        $quotation = $this->postJson('/api/quotations', ['customer_id' => $this->customer->id, 'store_id' => $this->store->id, 'valid_until' => now()->addWeek()->toDateString(), 'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => '2']]])->assertCreated()->json();
        $this->getJson('/api/quotations?status=DRAFT')->assertOk()->assertJsonPath('data.0.id', $quotation['id'])->assertJsonPath('data.0.customer.code', 'TCRH');

        $order = $this->postJson("/api/quotations/{$quotation['id']}/accept", [], ['Idempotency-Key' => 'dl-1'])->assertCreated()->json();
        $list = $this->postJson("/api/sales-orders/{$order['id']}/pick")->assertCreated()->json();
        $this->getJson('/api/picking-lists?status=IN_PROGRESS')->assertOk()->assertJsonPath('data.0.id', $list['id'])->assertJsonPath('data.0.sales_order.customer.code', 'TCRH');
        $this->getJson("/api/picking-lists/{$list['id']}")->assertOk()->assertJsonPath('lines.0.product.code', 'AMOX500')->assertJsonPath('lines.0.batch.batch_number', 'L1');

        foreach ($list['lines'] as $line) {
            $this->postJson("/api/picking-lists/{$list['id']}/lines/{$line['id']}/pick", ['qty_picked_base' => $line['qty_to_pick_base']])->assertOk();
        }
        $this->postJson("/api/picking-lists/{$list['id']}/complete")->assertOk();
        $note = $this->postJson("/api/sales-orders/{$order['id']}/dispatch", [], ['Idempotency-Key' => 'dn-1'])->assertCreated()->json();
        $this->getJson('/api/delivery-notes?status=DISPATCHED')->assertOk()->assertJsonPath('data.0.id', $note['id']);
        $this->getJson("/api/delivery-notes/{$note['id']}")->assertOk()->assertJsonPath('lines.0.product.code', 'AMOX500')->assertJsonPath('lines.0.batch_allocations.0.batch.batch_number', 'L1');

        $adjustment = $this->postJson('/api/inventory/adjustments', ['store_id' => $this->store->id, 'reason_code' => 'BREAKAGE', 'lines' => [['product_id' => $this->amox->id, 'batch_id' => ProductBatch::where('batch_number', 'L1')->value('id'), 'qty_base' => '-10']]])->assertCreated()->json();
        $this->getJson('/api/inventory/adjustments?approval_status=APPROVED')->assertOk()->assertJsonPath('data.0.id', $adjustment['id']);
        $this->getJson("/api/inventory/adjustments/{$adjustment['id']}")->assertOk()->assertJsonPath('lines.0.batch.batch_number', 'L1')->assertJsonPath('lines.0.unit_cost', '2.0000');
    }

    public function test_lookups_for_pickers(): void
    {
        ProductCategory::create(['code' => 'ANTI', 'name' => 'Antibiotics', 'is_active' => true]);

        $this->getJson('/api/users?q=cash')->assertOk()->assertJsonPath('0.name', 'Test Cashier')->assertJsonMissingPath('0.email');
        $this->getJson('/api/product-categories')->assertOk()->assertJsonPath('0.code', 'ANTI');
    }
}
