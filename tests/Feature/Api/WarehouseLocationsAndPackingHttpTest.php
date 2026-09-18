<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\PickingList;
use App\Models\ProductBatch;
use App\Models\Store;
use App\Services\Inventory\StockLedgerService;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 10.6 — warehouse locations inside a store (with stock put away per
 * location from the ledger) and the packing step between pick and dispatch.
 */
class WarehouseLocationsAndPackingHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('L1', now()->addYears(2)->toDateString(), '5000', '2.0000');
        $this->grantPermissions(['stock.view', 'location.manage', 'sale.create', 'sale.view', 'warehouse.pick', 'warehouse.dispatch']);
        Sanctum::actingAs($this->user);
    }

    public function test_locations_are_created_per_store_renamed_and_deactivated(): void
    {
        $location = $this->postJson('/api/locations', ['store_id' => $this->store->id, 'code' => 'A-01-01', 'name' => 'Aisle A rack 1 bin 1', 'location_type' => 'SHELF', 'aisle' => 'A', 'rack' => '01', 'bin' => '01', 'capacity' => 200])
            ->assertCreated()->assertJsonPath('location_type', 'SHELF')->assertJsonPath('store.code', 'MAIN')->json();

        $this->postJson('/api/locations', ['store_id' => $this->store->id, 'code' => 'A-01-01'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->postJson('/api/locations', ['store_id' => $this->store->id, 'code' => 'X', 'location_type' => 'FREEZER'])->assertStatus(422)->assertJsonValidationErrors('location_type');

        // The same code is fine in another store of the branch.
        $cold = Store::create(['branch_id' => $this->branch->id, 'code' => 'COLD', 'name' => 'Cold room', 'store_type' => 'MAIN', 'is_sellable' => false]);
        $this->postJson('/api/locations', ['store_id' => $cold->id, 'code' => 'A-01-01', 'location_type' => 'COLD_SHELF'])->assertCreated()->assertJsonPath('location_type', 'COLD_SHELF');

        $this->getJson('/api/locations?store_id='.$this->store->id)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $location['id'])->assertJsonPath('0.on_hand_base', '0.0000');
        $this->getJson('/api/locations')->assertOk()->assertJsonCount(2);

        $this->patchJson("/api/locations/{$location['id']}", ['name' => 'Fast movers'])->assertOk()->assertJsonPath('name', 'Fast movers')->assertJsonPath('location_type', 'SHELF');
        $this->patchJson("/api/locations/{$location['id']}", ['code' => 'A-01-01'])->assertOk();
        $this->patchJson("/api/locations/{$location['id']}", ['is_active' => false])->assertOk()->assertJsonPath('is_active', false);

        $this->getJson('/api/locations?store_id='.$this->store->id)->assertOk()->assertJsonCount(0);
        $this->getJson('/api/locations?include_inactive=1&store_id='.$this->store->id)->assertOk()->assertJsonCount(1);
        $this->assertSame(1, AuditLog::where('action', 'LOCATION_DEACTIVATED')->where('entity_id', $location['id'])->count());
        $this->assertSame(2, AuditLog::where('action', 'LOCATION_CREATED')->count());
    }

    public function test_location_stock_is_summed_from_the_ledger(): void
    {
        $location = $this->postJson('/api/locations', ['store_id' => $this->store->id, 'code' => 'B-02'])->assertCreated()->json();
        $this->getJson("/api/locations/{$location['id']}/stock")->assertOk()->assertJsonCount(0, 'data');

        $batch = ProductBatch::where('batch_number', 'L1')->firstOrFail();
        $ledger = app(StockLedgerService::class);
        foreach (['300', '-120'] as $qty) {
            $ledger->post([
                'txn_type' => str_starts_with($qty, '-') ? 'ADJUSTMENT_DOWN' : 'ADJUSTMENT_UP', 'product_id' => $this->amox->id, 'batch_id' => $batch->id,
                'store_id' => $this->store->id, 'location_id' => $location['id'], 'qty_base' => $qty, 'unit_cost' => '2.0000',
                'source_doc_type' => 'test', 'source_doc_id' => $location['id'], 'user_id' => $this->user->id, 'branch_id' => $this->branch->id,
            ]);
        }

        $this->getJson("/api/locations/{$location['id']}/stock")->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_code', 'AMOX500')
            ->assertJsonPath('data.0.batch_number', 'L1')
            ->assertJsonPath('data.0.qty_base', '180.0000');
        $this->getJson('/api/locations')->assertOk()->assertJsonPath('0.on_hand_base', '180.0000');
    }

    public function test_location_permissions(): void
    {
        $this->grantPermissions(['stock.view']);
        $this->getJson('/api/locations')->assertOk();
        $this->postJson('/api/locations', ['store_id' => $this->store->id, 'code' => 'Z'])->assertStatus(403);

        $this->grantPermissions([]);
        $this->getJson('/api/locations')->assertStatus(403);
    }

    public function test_completed_picks_are_packed_and_leave_the_queue_on_dispatch(): void
    {
        [$order, $list] = $this->pickedOrder();

        $this->postJson("/api/picking-lists/{$list['id']}/pack", ['package_count' => 2])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_STATE');
        $this->getJson('/api/packing/queue')->assertOk()->assertJsonCount(0, 'data');

        $this->postJson("/api/picking-lists/{$list['id']}/complete")->assertOk();
        $this->getJson('/api/packing/queue?packed=no')->assertOk()
            ->assertJsonPath('data.0.id', $list['id'])
            ->assertJsonPath('data.0.sales_order.customer.code', 'TCRH')
            ->assertJsonPath('data.0.lines_count', 1)
            ->assertJsonPath('data.0.packed_at', null);

        $this->postJson("/api/picking-lists/{$list['id']}/pack", ['package_count' => 0])->assertStatus(422)->assertJsonValidationErrors('package_count');
        $this->postJson("/api/picking-lists/{$list['id']}/pack", ['package_count' => 3, 'total_weight_kg' => '12.5', 'packing_notes' => 'Two cartons and a cool box'])
            ->assertOk()->assertJsonPath('package_count', 3)->assertJsonPath('total_weight_kg', '12.500')->assertJsonPath('packer.id', $this->user->id);

        // Repeatable to correct.
        $this->postJson("/api/picking-lists/{$list['id']}/pack", ['package_count' => 4])->assertOk()->assertJsonPath('package_count', 4)->assertJsonPath('total_weight_kg', null);
        $this->assertSame(1, AuditLog::where('action', 'PICKING_LIST_PACKED')->count());
        $this->assertSame(1, AuditLog::where('action', 'PACKING_CORRECTED')->count());

        $this->getJson('/api/packing/queue?packed=no')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/packing/queue?packed=yes')->assertOk()->assertJsonPath('data.0.id', $list['id']);

        $this->postJson("/api/sales-orders/{$order['id']}/dispatch", [], ['Idempotency-Key' => 'pk-1'])->assertCreated();
        $this->getJson('/api/packing/queue')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/picking-lists/{$list['id']}/pack", ['package_count' => 5])->assertStatus(422)->assertJsonPath('error.code', 'ALREADY_DISPATCHED');
        $this->assertSame(4, PickingList::findOrFail($list['id'])->package_count);
    }

    public function test_packing_is_not_required_for_dispatch_and_needs_the_pick_permission(): void
    {
        [$order, $list] = $this->pickedOrder();
        $this->postJson("/api/picking-lists/{$list['id']}/complete")->assertOk();
        $this->postJson("/api/sales-orders/{$order['id']}/dispatch", [], ['Idempotency-Key' => 'pk-2'])->assertCreated()->assertJsonPath('status', 'DISPATCHED');

        $this->grantPermissions(['warehouse.dispatch']);
        $this->getJson('/api/packing/queue')->assertStatus(403);
        $this->postJson("/api/picking-lists/{$list['id']}/pack", ['package_count' => 1])->assertStatus(403);
    }

    /**
     * An accepted wholesale order with every picking line picked (list not yet completed).
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function pickedOrder(): array
    {
        $quotation = $this->postJson('/api/quotations', ['customer_id' => $this->customer->id, 'store_id' => $this->store->id, 'valid_until' => now()->addWeek()->toDateString(), 'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => '2']]])->assertCreated()->json();
        $order = $this->postJson("/api/quotations/{$quotation['id']}/accept", [], ['Idempotency-Key' => 'pk-q-'.$quotation['id']])->assertCreated()->json();
        $list = $this->postJson("/api/sales-orders/{$order['id']}/pick")->assertCreated()->json();
        foreach ($list['lines'] as $line) {
            $this->postJson("/api/picking-lists/{$list['id']}/lines/{$line['id']}/pick", ['qty_picked_base' => $line['qty_to_pick_base']])->assertOk();
        }

        return [$order, $list];
    }
}
