<?php

namespace Tests\Feature\Api;

use App\Mail\AlertDigestMail;
use App\Models\Alert;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\Alerts\AlertScanner;
use App\Services\Finance\ReceiptService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 17 — standing alerts. The scan is a recompute of what is true now:
 * money falling due and stock running out of shelf life appear, and both
 * disappear by themselves once paid or gone.
 */
class AlertsHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    private function scan(): array
    {
        return app(AlertScanner::class)->scan($this->branch);
    }

    /** A credit sale that is past its terms, made 40 days ago on 30-day terms. */
    private function overdueInvoice(): Sale
    {
        $this->receive('B-PAY', now()->addDays(400)->toDateString(), '1000', '2.0000');
        $sale = $this->checkout([$this->saleLine('TAB', '100', '5.0000')], [], ['customer_id' => $this->customer->id]);
        $sale->forceFill(['posted_at' => now()->subDays(40)])->save();

        return $sale;
    }

    public function test_an_overdue_invoice_and_expiring_stock_become_alerts_that_resolve_themselves(): void
    {
        $sale = $this->overdueInvoice();
        $this->receive('B-SOON', now()->addDays(20)->toDateString(), '50', '2.0000');

        $this->assertSame(['opened' => 2, 'refreshed' => 0, 'resolved' => 0], $this->scan());

        $receivable = Alert::where('category', 'RECEIVABLE')->firstOrFail();
        $this->assertSame('INVOICE_OVERDUE', $receivable->type);
        $this->assertSame('CRITICAL', $receivable->severity);
        $this->assertSame('500.0000', (string) $receivable->amount);
        $this->assertSame(-10, $receivable->days_to_due, '40 days old on 30-day terms is 10 days late');
        $this->assertStringContainsString('10 days overdue', $receivable->title);

        $expiry = Alert::where('category', 'EXPIRY')->firstOrFail();
        $this->assertSame('BATCH_EXPIRING', $expiry->type);
        $this->assertSame('WARNING', $expiry->severity, 'inside 30 days is a warning, not just a note');
        $this->assertStringContainsString('B-SOON', (string) $expiry->detail);
        $this->assertStringContainsString('50 on hand in MAIN', (string) $expiry->detail);

        // Re-scanning is an update, never a second copy of the same alert.
        $this->assertSame(['opened' => 0, 'refreshed' => 2, 'resolved' => 0], $this->scan());
        $this->assertSame(2, Alert::count());

        // Settle the invoice and the alert closes itself on the next scan.
        app(ReceiptService::class)->record([
            'organisation_id' => $this->org->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'method' => 'CASH',
            'amount' => '500.0000',
            'received_by' => $this->user->id,
            'allocations' => [['sale_id' => $sale->id, 'amount' => '500.0000']],
        ]);
        $this->assertSame(1, $this->scan()['resolved']);
        $this->assertNotNull($receivable->fresh()->resolved_at);
        $this->assertNull($expiry->fresh()->resolved_at, 'the stock is still short-dated');
    }

    public function test_stock_past_its_expiry_is_critical_and_far_dated_stock_raises_nothing(): void
    {
        $this->receive('B-OLD', now()->subDay()->toDateString(), '10', '2.0000');
        $this->receive('B-FAR', now()->addDays(200)->toDateString(), '10', '2.0000');

        $this->scan();

        $this->assertSame(1, Alert::where('category', 'EXPIRY')->count(), 'stock outside 90 days is not an alert');
        $alert = Alert::where('category', 'EXPIRY')->firstOrFail();
        $this->assertSame('BATCH_EXPIRED', $alert->type);
        $this->assertSame('CRITICAL', $alert->severity);
        $this->assertSame('stock.view', $alert->permission);
    }

    public function test_the_bell_and_the_list_show_only_what_the_user_may_see(): void
    {
        $this->overdueInvoice();
        $this->receive('B-SOON', now()->addDays(20)->toDateString(), '50', '2.0000');
        $this->scan();

        // A storekeeper sees the shelf-life alert and not what the books owe.
        $this->grantPermissions(['stock.view']);
        Sanctum::actingAs($this->user);

        $this->getJson('/api/alerts')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.category', 'EXPIRY');
        $this->getJson('/api/alerts/summary')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('critical', 0)
            ->assertJsonPath('by_category.RECEIVABLE.total', 0)
            ->assertJsonPath('by_category.EXPIRY.warning', 1);

        // The credit controller sees the money and not the shelf.
        $this->revokeAllRoles();
        $this->grantPermissions(['finance.ar.view'], 'Credit control');
        $this->getJson('/api/alerts')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.category', 'RECEIVABLE');
        $this->getJson('/api/alerts/summary')->assertOk()->assertJsonPath('critical', 1);
    }

    public function test_acknowledging_clears_the_bell_and_an_escalation_raises_it_again(): void
    {
        $this->receive('B-SOON', now()->addDays(20)->toDateString(), '50', '2.0000');
        $this->scan();

        $this->grantPermissions(['stock.view', 'admin.settings']);
        Sanctum::actingAs($this->user);

        $alert = Alert::firstOrFail();
        $this->postJson("/api/alerts/{$alert->id}/acknowledge")->assertOk()->assertJsonPath('acknowledged_by', $this->user->id);
        $this->getJson('/api/alerts/summary')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/alerts')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/alerts?include_acknowledged=1')->assertOk()->assertJsonCount(1, 'data');

        // The batch expires; a worse alert must not stay silently acknowledged.
        $this->travelTo(now()->addDays(25));
        $this->postJson('/api/alerts/scan')->assertOk()->assertJsonPath('refreshed', 1);

        $alert->refresh();
        $this->assertSame('CRITICAL', $alert->severity);
        $this->assertNull($alert->acknowledged_at);
        $this->getJson('/api/alerts/summary')->assertOk()->assertJsonPath('critical', 1);
    }

    public function test_alerts_are_read_only_to_users_without_any_alert_permission(): void
    {
        $this->grantPermissions(['sale.create']);
        Sanctum::actingAs($this->user);

        $this->getJson('/api/alerts')->assertStatus(403);
        $this->getJson('/api/alerts/summary')->assertStatus(403);
        $this->postJson('/api/alerts/scan')->assertStatus(403);
    }

    public function test_the_morning_digest_mails_each_person_only_the_alerts_they_may_see(): void
    {
        Mail::fake();

        $storekeeper = $this->user;
        $controller = User::create([
            'name' => 'Credit Controller', 'username' => 'credit', 'email' => 'credit@stockpoint.test',
            'password' => Hash::make('a-long-enough-password'), 'is_active' => true,
        ]);

        // The credit controller hears about money, the storekeeper about stock.
        $creditRole = $this->grantPermissions(['finance.ar.view'], 'Credit control');
        $this->revokeAllRoles();
        $this->grantPermissions(['stock.view'], 'Storekeeper');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $controller->assignRole($creditRole);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->overdueInvoice();
        $this->receive('B-SOON', now()->addDays(20)->toDateString(), '50', '2.0000');

        $this->artisan('alerts:scan')->assertSuccessful();

        // render() is called deliberately: a faked mail is never rendered, so
        // without this a broken template would only show up in production.
        Mail::assertSent(AlertDigestMail::class, function (AlertDigestMail $mail) use ($storekeeper) {
            if (! $mail->hasTo($storekeeper->email)) {
                return false;
            }
            $body = $mail->render();
            $this->assertStringContainsString('Stock running out of shelf life', $body);
            $this->assertStringContainsString('B-SOON', $body);
            $this->assertStringNotContainsString('Customer invoices', $body, 'the storekeeper is never told what the books owe');

            return $mail->alerts->count() === 1 && $mail->alerts->first()->category === 'EXPIRY';
        });

        Mail::assertSent(AlertDigestMail::class, fn (AlertDigestMail $mail) => $mail->hasTo($controller->email)
            && $mail->alerts->count() === 1
            && $mail->alerts->first()->category === 'RECEIVABLE'
            && str_contains($mail->envelope()->subject, '1 critical'));

        Mail::assertSentCount(2);
    }

    public function test_the_digest_is_silent_when_nothing_is_wrong_and_can_be_switched_off(): void
    {
        Mail::fake();
        $this->grantPermissions(['stock.view']);

        // Nothing due, nothing short-dated: no post lands on anyone's desk.
        $this->artisan('alerts:scan')->assertSuccessful();
        Mail::assertNothingSent();

        $this->receive('B-SOON', now()->addDays(20)->toDateString(), '50', '2.0000');
        Setting::create([
            'organisation_id' => $this->org->id, 'branch_id' => $this->branch->id,
            'scope' => 'alerts', 'key' => 'email_digest_enabled', 'value_json' => false,
            'effective_from' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('alerts:scan')->assertSuccessful();
        Mail::assertNothingSent();
        $this->assertSame(1, Alert::open()->count(), 'the alert centre still works with the digest switched off');

        // With the setting removed the digest goes out again.
        Setting::query()->delete();
        $this->artisan('alerts:scan')->assertSuccessful();
        Mail::assertSent(AlertDigestMail::class, 1);
    }
}
