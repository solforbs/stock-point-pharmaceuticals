<?php

namespace Tests\Feature\Api;

use App\Mail\NewTenantRequestMail;
use App\Mail\TenantInvitationMail;
use App\Mail\TenantRequestReceivedMail;
use App\Mail\TenantRequestRejectedMail;
use App\Models\ChartOfAccount;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TenantInvitation;
use App\Models\TenantRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * How an institution comes onto the platform: quote request, approval, a
 * one-time registration link, a seven-day trial, or direct creation by the
 * platform with an activation link; and what a lapsed or suspended
 * institution can still do.
 */
class SaasOnboardingTest extends TestCase
{
    use BuildsBlueprintWorld;

    private const PASSWORD = 'a-very-long-password';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->buildWorld();
        (new PermissionSeeder)->run();
        // The platform administrator also belongs to Stockpoint (tenant #1).
        $this->user->forceFill(['is_platform_admin' => true])->save();
    }

    public function test_a_quote_request_becomes_an_institution_on_a_seven_day_trial(): void
    {
        $this->postJson('/api/public/quote-requests', [
            'institution_name' => 'Lodwar Community Chemist', 'contact_name' => 'Amina Ekai',
            'email' => 'amina@lodwarchem.test', 'phone' => '0722000000', 'town' => 'Lodwar', 'branches_count' => 1,
        ])->assertCreated();
        Mail::assertSent(TenantRequestReceivedMail::class, fn ($m) => $m->hasTo('amina@lodwarchem.test'));
        Mail::assertSent(NewTenantRequestMail::class, fn ($m) => $m->hasTo($this->user->email));

        Sanctum::actingAs($this->user);
        $request = TenantRequest::sole();
        $this->postJson("/api/platform/quote-requests/{$request->id}/approve")->assertOk()->assertJsonPath('invitation.state', 'SENT');
        $token = $this->tokenMailedTo('amina@lodwarchem.test');

        $this->app['auth']->forgetGuards();
        $opened = $this->postJson('/api/public/invitations/open', ['token' => $token])->assertOk()
            ->assertJsonPath('purpose', 'REGISTER')->assertJsonPath('institution_name', 'Lodwar Community Chemist')->json();
        $this->postJson('/api/public/invitations/open', ['token' => $token])->assertStatus(410)->assertJsonPath('error.code', 'INVITATION_UNUSABLE');

        $this->postJson('/api/public/register', [
            'session_token' => $opened['session_token'], 'institution_name' => 'Lodwar Community Chemist', 'branch_name' => 'Lodwar Town',
            'admin_name' => 'Amina Ekai', 'admin_password' => self::PASSWORD, 'admin_password_confirmation' => self::PASSWORD,
        ])->assertCreated();

        $organisation = Organisation::where('name', 'Lodwar Community Chemist')->sole();
        $this->assertSame(Organisation::ACCESS_TRIAL, $organisation->accessState());
        $this->assertTrue($organisation->trial_ends_at->between(now()->addDays(6), now()->addDays(7)->addMinute()));
        $this->assertSame('REGISTERED', $request->fresh()->status);
        $this->assertGreaterThan(0, ChartOfAccount::withoutGlobalScopes()->where('organisation_id', $organisation->id)->count(), 'the books exist');
        $this->assertTrue(Role::withoutGlobalScopes()->where('organisation_id', $organisation->id)->where('name', 'Pharmacist')->exists(), 'its own roles');

        $owner = User::where('email', 'amina@lodwarchem.test')->sole();
        $this->assertSame($organisation->id, $owner->organisation_id);
        $this->postJson('/auth/login', ['email' => 'amina@lodwarchem.test', 'password' => self::PASSWORD])->assertOk();
        $this->getJson('/api/user')->assertOk()
            ->assertJsonPath('organisation.name', 'Lodwar Community Chemist')
            ->assertJsonPath('subscription.access_state', 'TRIAL');
        $this->getJson('/api/products')->assertOk()->assertJsonCount(0, 'data');

        $this->postJson('/api/public/register', ['session_token' => $opened['session_token'], 'institution_name' => 'Again', 'admin_name' => 'X', 'admin_password' => self::PASSWORD, 'admin_password_confirmation' => self::PASSWORD])
            ->assertStatus(410);
    }

    public function test_links_expire_unopened_and_forms_expire_once_opened(): void
    {
        Sanctum::actingAs($this->user);
        $request = TenantRequest::create(['institution_name' => 'Slow Pharmacy', 'contact_name' => 'Slow', 'email' => 'slow@example.test']);
        $this->postJson("/api/platform/quote-requests/{$request->id}/approve")->assertOk();
        $token = $this->tokenMailedTo('slow@example.test');

        $this->travel(TenantInvitation::LINK_DAYS + 1)->days();
        $this->postJson('/api/public/invitations/open', ['token' => $token])->assertStatus(410);
        $this->travelBack();

        $invitation = TenantInvitation::sole();
        $this->postJson("/api/platform/invitations/{$invitation->id}/resend")->assertCreated();
        $this->assertNotNull($invitation->fresh()->revoked_at, 'the earlier link is revoked');
        $fresh = $this->tokenMailedTo('slow@example.test');
        $session = $this->postJson('/api/public/invitations/open', ['token' => $fresh])->assertOk()->json('session_token');

        $this->travel(TenantInvitation::SESSION_MINUTES + 1)->minutes();
        $this->postJson('/api/public/register', ['session_token' => $session, 'institution_name' => 'Slow Pharmacy', 'admin_name' => 'Slow', 'admin_password' => self::PASSWORD, 'admin_password_confirmation' => self::PASSWORD])
            ->assertStatus(410);
        $this->assertFalse(Organisation::where('name', 'Slow Pharmacy')->exists());
    }

    public function test_a_rejected_request_is_told_why(): void
    {
        Sanctum::actingAs($this->user);
        $request = TenantRequest::create(['institution_name' => 'Not A Pharmacy', 'contact_name' => 'N', 'email' => 'n@example.test']);

        $this->postJson("/api/platform/quote-requests/{$request->id}/reject", ['reason' => 'Not a licensed pharmacy'])->assertOk()->assertJsonPath('status', 'REJECTED');
        Mail::assertSent(TenantRequestRejectedMail::class, fn ($m) => $m->hasTo('n@example.test'));
        $this->postJson("/api/platform/quote-requests/{$request->id}/approve")->assertStatus(409);
    }

    public function test_the_platform_can_create_an_institution_whose_admin_activates_by_email(): void
    {
        Sanctum::actingAs($this->user);
        $this->postJson('/api/platform/tenants', [
            'institution_name' => 'Kakuma Health Pharmacy', 'contact_email' => 'office@kakuma.test',
            'admin_name' => 'Peter Lokol', 'admin_email' => 'peter@kakuma.test',
        ])->assertCreated()->assertJsonPath('access_state', 'TRIAL')->assertJsonPath('invitation.purpose', 'ACTIVATE');

        $token = $this->tokenMailedTo('peter@kakuma.test');
        $this->app['auth']->forgetGuards();
        $this->postJson('/auth/login', ['email' => 'peter@kakuma.test', 'password' => self::PASSWORD])->assertStatus(422);

        $session = $this->postJson('/api/public/invitations/open', ['token' => $token])->assertOk()->assertJsonPath('purpose', 'ACTIVATE')->json('session_token');
        $this->postJson('/api/public/activate', ['session_token' => $session, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertOk();

        $this->postJson('/auth/login', ['email' => 'peter@kakuma.test', 'password' => self::PASSWORD])->assertOk();
        $this->getJson('/api/user')->assertOk()->assertJsonPath('organisation.name', 'Kakuma Health Pharmacy');
    }

    public function test_provisioning_works_on_a_database_seeded_before_a_permission_existed(): void
    {
        // Found on a real database: a permission added after it was seeded
        // made every registration fail when the standard roles were copied.
        Permission::where('name', 'record.delete')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($this->user);

        $this->postJson('/api/platform/tenants', ['institution_name' => 'Late Seed Pharmacy', 'contact_email' => 'l@example.test', 'admin_name' => 'L', 'admin_email' => 'l@example.test'])
            ->assertCreated();
        $this->assertTrue(Permission::where('name', 'record.delete')->exists());
    }

    public function test_the_platform_console_is_for_platform_administrators_and_spans_every_institution(): void
    {
        Sanctum::actingAs($this->user);
        $this->postJson('/api/platform/tenants', ['institution_name' => 'Other Pharmacy', 'contact_email' => 'o@example.test', 'admin_name' => 'O', 'admin_email' => 'o@example.test'])->assertCreated();

        $names = collect($this->getJson('/api/platform/tenants')->assertOk()->json('data'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing([$this->org->name, 'Other Pharmacy'], $names, 'not narrowed to the administrator\'s own institution');

        $this->user->forceFill(['is_platform_admin' => false])->save();
        Sanctum::actingAs($this->user->fresh());
        $this->getJson('/api/platform/tenants')->assertForbidden();
        $this->postJson('/api/platform/tenants', ['institution_name' => 'X', 'contact_email' => 'x@example.test', 'admin_name' => 'X', 'admin_email' => 'x@example.test'])->assertForbidden();
    }

    public function test_a_lapsed_institution_is_read_only_and_can_still_pay(): void
    {
        $this->grantPermissions(['product.view', 'product.create', 'admin.settings']);
        $this->org->forceFill(['is_complimentary' => false, 'trial_ends_at' => now()->subDay()])->save();
        Sanctum::actingAs($this->user);

        $this->getJson('/api/user')->assertOk()->assertJsonPath('subscription.access_state', 'LAPSED');
        $this->getJson('/api/products')->assertOk();
        $this->postJson('/api/products', ['code' => 'NEW', 'name' => 'New', 'base_uom_id' => $this->uoms['TAB']->id])
            ->assertStatus(402)->assertJsonPath('error.code', 'SUBSCRIPTION_REQUIRED');
        $this->getJson('/api/billing')->assertOk()->assertJsonPath('access_state', 'LAPSED')->assertJsonCount(0, 'plans');
    }

    public function test_a_suspended_institution_is_stopped_but_can_see_why(): void
    {
        $this->grantPermissions(['product.view', 'admin.settings']);
        Sanctum::actingAs($this->user);
        $this->postJson("/api/platform/tenants/{$this->org->id}/suspend", ['reason' => 'Unpaid invoice'])->assertOk()->assertJsonPath('access_state', 'SUSPENDED');

        $this->getJson('/api/products')->assertForbidden()->assertJsonPath('error.code', 'INSTITUTION_SUSPENDED');
        $this->getJson('/api/user')->assertOk()->assertJsonPath('subscription.access_state', 'SUSPENDED');
        $this->getJson('/api/billing')->assertOk()->assertJsonPath('suspension_reason', 'Unpaid invoice');

        $this->postJson("/api/platform/tenants/{$this->org->id}/reactivate")->assertOk()->assertJsonPath('access_state', 'ACTIVE');
        $this->getJson('/api/products')->assertOk();
    }

    public function test_the_platform_can_extend_a_trial(): void
    {
        $this->org->forceFill(['is_complimentary' => false, 'trial_ends_at' => now()->addDay()])->save();
        Sanctum::actingAs($this->user);

        $this->postJson("/api/platform/tenants/{$this->org->id}/extend-trial", ['days' => 14])->assertOk()->assertJsonPath('access_state', 'TRIAL');
        $this->assertTrue($this->org->fresh()->trial_ends_at->between(now()->addDays(15)->subMinute(), now()->addDays(15)->addMinute()), 'counted from the current trial end');
    }

    private function tokenMailedTo(string $email): string
    {
        $link = null;
        Mail::assertSent(TenantInvitationMail::class, function (TenantInvitationMail $mail) use ($email, &$link) {
            if ($mail->hasTo($email)) {
                $link = $mail->link;
            }

            return true;
        });
        parse_str((string) parse_url((string) $link, PHP_URL_QUERY), $query);

        return (string) $query['token'];
    }
}
