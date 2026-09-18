<?php

namespace Tests\Feature\Api;

use App\Models\ProductBatch;
use App\Models\RecallCustomer;
use App\Models\Sale;
use App\Models\StockLedger;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 8.4 — where is the stock, who received it, and what percentage came
 * back. Answered from posted data in one request.
 */
class RecallHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    private ProductBatch $batch;

    private Sale $hospitalSale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->batch = $this->receive('R1', now()->addYears(2)->toDateString(), '3000', '2.0000');
        $this->hospitalSale = $this->checkout([$this->saleLine('BOX', '2', '500.0000')], [], ['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id]);
        $this->checkout([$this->saleLine('BOX', '1', '500.0000')], [['method' => 'CASH', 'amount' => '500.0000']]);
        $this->grantPermissions([
            'recall.initiate', 'stock.view', 'quality.release', 'return.create', 'return.post', 'waste.approve', 'stock.adjust',
            'sale.view', 'sale.create', 'product.cost.view',
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_a_recall_traces_blocks_recovers_and_measures_effectiveness(): void
    {
        $recall = $this->postJson('/api/recalls', ['source' => 'PPB', 'external_reference' => 'PPB/RC/2026/17', 'reason' => 'Out-of-specification dissolution', 'batch_ids' => [$this->batch->id]])
            ->assertCreated()
            ->assertJsonPath('recall.status', 'SCOPED')
            ->assertJsonPath('batches.0.on_hand_at_scope', '2400.0000')
            ->assertJsonPath('batches.0.distributed_qty', '600.0000')
            ->assertJsonPath('distributed_qty', '600.0000')
            ->assertJsonCount(2, 'customers')
            ->json();
        $customers = collect($recall['customers'])->keyBy('customer_name_snapshot');
        $this->assertSame('400.0000', $customers['Turkana County Referral Hospital']['qty_distributed']);
        $this->assertSame('200.0000', $customers['Walk-in customers']['qty_distributed']);

        // Block: the batch is RECALLED everywhere and the POS cannot touch it.
        $this->postJson("/api/recalls/{$recall['recall']['id']}/block")->assertOk()->assertJsonPath('recall.status', 'BLOCKED');
        $this->assertSame('RECALLED', $this->batch->fresh()->status);
        $this->getJson('/api/inventory/stock?product_id='.$this->amox->id)
            ->assertJsonPath('data.0.recalled', '2400.0000')
            ->assertJsonPath('data.0.free_to_sell', '0.0000');
        $quote = $this->postJson('/api/pricing/quote', ['sale_mode' => 'RETAIL', 'store_id' => $this->store->id, 'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => '1']]])->assertOk()->json();
        $this->postJson('/api/sales/checkout', ['quote_id' => $quote['quote_id'], 'store_id' => $this->store->id, 'payments' => [['method' => 'CASH', 'amount' => $quote['totals']['grand_total']]]], ['Idempotency-Key' => 'rc-1'])
            ->assertStatus(409)->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');

        // Notify, then the hospital sends its 400 back against the recall.
        $this->postJson("/api/recalls/{$recall['recall']['id']}/notify", ['notification_reference' => 'LETTERS-2026-09-17'])
            ->assertOk()->assertJsonPath('recall.status', 'NOTIFIED');
        $this->assertNotNull(RecallCustomer::where('recall_id', $recall['recall']['id'])->first()->notified_at);

        $return = $this->postJson('/api/customer-returns', [
            'sale_id' => $this->hospitalSale->id, 'store_id' => $this->store->id, 'reason' => 'Recall PPB/RC/2026/17', 'recall_id' => $recall['recall']['id'],
            'lines' => [['sale_line_id' => $this->hospitalSale->lines()->first()->id, 'batch_id' => $this->batch->id, 'qty_base' => '400']],
        ])->assertCreated()->json();
        $this->postJson("/api/customer-returns/{$return['id']}/post")->assertOk();

        $trace = $this->getJson("/api/recalls/{$recall['recall']['id']}")->assertOk()
            ->assertJsonPath('recall.status', 'RECOVERING')
            ->assertJsonPath('recovered_qty', '400.0000')
            ->assertJsonPath('batches.0.outstanding_qty', '200.0000')
            ->assertJsonPath('batches.0.on_hand_now', '2800.0000')
            ->json();
        $this->assertSame('66.67', $trace['effectiveness_pct'], '400 recovered ÷ 600 distributed');

        $this->postJson("/api/recalls/{$recall['recall']['id']}/reconcile")->assertOk()->assertJsonPath('recall.status', 'RECONCILED')->assertJsonPath('recall.effectiveness_pct', '66.67');
        $this->postJson("/api/recalls/{$recall['recall']['id']}/disposition", ['disposition' => 'DESTROY'])->assertOk()->assertJsonPath('recall.status', 'DISPOSITIONED');

        // Witnessed destruction of everything on hand, cited to the recall.
        $disposal = $this->postJson('/api/waste-disposals', [
            'store_id' => $this->store->id, 'reason' => 'RECALLED', 'recall_id' => $recall['recall']['id'],
            'disposal_method' => 'Incineration', 'disposal_contractor' => 'Lodwar Medical Waste Ltd', 'certificate_reference' => 'INC-2026-0912',
            'lines' => [['product_id' => $this->amox->id, 'batch_id' => $this->batch->id, 'qty_base' => '2800']],
        ])->assertCreated()->json();
        $witness = User::create(['name' => 'Witness', 'username' => 'witness', 'email' => 'witness@example.test', 'password' => 'password-long-enough']);
        $this->postJson("/api/waste-disposals/{$disposal['id']}/post", ['witnessed_by_1' => $this->user->id, 'witnessed_by_2' => $witness->id])
            ->assertOk()->assertJsonPath('status', 'POSTED');

        $this->assertSame('DISPOSED', $this->batch->fresh()->status);
        $this->assertSame('0.0000', (string) StockLedger::where('batch_id', $this->batch->id)->sum('qty_base'));

        $this->postJson("/api/recalls/{$recall['recall']['id']}/close")->assertOk()
            ->assertJsonPath('recall.status', 'CLOSED')
            ->assertJsonPath('disposed_qty', '2800.0000')
            ->assertJsonPath('effectiveness_pct', '66.67');
    }

    public function test_recall_actions_need_the_permission_and_follow_the_state_machine(): void
    {
        $recall = $this->postJson('/api/recalls', ['source' => 'INTERNAL', 'reason' => 'Quality finding on stability sample', 'batch_ids' => [$this->batch->id]])->assertCreated()->json();
        $this->postJson("/api/recalls/{$recall['recall']['id']}/close")->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');

        $this->grantPermissions(['stock.view']);
        $this->getJson("/api/recalls/{$recall['recall']['id']}")->assertOk();
        $this->postJson("/api/recalls/{$recall['recall']['id']}/block")->assertStatus(403);
    }
}
