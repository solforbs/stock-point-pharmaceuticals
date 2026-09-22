<?php

namespace Tests\Feature\Api;

use App\Models\ProductCategory;
use App\Models\StockAdjustment;
use App\Models\StockLedger;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 21.8 over HTTP: the eight quantity states, the real ledger with a
 * running balance, reasoned adjustments with an approval threshold, and
 * the batch quality state machine.
 */
class InventoryHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['stock.view', 'stock.adjust', 'stock.adjust.approve', 'quality.release', 'product.cost.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_stock_reports_the_eight_states_from_the_batch_register(): void
    {
        $released = $this->receive('R1', now()->addYears(2)->toDateString(), '10000', '2.0000');
        $this->receive('Q1', now()->addYears(2)->toDateString(), '500', '2.0000', release: false);

        $row = $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)
            ->assertOk()
            ->assertJsonPath('data.0.on_hand', '10500.0000')
            ->assertJsonPath('data.0.free_to_sell', '10000.0000')
            ->assertJsonPath('data.0.pending_qc', '500.0000')
            ->assertJsonPath('data.0.reserved', '0.0000')
            ->assertJsonPath('data.0.quarantined', '0.0000')
            ->assertJsonPath('data.0.expired', '0.0000')
            ->assertJsonPath('data.0.recalled', '0.0000')
            ->assertJsonPath('data.0.in_transit', '0.0000')
            ->assertJsonPath('data.0.on_order', '0.0000')
            ->assertJsonPath('data.0.value_at_cost', '21000.0000')
            ->json('data.0');
        $this->assertCount(2, $row['batches']);

        $this->postJson("/api/batches/{$released->id}/quarantine", [])->assertStatus(422);
        $this->postJson("/api/batches/{$released->id}/quarantine", ['reason' => 'Cold chain excursion on delivery'])
            ->assertOk()->assertJsonPath('status', 'QUARANTINED');

        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)
            ->assertJsonPath('data.0.free_to_sell', '0.0000')
            ->assertJsonPath('data.0.quarantined', '10000.0000');

        $this->postJson("/api/batches/{$released->id}/release", ['justification' => 'Temperature log reviewed, within range'])
            ->assertOk()->assertJsonPath('status', 'RELEASED');
        $this->postJson("/api/batches/{$released->id}/release")->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');

        $this->grantPermissions(['stock.view']);
        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->assertOk()->assertJsonMissingPath('data.0.value_at_cost');
        $this->postJson("/api/batches/{$released->id}/quarantine", ['reason' => 'Trying it on'])->assertStatus(403);
    }

    public function test_stock_can_be_narrowed_to_a_heading_and_its_sub_headings(): void
    {
        $drugs = ProductCategory::create(['code' => 'T-DRUGS', 'name' => 'Drugs']);
        $antibiotics = ProductCategory::create(['code' => 'T-ABX', 'name' => 'Antibiotics', 'parent_id' => $drugs->id]);
        $topicals = ProductCategory::create(['code' => 'T-TOP', 'name' => 'Topicals']);
        $this->amox->update(['category_id' => $antibiotics->id]);
        $this->receive('C1', now()->addYears(2)->toDateString(), '100', '2.0000');

        $this->getJson('/api/inventory/stock?category_id='.$drugs->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category_name', 'Antibiotics');
        $this->getJson('/api/inventory/stock?category_id='.$topicals->id)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_adjustments_need_a_reason_and_large_ones_wait_for_approval(): void
    {
        $batch = $this->receive('A1', now()->addYears(2)->toDateString(), '20000', '2.0000');

        $this->postJson('/api/inventory/adjustments', ['reason_code' => 'BECAUSE'] + $this->adjustmentPayload($batch->id, '-100'))->assertStatus(422);
        $this->postJson('/api/inventory/adjustments', $this->adjustmentPayload($batch->id, '0'))->assertStatus(422);

        // KES 200 of breakage: below the 10,000 threshold, posts immediately.
        $small = $this->postJson('/api/inventory/adjustments', $this->adjustmentPayload($batch->id, '-100'))
            ->assertCreated()
            ->assertJsonPath('approval_status', 'APPROVED')
            ->json();
        $this->assertSame('-100.0000', (string) StockLedger::where('source_doc_id', $small['id'])->sum('qty_base'));
        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->assertJsonPath('data.0.on_hand', '19900.0000');

        // KES 12,000 of theft: above the threshold, waits for a second person.
        $large = $this->postJson('/api/inventory/adjustments', $this->adjustmentPayload($batch->id, '-6000', 'THEFT'))
            ->assertStatus(202)
            ->assertJsonPath('approval_status', 'PENDING')
            ->json();
        $this->assertSame(0, StockLedger::where('source_doc_id', $large['id'])->count(), 'nothing moves until approved');
        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->assertJsonPath('data.0.on_hand', '19900.0000');

        $this->postJson("/api/inventory/adjustments/{$large['id']}/approve")->assertOk()->assertJsonPath('approval_status', 'APPROVED');
        $this->assertSame('-6000.0000', (string) StockLedger::where('source_doc_id', $large['id'])->sum('qty_base'));
        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->assertJsonPath('data.0.on_hand', '13900.0000');

        $rejected = $this->postJson('/api/inventory/adjustments', $this->adjustmentPayload($batch->id, '-5100', 'THEFT'))->assertStatus(202)->json();
        $this->postJson("/api/inventory/adjustments/{$rejected['id']}/reject", ['reason' => 'Recount found the stock'])
            ->assertOk()->assertJsonPath('approval_status', 'REJECTED');
        $this->assertSame(0, StockLedger::where('source_doc_id', $rejected['id'])->count());
        $this->assertSame(3, StockAdjustment::count(), 'rejected requests stay on record');
    }

    public function test_the_ledger_endpoint_shows_every_movement_with_a_running_balance(): void
    {
        $batch = $this->receive('L1', now()->addYears(2)->toDateString(), '1000', '2.0000');
        $this->postJson('/api/inventory/adjustments', $this->adjustmentPayload($batch->id, '-40'))->assertCreated();

        $rows = $this->getJson("/api/inventory/ledger?batch_id={$batch->id}&store_id={$this->store->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.txn_type', 'GRN_RECEIPT')
            ->assertJsonPath('data.0.running_balance', '1000.0000')
            ->assertJsonPath('data.1.running_balance', '960.0000')
            ->json('data');
        $this->assertArrayHasKey('unit_cost', $rows[0]);

        $this->getJson("/api/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('batch_number', 'L1')
            ->assertJsonCount(2, 'movements')
            ->assertJsonPath('distributed_base', '0.0000');

        $this->grantPermissions(['stock.view']);
        $this->getJson("/api/inventory/ledger?batch_id={$batch->id}&store_id={$this->store->id}")
            ->assertOk()->assertJsonMissingPath('data.0.unit_cost');
    }

    /**
     * @return array<string, mixed>
     */
    private function adjustmentPayload(string $batchId, string $qty, string $reason = 'BREAKAGE'): array
    {
        return [
            'store_id' => $this->store->id,
            'reason_code' => $reason,
            'lines' => [['product_id' => $this->amox->id, 'batch_id' => $batchId, 'qty_base' => $qty]],
        ];
    }
}
