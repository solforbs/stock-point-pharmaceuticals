<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\JournalEntry;
use App\Models\NumberSequence;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Services\Admin\BackupService;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Clearing a demonstration: everything recorded since a moment goes, and
 * stock, balances and numbering are back where they stood before it.
 */
class TransactionPurgeTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['sale.create', 'sale.view', 'stock.view']);
        $this->user->forceFill(['is_platform_admin' => true])->save();
        $this->app->instance(BackupService::class, new class extends BackupService
        {
            protected function runDump(\Closure $write): void
            {
                $write('-- fake dump');
            }
        });
        Sanctum::actingAs($this->user);
    }

    public function test_a_demo_is_cleared_and_everything_before_it_is_untouched(): void
    {
        // Real opening stock, before the demo.
        $opening = $this->receive('B1', now()->addYears(2)->toDateString(), '2000', '2.0000');
        $journalsBefore = JournalEntry::count();

        $this->travel(1)->hours();
        $since = now()->toDateTimeString();
        $this->travel(1)->minutes();

        // The demo: a second receipt and a sale out of the opening stock.
        $this->receive('DEMO-1', now()->addYears(2)->toDateString(), '500', '2.5000');
        $quote = $this->postJson('/api/pricing/quote', [
            'sale_mode' => 'RETAIL', 'store_id' => $this->store->id,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => '2']],
        ])->assertOk()->json();
        $this->postJson('/api/sales/checkout', [
            'quote_id' => $quote['quote_id'], 'store_id' => $this->store->id,
            'payments' => [['method' => 'CASH', 'amount' => $quote['totals']['grand_total']]],
        ], ['Idempotency-Key' => 'demo-sale'])->assertCreated();

        $this->assertSame(2, GoodsReceipt::count());
        $this->assertSame(1, Sale::count());

        $preview = collect($this->getJson("/api/platform/tenants/{$this->org->id}/transactions-since?since=".urlencode($since))
            ->assertOk()->json('documents'))->pluck('count', 'table');
        $this->assertSame(1, $preview['goods_receipts']);
        $this->assertSame(1, $preview['sales']);

        $this->postJson("/api/platform/tenants/{$this->org->id}/clear-transactions", ['since' => $since, 'confirm_name' => 'wrong', 'reason' => 'Demo'])
            ->assertStatus(422)->assertJsonValidationErrors('confirm_name');
        $this->assertSame(1, Sale::count(), 'a wrong confirmation deletes nothing');

        $this->postJson("/api/platform/tenants/{$this->org->id}/clear-transactions", ['since' => $since, 'confirm_name' => $this->org->name, 'reason' => 'Online demo'])
            ->assertOk()
            ->assertJsonPath('deleted.goods_receipts', 1)
            ->assertJsonPath('deleted.sales', 1);

        $this->assertSame(1, GoodsReceipt::count(), 'the opening receipt stays');
        $this->assertSame(0, Sale::count());
        $this->assertFalse(ProductBatch::where('batch_number', 'DEMO-1')->exists());
        $this->assertSame($journalsBefore, JournalEntry::count());
        $this->assertSame('2000.0000', (string) $this->balance($opening->id)->qty_on_hand, 'the stock sold in the demo is back');
        $this->assertSame('2000.0000', $this->ledgerSum());
        $this->assertSame(1, (int) NumberSequence::where('prefix', 'GRN')->value('current_value'), 'the next real GRN follows the opening one');
        $this->assertSame(0, (int) NumberSequence::where('prefix', 'INV')->value('current_value'));
        $this->assertTrue(AuditLog::where('action', 'TRANSACTIONS_PURGED')->exists());
    }

    public function test_only_platform_administrators_can_clear_transactions(): void
    {
        $this->user->forceFill(['is_platform_admin' => false])->save();

        $this->postJson("/api/platform/tenants/{$this->org->id}/clear-transactions", ['since' => now()->toDateTimeString(), 'confirm_name' => $this->org->name, 'reason' => 'x'])
            ->assertForbidden();
    }
}
