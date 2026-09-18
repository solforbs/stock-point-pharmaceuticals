<?php

namespace Tests\Feature\Api;

use App\Models\PurchaseOrder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 9.1 — REQUISITION → approval → supplier selection → PURCHASE ORDER,
 * and the preserved v5 reorder arithmetic (Part 22.1).
 */
class RequisitionHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['requisition.view', 'requisition.create', 'requisition.approve', 'po.create', 'stock.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_a_requisition_becomes_a_purchase_order_after_approval(): void
    {
        $req = $this->postJson('/api/requisitions', [
            'needed_by' => now()->addWeek()->toDateString(),
            'lines' => [['product_id' => $this->amox->id, 'qty_base' => '4000', 'notes' => 'Hospital tender stock']],
        ])->assertCreated()->assertJsonPath('status', 'DRAFT')->assertJsonPath('lines.0.qty_requested', '4000.0000')->json();

        $this->postJson("/api/requisitions/{$req['id']}/approve")->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');
        $this->postJson("/api/requisitions/{$req['id']}/submit")->assertOk()->assertJsonPath('status', 'PENDING_APPROVAL');
        $this->postJson("/api/requisitions/{$req['id']}/approve")->assertOk()->assertJsonPath('status', 'APPROVED');

        $po = $this->postJson("/api/requisitions/{$req['id']}/convert", [
            'supplier_id' => $this->supplier->id,
            'lines' => [['requisition_line_id' => $req['lines'][0]['id'], 'uom_id' => $this->uoms['BOX']->id, 'qty_ordered' => '20', 'unit_price' => '420']],
        ])->assertCreated()->assertJsonPath('status', 'DRAFT')->assertJsonPath('requisition_id', $req['id'])->json();

        $this->assertSame('20.0000', (string) PurchaseOrder::findOrFail($po['id'])->lines()->first()->qty_ordered);
        $this->getJson("/api/requisitions/{$req['id']}")->assertOk()->assertJsonPath('status', 'CONVERTED');

        $this->postJson("/api/requisitions/{$req['id']}/convert", ['supplier_id' => $this->supplier->id, 'lines' => []])->assertStatus(422);
    }

    public function test_a_requisition_can_be_rejected_with_a_reason_and_selling_units_cannot_be_purchased(): void
    {
        $req = $this->postJson('/api/requisitions', ['lines' => [['product_id' => $this->amox->id, 'qty_base' => '100']]])->assertCreated()->json();
        $this->postJson("/api/requisitions/{$req['id']}/submit")->assertOk();
        $this->postJson("/api/requisitions/{$req['id']}/reject", [])->assertStatus(422);
        $this->postJson("/api/requisitions/{$req['id']}/reject", ['reason' => 'Budget frozen this month'])->assertOk()->assertJsonPath('status', 'REJECTED');

        $req2 = $this->postJson('/api/requisitions', ['lines' => [['product_id' => $this->amox->id, 'qty_base' => '100']]])->assertCreated()->json();
        $this->postJson("/api/requisitions/{$req2['id']}/submit")->assertOk();
        $this->postJson("/api/requisitions/{$req2['id']}/approve")->assertOk();
        $this->postJson("/api/requisitions/{$req2['id']}/convert", [
            'supplier_id' => $this->supplier->id,
            'lines' => [['requisition_line_id' => $req2['lines'][0]['id'], 'uom_id' => $this->uoms['STR']->id, 'qty_ordered' => '10', 'unit_price' => '25']],
        ])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
    }

    public function test_the_reorder_advisor_applies_the_preserved_v5_arithmetic(): void
    {
        $this->amox->update(['reorder_point' => '5000', 'lead_time_days' => 5]);
        $this->receive('R1', now()->addYears(2)->toDateString(), '1000', '2.0000');

        $row = $this->getJson('/api/procurement/reorder-suggestions')->assertOk()->assertJsonCount(1, 'data')->json('data.0');
        $this->assertSame('1000.0000', $row['free_to_sell']);
        $this->assertSame('0.0000', $row['on_order']);
        $this->assertSame('14000.0000', $row['suggested_qty_base'], 'max(round(5000 × 3 − 1000 − 0), 10)');
        $this->assertSame(now()->addDays(7)->toDateString(), $row['required_by'], 'lead time + 2');
        $this->assertSame('PDL', $row['supplier_code'], 'grouped by the supplier who last delivered');

        // An approved, outstanding PO counts as on-order and shrinks the suggestion.
        $req = $this->postJson('/api/requisitions', ['lines' => [['product_id' => $this->amox->id, 'qty_base' => '4000']]])->assertCreated()->json();
        $this->postJson("/api/requisitions/{$req['id']}/submit")->assertOk();
        $this->postJson("/api/requisitions/{$req['id']}/approve")->assertOk();
        $po = $this->postJson("/api/requisitions/{$req['id']}/convert", [
            'supplier_id' => $this->supplier->id,
            'lines' => [['requisition_line_id' => $req['lines'][0]['id'], 'uom_id' => $this->uoms['BOX']->id, 'qty_ordered' => '20', 'unit_price' => '420']],
        ])->assertCreated()->json();
        PurchaseOrder::whereKey($po['id'])->update(['status' => 'APPROVED']);

        $row = $this->getJson('/api/procurement/reorder-suggestions')->assertOk()->json('data.0');
        $this->assertSame('4000.0000', $row['on_order']);
        $this->assertSame('10000.0000', $row['suggested_qty_base']);

        // Enough cover: the product drops off the list.
        $this->receive('R2', now()->addYears(2)->toDateString(), '1000', '2.0000');
        $this->getJson('/api/procurement/reorder-suggestions')->assertOk()->assertJsonCount(0, 'data');
    }
}
