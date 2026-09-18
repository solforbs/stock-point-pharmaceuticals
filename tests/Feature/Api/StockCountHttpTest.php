<?php

namespace Tests\Feature\Api;

use App\Models\JournalEntry;
use App\Models\ProductBatch;
use App\Models\StockLedger;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 7.7 — batch-level counts: PLANNED → COUNTING → REVIEW → APPROVED
 * (COUNT_VARIANCE ledger rows + Stock variance journal) → CLOSED, with a
 * second approver above the threshold.
 */
class StockCountHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('C1', now()->addYears(2)->toDateString(), '1000', '2.0000');
        $this->receive('C2', now()->addYears(2)->toDateString(), '500', '3.0000');
        $this->grantPermissions(['stock.view', 'stock.count.enter', 'stock.count.post', 'product.cost.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_a_count_posts_its_variances_to_the_ledger_and_the_journal(): void
    {
        $count = $this->postJson('/api/inventory/counts', ['store_id' => $this->store->id])
            ->assertCreated()->assertJsonPath('status', 'PLANNED')->assertJsonCount(2, 'lines')->json();
        $lines = collect($count['lines'])->keyBy(fn ($l) => ProductBatch::whereKey($l['batch_id'])->value('batch_number'));

        $this->postJson("/api/inventory/counts/{$count['id']}/review")->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');
        $this->postJson("/api/inventory/counts/{$count['id']}/start")->assertOk()->assertJsonPath('status', 'COUNTING');

        // A variance without a reason cannot go to review.
        $this->postJson("/api/inventory/counts/{$count['id']}/lines/{$lines['C1']['id']}", ['counted_qty' => '990'])
            ->assertOk()->assertJsonPath('variance_qty', '-10.0000')->assertJsonPath('variance_value', '-20.0000');
        $this->postJson("/api/inventory/counts/{$count['id']}/review")->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');

        $this->postJson("/api/inventory/counts/{$count['id']}/lines/{$lines['C1']['id']}", ['counted_qty' => '990', 'reason_code' => 'BREAKAGE'])->assertOk();
        $this->postJson("/api/inventory/counts/{$count['id']}/lines/{$lines['C2']['id']}", ['counted_qty' => '505', 'reason_code' => 'MISCOUNT'])
            ->assertOk()->assertJsonPath('variance_value', '15.0000');

        $this->postJson("/api/inventory/counts/{$count['id']}/review")->assertOk()->assertJsonPath('status', 'REVIEW');
        $this->postJson("/api/inventory/counts/{$count['id']}/approve")->assertOk()->assertJsonPath('status', 'APPROVED');

        $rows = StockLedger::where('source_doc_type', 'stock_count')->where('source_doc_id', $count['id'])->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['COUNT_VARIANCE'], $rows->pluck('txn_type')->unique()->values()->all());
        $this->assertSame('-5.0000', (string) StockLedger::where('source_doc_id', $count['id'])->sum('qty_base'), 'net of −10 and +5');

        $stock = collect($this->getJson('/api/inventory/stock?product_id='.$this->amox->id)->json('data.0.batches'))->keyBy('batch_number');
        $this->assertSame('990.0000', $stock['C1']['on_hand']);
        $this->assertSame('505.0000', $stock['C2']['on_hand']);

        $journal = JournalEntry::where('source_doc_type', 'stock_count')->where('source_doc_id', $count['id'])->with('lines.account')->firstOrFail();
        $this->assertTrue($journal->isBalanced());
        $byRole = $journal->lines->groupBy(fn ($l) => $l->account->system_role);
        $this->assertSame('20.0000', (string) $byRole['STOCK_VARIANCE']->firstWhere('debit_amount', '>', 0)->debit_amount, 'the shortage is an expense');
        $this->assertSame('15.0000', (string) $byRole['INVENTORY']->firstWhere('debit_amount', '>', 0)->debit_amount, 'the surplus is a gain');

        $this->postJson("/api/inventory/counts/{$count['id']}/close")->assertOk()->assertJsonPath('status', 'CLOSED');
    }

    public function test_a_large_variance_needs_a_second_approver(): void
    {
        $this->receive('C3', now()->addYears(2)->toDateString(), '10000', '2.0000');
        $count = $this->postJson('/api/inventory/counts', ['store_id' => $this->store->id, 'product_ids' => [$this->amox->id]])->assertCreated()->json();
        $this->postJson("/api/inventory/counts/{$count['id']}/start")->assertOk();

        foreach ($count['lines'] as $line) {
            $batchNumber = ProductBatch::whereKey($line['batch_id'])->value('batch_number');
            $counted = $batchNumber === 'C3' ? '0' : $line['system_qty'];
            $this->postJson("/api/inventory/counts/{$count['id']}/lines/{$line['id']}", ['counted_qty' => $counted, 'reason_code' => 'THEFT'])->assertOk();
        }
        $this->postJson("/api/inventory/counts/{$count['id']}/review")->assertOk();

        $this->postJson("/api/inventory/counts/{$count['id']}/approve")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SECOND_APPROVER_REQUIRED')
            ->assertJsonPath('error.details.variance_value', '20000.0000');
        $this->assertSame(0, StockLedger::where('source_doc_type', 'stock_count')->count());

        $approver = User::create(['name' => 'Second Approver', 'username' => 'approver', 'email' => 'approver@example.test', 'password' => 'password-long-enough']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $approver->assignRole(Role::where('name', 'Test role')->firstOrFail());
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Sanctum::actingAs($approver);

        $this->postJson("/api/inventory/counts/{$count['id']}/approve")->assertOk()->assertJsonPath('status', 'APPROVED');
        $this->assertSame('-10000.0000', (string) StockLedger::where('source_doc_type', 'stock_count')->sum('qty_base'));
    }
}
