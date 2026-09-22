<?php

namespace Tests\Feature\Api;

use App\Models\AccountsReceivable;
use App\Models\CustomerCredit;
use App\Models\CustomerReturn;
use App\Models\JournalEntry;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\WasteDisposal;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 11.1 — every returned unit is dispositioned before it re-enters
 * stock; the default is quarantine; the credit note reverses at the
 * original cost, never today's WAC.
 */
class CustomerReturnHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    private ProductBatch $batch;

    private Sale $sale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->batch = $this->receive('B1', now()->addYears(2)->toDateString(), '2000', '2.0000');
        // A retail cash sale of 2 boxes (400 tablets) at 500.00 a box.
        $this->sale = $this->checkout([$this->saleLine('BOX', '2', '500.0000')], [['method' => 'CASH', 'amount' => '1000.0000']]);
        $this->grantPermissions(['sale.view', 'sale.create', 'return.create', 'return.post', 'stock.view', 'product.cost.view', 'waste.approve']);
        Sanctum::actingAs($this->user);
    }

    public function test_a_return_defaults_to_quarantine_and_credits_at_the_original_cost(): void
    {
        $return = $this->postJson('/api/customer-returns', $this->payload('200', ['refund_method' => 'CASH']))
            ->assertCreated()
            ->assertJsonPath('status', 'DRAFT')
            ->assertJsonPath('lines.0.disposition', 'QUARANTINE')
            ->assertJsonPath('lines.0.unit_price', '2.5000')
            ->assertJsonPath('lines.0.unit_cost', '2.0000')
            ->assertJsonPath('grand_total', '500.0000')
            ->json();

        $this->postJson("/api/customer-returns/{$return['id']}/post")->assertOk()->assertJsonPath('status', 'POSTED');
        $this->assertNotNull(CustomerReturn::findOrFail($return['id'])->credit_note_number);

        $rows = StockLedger::where('source_doc_type', 'customer_return')->where('source_doc_id', $return['id'])->get();
        $this->assertCount(1, $rows);
        $this->assertSame('CUSTOMER_RETURN', $rows[0]->txn_type);
        $this->assertSame('2.0000', (string) $rows[0]->unit_cost, 'back at the original batch cost');

        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)
            ->assertJsonPath('data.0.on_hand', '1800.0000')
            ->assertJsonPath('data.0.quarantined', '200.0000')
            ->assertJsonPath('data.0.free_to_sell', '1600.0000');

        $journal = JournalEntry::where('source_doc_type', 'customer_return')->where('source_doc_id', $return['id'])->with('lines.account')->firstOrFail();
        $this->assertTrue($journal->isBalanced());
        $byRole = $journal->lines->mapWithKeys(fn ($l) => [$l->account->system_role.($l->debit_amount > 0 ? ':DR' : ':CR') => (string) ($l->debit_amount > 0 ? $l->debit_amount : $l->credit_amount)]);
        $this->assertSame('500.0000', $byRole['SALES_RETURNS:DR']);
        $this->assertSame('500.0000', $byRole['CASH:CR'], 'cash refunded');
        $this->assertSame('400.0000', $byRole['INVENTORY:DR']);
        $this->assertSame('400.0000', $byRole['COGS:CR']);

        // Only a pharmacist can put it back on sale.
        $this->postJson("/api/batches/{$this->batch->id}/release")->assertStatus(403);
    }

    public function test_resaleable_is_a_pharmacists_decision(): void
    {
        $return = $this->postJson('/api/customer-returns', $this->payload('200'))->assertCreated()->json();
        $lineId = $return['lines'][0]['id'];

        $this->postJson("/api/customer-returns/{$return['id']}/lines/{$lineId}/disposition", ['disposition' => 'RESALEABLE'])->assertStatus(403);

        $this->grantPermissions(['sale.view', 'return.create', 'return.post', 'stock.view', 'quality.release']);
        $this->postJson("/api/customer-returns/{$return['id']}/lines/{$lineId}/disposition", ['disposition' => 'RESALEABLE', 'inspection_notes' => 'Sealed, in date, cold chain intact'])
            ->assertOk()->assertJsonPath('disposition', 'RESALEABLE');
        $this->postJson("/api/customer-returns/{$return['id']}/post")->assertOk();

        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)
            ->assertJsonPath('data.0.free_to_sell', '1800.0000')
            ->assertJsonPath('data.0.quarantined', '0.0000');
    }

    public function test_destroyed_goods_never_re_enter_stock_but_are_written_off(): void
    {
        $return = $this->postJson('/api/customer-returns', $this->payload('200', ['refund_method' => 'CASH']))->assertCreated()->json();
        $this->postJson("/api/customer-returns/{$return['id']}/lines/{$return['lines'][0]['id']}/disposition", ['disposition' => 'DESTROY', 'inspection_notes' => 'Blister crushed'])->assertOk();

        // Destruction needs a second signatory who is not the poster.
        $this->postJson("/api/customer-returns/{$return['id']}/post")->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
        $witness = User::create(['name' => 'Witness', 'username' => 'witness', 'email' => 'witness@example.test', 'password' => 'password-long-enough']);
        $this->postJson("/api/customer-returns/{$return['id']}/post", ['witness_user_id' => $witness->id])->assertOk()->assertJsonPath('status', 'POSTED');

        $this->assertSame(0, StockLedger::where('source_doc_type', 'customer_return')->count(), 'no ledger IN for destroyed goods');
        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->assertJsonPath('data.0.on_hand', '1600.0000');

        $disposal = WasteDisposal::firstOrFail();
        $this->assertSame('POSTED', $disposal->status);
        $this->assertFalse($disposal->stock_effect);
        $this->assertSame('400.0000', (string) $disposal->total_value);

        $writeOff = JournalEntry::where('source_doc_type', 'waste_disposal')->with('lines.account')->firstOrFail();
        $roles = $writeOff->lines->map(fn ($l) => $l->account->system_role.':'.($l->debit_amount > 0 ? 'DR' : 'CR'))->all();
        $this->assertEqualsCanonicalizing(['STOCK_WRITEOFF_DAMAGE:DR', 'COGS:CR'], $roles, 'the cost moves out of COGS into the write-off account');
    }

    public function test_more_than_was_issued_cannot_come_back(): void
    {
        $this->postJson('/api/customer-returns', $this->payload('500'))->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');

        $first = $this->postJson('/api/customer-returns', $this->payload('300'))->assertCreated()->json();
        $this->postJson("/api/customer-returns/{$first['id']}/post")->assertOk();
        $this->postJson('/api/customer-returns', $this->payload('200'))->assertStatus(422);
    }

    public function test_a_credit_customers_return_reduces_what_they_owe(): void
    {
        $creditSale = $this->checkout([$this->saleLine('BOX', '2', '500.0000')], [], ['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id]);
        $this->assertSame('1000.0000', (string) CustomerCredit::where('customer_id', $this->customer->id)->value('current_balance'));

        $return = $this->postJson('/api/customer-returns', [
            'sale_id' => $creditSale->id, 'store_id' => $this->store->id, 'reason' => 'Wrong strength delivered',
            'lines' => [['sale_line_id' => $creditSale->lines()->first()->id, 'batch_id' => $this->batch->id, 'qty_base' => '200']],
        ])->assertCreated()->assertJsonPath('refund_method', 'CUSTOMER_ACCOUNT')->json();
        $this->postJson("/api/customer-returns/{$return['id']}/post")->assertOk();

        $this->assertSame('500.0000', (string) CustomerCredit::where('customer_id', $this->customer->id)->value('current_balance'));
        $this->assertSame(1, AccountsReceivable::where('customer_id', $this->customer->id)->where('txn_type', 'CREDIT_NOTE')->count());
        $this->getJson("/api/customers/{$this->customer->id}")->assertJsonPath('available_credit', '999500.0000');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function test_each_line_says_why_it_came_back_and_the_detail_names_the_exact_item(): void
    {
        $this->amox->update(['description' => 'Hard gelatin capsule, red/yellow']);
        $line = ['sale_line_id' => $this->sale->lines()->first()->id, 'batch_id' => $this->batch->id, 'qty_base' => '200'];

        $return = $this->postJson('/api/customer-returns', ['lines' => [['return_reason' => 'DAMAGED', 'remarks' => 'Box crushed in transit to Kakuma'] + $line]] + $this->payload('200'))->assertCreated()->json();

        $this->getJson("/api/customer-returns/{$return['id']}")->assertOk()
            ->assertJsonPath('lines.0.return_reason', 'DAMAGED')
            ->assertJsonPath('lines.0.remarks', 'Box crushed in transit to Kakuma')
            ->assertJsonPath('lines.0.product.strength', '500mg')
            ->assertJsonPath('lines.0.product.generic_name', 'Amoxicillin')
            ->assertJsonPath('lines.0.product.description', 'Hard gelatin capsule, red/yellow')
            ->assertJsonPath('lines.0.product.base_uom.code', 'TAB')
            ->assertJsonPath('lines.0.batch.batch_number', 'B1')
            ->assertJsonPath('lines.0.batch.expiry_date', fn (string $date) => str_starts_with($date, $this->batch->expiry_date->toDateString()));

        $this->postJson('/api/customer-returns', ['lines' => [['qty_base' => '10', 'return_reason' => 'OTHER'] + $line]] + $this->payload('10'))
            ->assertStatus(422)->assertJsonValidationErrors('lines.0.remarks');
        $this->postJson('/api/customer-returns', ['lines' => [['qty_base' => '10', 'return_reason' => 'SLOW_MOVING'] + $line]] + $this->payload('10'))
            ->assertStatus(422)->assertJsonValidationErrors('lines.0.return_reason');
    }

    private function payload(string $qty, array $extra = []): array
    {
        return [
            'sale_id' => $this->sale->id,
            'store_id' => $this->store->id,
            'reason' => 'Customer changed their mind',
            'lines' => [['sale_line_id' => $this->sale->lines()->first()->id, 'batch_id' => $this->batch->id, 'qty_base' => $qty]],
        ] + $extra;
    }
}
