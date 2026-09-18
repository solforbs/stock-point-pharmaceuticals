<?php

namespace Tests\Feature\Api;

use App\Models\AccountsPayable;
use App\Models\JournalEntry;
use App\Models\ProductBatch;
use App\Models\StockLedger;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 11.2 supplier returns (the reverse of a GRN) and Part 11.3 waste
 * disposal (two witnesses, dedicated write-off account).
 */
class WasteAndSupplierReturnHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    private ProductBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->batch = $this->receive('W1', now()->addYears(2)->toDateString(), '1000', '2.0000');
        $this->grantPermissions(['stock.view', 'stock.adjust', 'waste.approve', 'quality.release', 'supplier.return', 'supplier.view', 'product.cost.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_waste_needs_two_witnesses_and_posts_to_the_write_off_account(): void
    {
        $this->postJson("/api/batches/{$this->batch->id}/quarantine", ['reason' => 'Water damage in the store room'])->assertOk();

        $disposal = $this->postJson('/api/waste-disposals', [
            'store_id' => $this->store->id, 'reason' => 'DAMAGED', 'disposal_method' => 'Incineration',
            'lines' => [['product_id' => $this->amox->id, 'batch_id' => $this->batch->id, 'qty_base' => '300']],
        ])->assertCreated()->assertJsonPath('status', 'DRAFT')->assertJsonPath('total_value', '600.0000')->json();

        $this->postJson("/api/waste-disposals/{$disposal['id']}/post", ['witnessed_by_1' => $this->user->id])
            ->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
        $this->postJson("/api/waste-disposals/{$disposal['id']}/post", ['witnessed_by_1' => $this->user->id, 'witnessed_by_2' => $this->user->id])
            ->assertStatus(422);

        $witness = User::create(['name' => 'Witness', 'username' => 'witness', 'email' => 'witness@example.test', 'password' => 'password-long-enough']);
        $this->postJson("/api/waste-disposals/{$disposal['id']}/post", ['witnessed_by_1' => $this->user->id, 'witnessed_by_2' => $witness->id, 'certificate_reference' => 'INC-0007'])
            ->assertOk()->assertJsonPath('status', 'POSTED')->assertJsonPath('certificate_reference', 'INC-0007');

        $row = StockLedger::where('source_doc_type', 'waste_disposal')->firstOrFail();
        $this->assertSame('DAMAGE_WRITE_OFF', $row->txn_type);
        $this->assertSame('-300.0000', (string) $row->qty_base);
        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->assertJsonPath('data.0.on_hand', '700.0000');

        $journal = JournalEntry::where('source_doc_type', 'waste_disposal')->with('lines.account')->firstOrFail();
        $this->assertTrue($journal->isBalanced());
        $roles = $journal->lines->map(fn ($l) => $l->account->system_role.':'.(string) ($l->debit_amount > 0 ? $l->debit_amount : $l->credit_amount))->all();
        $this->assertEqualsCanonicalizing(['STOCK_WRITEOFF_DAMAGE:600.0000', 'INVENTORY:600.0000'], $roles);

        $this->postJson("/api/waste-disposals/{$disposal['id']}/post", ['witnessed_by_1' => $this->user->id, 'witnessed_by_2' => $witness->id])
            ->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');
    }

    public function test_a_supplier_return_reverses_the_grn_and_raises_a_debit_note(): void
    {
        $return = $this->postJson('/api/supplier-returns', [
            'supplier_id' => $this->supplier->id, 'store_id' => $this->store->id, 'reason' => 'Short-dated on delivery',
            'lines' => [['product_id' => $this->amox->id, 'batch_id' => $this->batch->id, 'qty_base' => '400']],
        ])->assertCreated()->assertJsonPath('status', 'POSTED')->assertJsonPath('total_value', '800.0000')->json();

        $row = StockLedger::where('source_doc_type', 'supplier_return')->where('source_doc_id', $return['id'])->firstOrFail();
        $this->assertSame('PURCHASE_RETURN', $row->txn_type);
        $this->assertSame('-400.0000', (string) $row->qty_base);
        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->assertJsonPath('data.0.on_hand', '600.0000');

        $this->assertSame('-800.0000', AccountsPayable::balanceFor($this->supplier->id), 'the debit note means the supplier now owes us');
        $journal = JournalEntry::where('source_doc_type', 'supplier_return')->with('lines.account')->firstOrFail();
        $roles = $journal->lines->map(fn ($l) => $l->account->system_role.':'.($l->debit_amount > 0 ? 'DR' : 'CR'))->all();
        $this->assertEqualsCanonicalizing(['AP_CONTROL:DR', 'INVENTORY:CR'], $roles);

        $this->postJson('/api/supplier-returns', [
            'supplier_id' => $this->supplier->id, 'store_id' => $this->store->id, 'reason' => 'Too many',
            'lines' => [['product_id' => $this->amox->id, 'batch_id' => $this->batch->id, 'qty_base' => '5000']],
        ])->assertStatus(409)->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');
    }
}
