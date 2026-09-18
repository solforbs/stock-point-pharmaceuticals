<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 18.3 — the first administrator comes from the console, holds the
 * System Administrator role per branch, and only gets every permission
 * when that is asked for explicitly.
 */
class CreateAdminUserTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        (new PermissionSeeder)->run();
        (new RoleSeeder)->run();
    }

    public function test_it_creates_a_system_administrator_who_can_sign_in_but_cannot_post(): void
    {
        $this->artisan('user:create-admin', ['email' => 'Admin@Stockpoint.test', '--name' => 'Amina Admin', '--password' => 'a-long-enough-password'])
            ->expectsOutputToContain('Administrator admin@stockpoint.test created.')
            ->assertSuccessful();

        $admin = User::where('email', 'admin@stockpoint.test')->firstOrFail();
        $this->assertSame('admin', $admin->username);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('a-long-enough-password', $admin->password), 'stored as a hash, never in the clear');
        $this->assertSame(1, AuditLog::where('action', 'USER_CREATED')->where('entity_id', (string) $admin->id)->count());

        $this->postJson('/auth/login', ['email' => 'admin@stockpoint.test', 'password' => 'a-long-enough-password'])->assertOk();
        $this->getJson('/api/user')->assertOk()
            ->assertJsonPath('active_branch.code', 'LDW')
            ->assertJsonPath('permissions', ['admin.users', 'admin.settings', 'audit.view']);

        // No clinical or financial posting rights.
        $this->postJson('/api/pricing/quote', ['sale_mode' => 'RETAIL', 'store_id' => $this->store->id, 'lines' => []])->assertStatus(403);
        $this->getJson('/api/finance/trial-balance')->assertStatus(403);
    }

    public function test_full_access_grants_every_permission_in_the_branch(): void
    {
        $this->artisan('user:create-admin', ['email' => 'owner@stockpoint.test', '--password' => 'a-long-enough-password', '--branch' => ['LDW'], '--full-access' => true])
            ->expectsOutputToContain('Super Administrator carries every permission')
            ->assertSuccessful();

        $this->postJson('/auth/login', ['email' => 'owner@stockpoint.test', 'password' => 'a-long-enough-password'])->assertOk();
        $permissions = $this->getJson('/api/user')->assertOk()->json('permissions');
        $this->assertEqualsCanonicalizing(Permission::pluck('name')->all(), $permissions);

        $this->getJson('/api/reports')->assertOk();
        $this->getJson('/api/finance/trial-balance')->assertOk();
    }

    public function test_a_generated_password_is_shown_once_and_works(): void
    {
        Artisan::call('user:create-admin', ['email' => 'generated@stockpoint.test']);
        $output = Artisan::output();

        $this->assertMatchesRegularExpression('/Password\s*\|\s*([A-Za-z0-9]{20})\s*\|/', $output);
        preg_match('/Password\s*\|\s*([A-Za-z0-9]{20})\s*\|/', $output, $m);

        $this->postJson('/auth/login', ['email' => 'generated@stockpoint.test', 'password' => $m[1]])->assertOk();
    }

    public function test_it_refuses_duplicates_short_passwords_and_unknown_branches(): void
    {
        $this->artisan('user:create-admin', ['email' => 'cashier@example.test', '--password' => 'a-long-enough-password'])->assertFailed();
        $this->artisan('user:create-admin', ['email' => 'short@stockpoint.test', '--password' => 'short'])->assertFailed();
        $this->artisan('user:create-admin', ['email' => 'lost@stockpoint.test', '--password' => 'a-long-enough-password', '--branch' => ['NOPE']])
            ->expectsOutputToContain('Unknown or inactive branch code: NOPE')->assertFailed();

        $this->assertSame(1, User::count(), 'only the fixture user exists');
    }
}
