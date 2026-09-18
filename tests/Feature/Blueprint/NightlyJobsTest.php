<?php

namespace Tests\Feature\Blueprint;

use App\Models\AuditLog;
use App\Models\StockReservation;
use App\Services\Sales\SalesOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * The scheduled jobs the blueprint relies on: expiry transitions (8.3),
 * reservation expiry (7.5) and the nightly ledger reconciliation (7.1/12.4).
 */
class NightlyJobsTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_batches_past_their_expiry_date_are_moved_to_expired_and_leave_free_to_sell(): void
    {
        $expired = $this->receive('B-OLD', now()->addDays(2)->toDateString(), '400', '2.0500');
        $fresh = $this->receive('B-NEW', now()->addDays(300)->toDateString(), '400', '2.0500');
        DB::table('product_batches')->where('id', $expired->id)->update(['expiry_date' => now()->subDay()->toDateString()]);

        $this->artisan('inventory:expire-batches')->assertSuccessful();

        $this->assertSame('EXPIRED', $expired->fresh()->status);
        $this->assertSame('RELEASED', $fresh->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'BATCH_EXPIRED')->where('entity_id', $expired->id)->exists());
        $this->assertSame('400.0000', (string) $this->balance($expired->id)->qty_on_hand, 'Expired stock is still owned and valued until written off');
    }

    public function test_expired_reservations_are_released_back_to_free_stock(): void
    {
        $batch = $this->receive('B-2405', now()->addDays(348)->toDateString(), '2000', '2.0500');
        $order = app(SalesOrderService::class)->create([
            'organisation_id' => $this->org->id, 'branch_id' => $this->branch->id, 'store_id' => $this->store->id,
            'customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'idempotency_key' => (string) Str::uuid(),
            'lines' => [$this->saleLine('BOX', '2', '500.0000')],
        ]);
        app(SalesOrderService::class)->confirm($order, $this->user->id);
        $this->assertSame('400.0000', (string) $this->balance($batch->id)->qty_reserved);

        StockReservation::query()->update(['expires_at' => now()->subHour()]);
        $this->artisan('inventory:release-expired-reservations')->assertSuccessful();

        $this->assertSame('0.0000', (string) $this->balance($batch->id)->qty_reserved);
        $this->assertSame('EXPIRED', StockReservation::first()->status);
        $this->assertTrue(AuditLog::where('action', 'RESERVATION_EXPIRED')->exists());
    }

    public function test_reconciliation_passes_when_the_ledger_and_balances_agree_and_fails_when_they_drift(): void
    {
        $batch = $this->receive('B-2405', now()->addDays(348)->toDateString(), '2000', '2.0500');
        $this->checkout([$this->saleLine('BOX', '1', '500.0000')], [['method' => 'CASH', 'amount' => '500.0000']]);

        $this->artisan('inventory:reconcile-ledger')->assertSuccessful();

        // Simulate the V5 defect: someone edits the balance directly.
        DB::table('stock_balances')->where('batch_id', $batch->id)->update(['qty_on_hand' => '1799.0000']);

        $this->artisan('inventory:reconcile-ledger')->assertFailed();
    }
}
