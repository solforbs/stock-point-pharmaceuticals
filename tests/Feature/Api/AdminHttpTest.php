<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Parts 17–19 — the administration surface: users with per-branch roles,
 * role permission sets, branches and stores, versioned settings, number
 * sequences and the audit log.
 */
class AdminHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        (new PermissionSeeder)->run();
        (new RoleSeeder)->run();
        $this->grantPermissions(['admin.users', 'admin.settings', 'audit.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_users_are_created_with_branch_role_assignments_and_can_be_updated_and_unlocked(): void
    {
        $created = $this->postJson('/api/admin/users', [
            'name' => 'Grace Storekeeper', 'username' => 'grace', 'email' => 'grace@example.test', 'password' => 'a-temporary-password',
            'assignments' => [['branch_id' => $this->branch->id, 'roles' => ['Storekeeper', 'Cashier']]],
        ])->assertCreated()
            ->assertJsonPath('must_change_password', true)
            ->assertJsonPath('assignments.0.branch_code', 'LDW')
            ->assertJsonPath('assignments.0.role', 'Cashier')
            ->assertJsonPath('assignments.1.role', 'Storekeeper')
            ->assertJsonMissingPath('password')
            ->json();

        $this->getJson('/api/admin/users?q=grace')->assertOk()->assertJsonPath('data.0.id', $created['id'])->assertJsonCount(2, 'data.0.assignments');

        // The new user can act in the branch with exactly those roles' permissions.
        Sanctum::actingAs(User::find($created['id']));
        $this->getJson('/api/user')->assertOk()->assertJsonPath('active_branch.code', 'LDW')->assertJsonFragment(['permissions' => User::find($created['id'])->getAllPermissions()->pluck('name')->all()]);
        $this->postJson('/api/admin/users', ['name' => 'x', 'username' => 'x', 'email' => 'x@example.test', 'password' => 'a-temporary-password'])->assertStatus(403);

        Sanctum::actingAs($this->user);
        User::whereKey($created['id'])->update(['failed_login_attempts' => 5, 'locked_until' => now()->addHour()]);
        $this->postJson("/api/admin/users/{$created['id']}/unlock")->assertOk()->assertJsonPath('locked_until', null)->assertJsonPath('failed_login_attempts', 0);

        $this->patchJson("/api/admin/users/{$created['id']}", ['is_active' => false, 'assignments' => [['branch_id' => $this->branch->id, 'roles' => ['Cashier']]]])
            ->assertOk()->assertJsonPath('is_active', false)->assertJsonCount(1, 'assignments');
        $this->assertSame(1, AuditLog::where('action', 'USER_UPDATED')->where('entity_id', (string) $created['id'])->count());

        $this->patchJson("/api/admin/users/{$this->user->id}", ['is_active' => false])->assertStatus(422)->assertJsonPath('error.code', 'SELF_DEACTIVATION');
    }

    public function test_role_permission_sets_can_be_read_and_replaced(): void
    {
        $roles = $this->getJson('/api/admin/roles')->assertOk()->json();
        $cashier = collect($roles)->firstWhere('name', 'Cashier');
        $this->assertContains('sale.create', $cashier['permissions']);

        $this->getJson('/api/admin/permissions')->assertOk()->assertJsonFragment(['group' => 'sale']);

        $this->patchJson("/api/admin/roles/{$cashier['id']}", ['permissions' => ['sale.view', 'product.view']])->assertOk()->assertJsonPath('permissions', ['product.view', 'sale.view']);
        $this->patchJson("/api/admin/roles/{$cashier['id']}", ['permissions' => ['no.such.permission']])->assertStatus(422);
        $this->assertSame(1, AuditLog::where('action', 'ROLE_PERMISSIONS_CHANGED')->count());

        $this->postJson('/api/admin/roles', ['name' => 'Dispatch Clerk', 'permissions' => ['warehouse.dispatch']])->assertCreated()->assertJsonPath('permissions.0', 'warehouse.dispatch');
    }

    public function test_branches_and_stores_are_managed_and_settings_are_versioned(): void
    {
        $this->getJson('/api/admin/branches')->assertOk()->assertJsonPath('0.code', 'LDW')->assertJsonPath('0.stores.0.code', 'MAIN');
        $this->patchJson("/api/admin/branches/{$this->branch->id}", ['dispensing_enabled' => true])->assertOk()->assertJsonPath('dispensing_enabled', true);
        $this->postJson("/api/admin/branches/{$this->branch->id}/stores", ['code' => 'COLD', 'name' => 'Cold Room', 'store_type' => 'COLD'])->assertCreated()->assertJsonPath('is_sellable', false);
        $this->postJson("/api/admin/branches/{$this->branch->id}/stores", ['code' => 'COLD', 'name' => 'Dup', 'store_type' => 'COLD'])->assertStatus(422);

        $settings = $this->getJson('/api/admin/settings')->assertOk()->json('data');
        $ttl = collect($settings)->firstWhere('key', 'quote_ttl_minutes');
        $this->assertSame(15, $ttl['value']);
        $this->assertSame('default', $ttl['source']);

        $this->putJson('/api/admin/settings', ['scope' => 'pricing', 'key' => 'quote_ttl_minutes', 'value' => 30])->assertOk();
        $this->putJson('/api/admin/settings', ['scope' => 'pricing', 'key' => 'quote_ttl_minutes', 'value' => 45, 'branch_scoped' => true])->assertOk();

        $settings = $this->getJson('/api/admin/settings')->assertOk()->json('data');
        $ttl = collect($settings)->firstWhere('key', 'quote_ttl_minutes');
        $this->assertSame(45, $ttl['value']);
        $this->assertSame('branch', $ttl['source']);
        $this->assertSame(30, $ttl['organisation_value']);
        $this->assertSame(45, Setting::resolve($this->org->id, $this->branch->id, 'pricing', 'quote_ttl_minutes'));
        $this->assertSame(2, AuditLog::where('action', 'SETTING_CHANGED')->count());

        $this->getJson('/api/admin/number-sequences')->assertOk();
    }

    public function test_the_audit_log_is_filterable(): void
    {
        $this->putJson('/api/admin/settings', ['scope' => 'pricing', 'key' => 'round_to', 'value' => '0.05'])->assertOk();

        $this->getJson('/api/admin/audit-log?action=SETTING_CHANGED')->assertOk()
            ->assertJsonPath('data.0.action', 'SETTING_CHANGED')
            ->assertJsonPath('data.0.reference', 'pricing.round_to')
            ->assertJsonFragment(['actions' => AuditLog::distinct()->orderBy('action')->pluck('action')->all()]);
        $this->getJson('/api/admin/audit-log?q=nothing-matches-this')->assertOk()->assertJsonCount(0, 'data');
    }
}
