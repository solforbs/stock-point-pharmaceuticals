<?php

namespace Tests\Feature\Api;

use App\Models\JournalEntry;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\StockLedger;
use App\Models\Store;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 7.6 — DRAFT → APPROVED → DISPATCHED (in transit) → RECEIVED, or
 * DISCREPANCY resolved by a ledger-true correction. No journal: one entity.
 */
class StockTransferHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    private Store $cold;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->cold = Store::create(['branch_id' => $this->branch->id, 'code' => 'COLD', 'name' => 'Cold Room', 'store_type' => 'COLD', 'is_sellable' => true]);
        $this->receive('T1', now()->addYears(2)->toDateString(), '2000', '2.0000');
        $this->grantPermissions([
            'stock.view', 'stock.transfer.create', 'stock.transfer.approve', 'stock.transfer.dispatch', 'stock.transfer.receive',
            'stock.adjust', 'stock.adjust.approve', 'product.cost.view',
        ]);
        Sanctum::actingAs($this->user);

        $this->approver = User::create(['name' => 'Transfer Approver', 'username' => 'approver2', 'email' => 'approver2@example.test', 'password' => 'password-long-enough']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->approver->assignRole(Role::where('name', 'Test role')->firstOrFail());
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_a_transfer_moves_stock_through_in_transit_without_a_journal(): void
    {
        $batch = $this->batchId('T1');
        $transfer = $this->postJson('/api/inventory/transfers', $this->payload($batch, '500'))
            ->assertCreated()->assertJsonPath('status', 'DRAFT')->assertJsonCount(1, 'lines')->json();

        $this->postJson("/api/inventory/transfers/{$transfer['id']}/dispatch")->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');

        // Anti-self-approval blocks creator
        $this->postJson("/api/inventory/transfers/{$transfer['id']}/approve")->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');

        // Distinct approver succeeds
        Sanctum::actingAs($this->approver);
        $this->postJson("/api/inventory/transfers/{$transfer['id']}/approve")->assertOk()->assertJsonPath('status', 'APPROVED');
        Sanctum::actingAs($this->user);

        $this->postJson("/api/inventory/transfers/{$transfer['id']}/dispatch")->assertOk()->assertJsonPath('status', 'DISPATCHED');

        $stock = $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->assertOk()->json('data');
        $byStore = collect($stock)->keyBy('store_code');
        $this->assertSame('1500.0000', $byStore['MAIN']['on_hand']);
        $this->assertSame('0.0000', $byStore['COLD']['on_hand'], 'nothing has arrived yet');
        $this->assertSame('500.0000', $byStore['COLD']['in_transit'], 'the quantity is in transit to the cold room');

        $this->postJson("/api/inventory/transfers/{$transfer['id']}/receive")->assertOk()->assertJsonPath('status', 'RECEIVED');

        $byStore = collect($this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->json('data'))->keyBy('store_code');
        $this->assertSame('500.0000', $byStore['COLD']['on_hand']);
        $this->assertSame('0.0000', $byStore['COLD']['in_transit']);

        $rows = StockLedger::where('source_doc_id', $transfer['id'])->orderBy('created_at')->get();
        $this->assertSame(['TRANSFER_OUT', 'TRANSFER_IN'], $rows->pluck('txn_type')->all());
        $this->assertSame('-500.0000', (string) $rows[0]->qty_base);
        $this->assertSame('2.0000', (string) $rows[1]->unit_cost, 'stock arrives at the batch cost it left with');
        $this->assertSame(0, JournalEntry::where('source_doc_type', 'stock_transfer')->count(), 'Part 12.3: no journal between stores of one entity');
    }

    public function test_a_short_receipt_is_a_discrepancy_until_it_is_resolved(): void
    {
        $batch = $this->batchId('T1');
        $transfer = $this->postJson('/api/inventory/transfers', $this->payload($batch, '400'))->assertCreated()->json();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/inventory/transfers/{$transfer['id']}/approve")->assertOk();
        Sanctum::actingAs($this->user);

        $this->postJson("/api/inventory/transfers/{$transfer['id']}/dispatch")->assertOk();

        $this->postJson("/api/inventory/transfers/{$transfer['id']}/receive", ['lines' => [['id' => $transfer['lines'][0]['id'], 'qty_received' => '380']]])
            ->assertOk()->assertJsonPath('status', 'DISCREPANCY');

        $byStore = collect($this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->json('data'))->keyBy('store_code');
        $this->assertSame('380.0000', $byStore['COLD']['on_hand']);
        $this->assertSame('20.0000', $byStore['COLD']['in_transit'], 'the missing 20 are still unaccounted for');

        $this->postJson("/api/inventory/transfers/{$transfer['id']}/resolve", ['resolution' => 'LOST', 'reason' => 'Carton damaged on the Kakuma road', 'adjustment_reason_code' => 'BREAKAGE'])
            ->assertOk()->assertJsonPath('status', 'RECEIVED');

        $byStore = collect($this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->json('data'))->keyBy('store_code');
        $this->assertSame('380.0000', $byStore['COLD']['on_hand']);
        $this->assertSame('0.0000', $byStore['COLD']['in_transit']);

        $adjustment = StockAdjustment::where('store_id', $this->cold->id)->firstOrFail();
        $this->assertSame('BREAKAGE', $adjustment->reason_code);
        $this->assertSame('APPROVED', $adjustment->approval_status, 'KES 40 is below the approval threshold');
        $this->assertSame('-20.0000', (string) StockLedger::where('source_doc_id', $adjustment->id)->sum('qty_base'));
    }

    public function test_more_than_the_unreserved_quantity_cannot_be_dispatched(): void
    {
        $transfer = $this->postJson('/api/inventory/transfers', $this->payload($this->batchId('T1'), '5000'))->assertCreated()->json();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/inventory/transfers/{$transfer['id']}/approve")->assertOk();
        Sanctum::actingAs($this->user);

        $this->postJson("/api/inventory/transfers/{$transfer['id']}/dispatch")->assertStatus(409)->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');
        $this->assertSame(0, StockLedger::where('txn_type', 'TRANSFER_OUT')->count());
    }

    public function test_transfer_actions_need_their_permissions(): void
    {
        $this->grantPermissions(['stock.view']);
        $this->postJson('/api/inventory/transfers', $this->payload($this->batchId('T1'), '10'))->assertStatus(403);
        $this->getJson('/api/inventory/transfers')->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $batchId, string $qty): array
    {
        return [
            'from_store_id' => $this->store->id,
            'to_store_id' => $this->cold->id,
            'lines' => [['product_id' => $this->amox->id, 'batch_id' => $batchId, 'qty_base' => $qty]],
        ];
    }

    private function batchId(string $batchNumber): string
    {
        return ProductBatch::where('batch_number', $batchNumber)->firstOrFail()->id;
    }
}
