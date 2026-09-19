<?php

namespace Tests\Feature\Api;

use App\Console\Commands\CreateAdminUser;
use App\Models\AuditLog;
use App\Services\Admin\DeploymentFailedException;
use App\Services\Admin\DeploymentService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 17 — the deployment screen. It reports the running version and starts
 * one fixed script; it never takes a command from the browser.
 */
class DeploymentHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        File::deleteDirectory(storage_path('app/private/deployments'));
    }

    private function actAsDeployer(): void
    {
        $this->grantPermissions(['deploy.run']);
        Sanctum::actingAs($this->user);
    }

    public function test_the_status_reports_the_running_commit_and_the_deploy_switch(): void
    {
        $this->actAsDeployer();
        config(['deployment.enabled' => false]);

        $response = $this->getJson('/api/admin/deployments')->assertOk()
            ->assertJsonPath('branch', config('deployment.branch'))
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('deploy.status', 'never')
            ->json();

        // This repository is a git checkout, so a version is reported.
        $this->assertTrue($response['available']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{7,}$/', (string) $response['commit']);
        $this->assertIsInt($response['behind']);
    }

    public function test_deploying_is_refused_while_the_switch_is_off(): void
    {
        $this->actAsDeployer();
        config(['deployment.enabled' => false]);

        $this->postJson('/api/admin/deployments')->assertStatus(422)
            ->assertJsonPath('error.code', 'DEPLOY_DISABLED');

        $this->assertSame(0, AuditLog::where('action', 'DEPLOY_STARTED')->count());
    }

    public function test_a_deploy_is_recorded_and_a_second_one_is_refused_while_it_runs(): void
    {
        $this->actAsDeployer();
        config(['deployment.enabled' => true]);

        // Nothing is really deployed: the service is replaced with one that
        // only writes the state a real run would leave behind.
        $this->app->instance(DeploymentService::class, new class extends DeploymentService
        {
            public bool $started = false;

            public function start(int $userId): string
            {
                if ($this->started) {
                    throw new DeploymentFailedException('A deploy is already running; wait for it to finish.');
                }
                $this->started = true;

                return '20260919-010203';
            }
        });

        $this->postJson('/api/admin/deployments')->assertStatus(202)
            ->assertJsonPath('run_id', '20260919-010203')
            ->assertJsonPath('status', 'running');

        $this->assertSame(1, AuditLog::where('action', 'DEPLOY_STARTED')->where('entity_id', '20260919-010203')->count());

        $this->postJson('/api/admin/deployments')->assertStatus(422)
            ->assertJsonPath('error.code', 'DEPLOY_FAILED')
            ->assertJsonPath('error.message', 'A deploy is already running; wait for it to finish.');
    }

    public function test_a_finished_run_reports_success_or_failure_from_its_exit_code(): void
    {
        $this->actAsDeployer();
        $directory = storage_path('app/private/deployments');
        File::ensureDirectoryExists($directory);

        $write = function (array $state): void {
            File::put(storage_path('app/private/deployments/last-run.json'), (string) json_encode($state));
        };

        $write(['run_id' => 'r1', 'started_at' => now()->subMinutes(2)->toIso8601String(), 'started_by' => 1, 'exit_code' => 0, 'finished_at' => now()->toIso8601String()]);
        $this->getJson('/api/admin/deployments')->assertOk()->assertJsonPath('deploy.status', 'succeeded');

        $write(['run_id' => 'r2', 'started_at' => now()->subMinutes(2)->toIso8601String(), 'started_by' => 1, 'exit_code' => 1, 'finished_at' => now()->toIso8601String()]);
        $this->getJson('/api/admin/deployments')->assertOk()->assertJsonPath('deploy.status', 'failed')->assertJsonPath('deploy.exit_code', 1);

        // Started and never finished: running now, but not for ever.
        $write(['run_id' => 'r3', 'started_at' => now()->subMinute()->toIso8601String(), 'started_by' => 1, 'exit_code' => null, 'finished_at' => null]);
        $this->getJson('/api/admin/deployments')->assertOk()->assertJsonPath('deploy.status', 'running');

        $write(['run_id' => 'r4', 'started_at' => now()->subHours(3)->toIso8601String(), 'started_by' => 1, 'exit_code' => null, 'finished_at' => null]);
        $this->getJson('/api/admin/deployments')->assertOk()->assertJsonPath('deploy.status', 'unknown');
    }

    public function test_the_log_of_a_run_is_shown_with_its_status(): void
    {
        $this->actAsDeployer();
        $directory = storage_path('app/private/deployments');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/deploy-r9.log', "==> Backup before deploying\n==> Deployed abc1234\n");
        File::put($directory.'/last-run.json', (string) json_encode([
            'run_id' => 'r9', 'started_at' => now()->subMinute()->toIso8601String(), 'started_by' => 1,
            'exit_code' => 0, 'finished_at' => now()->toIso8601String(),
        ]));

        $this->getJson('/api/admin/deployments')->assertOk()
            ->assertJsonPath('deploy.status', 'succeeded')
            ->assertJsonFragment(['log' => "==> Backup before deploying\n==> Deployed abc1234\n"]);
    }

    public function test_every_deployment_endpoint_needs_the_deploy_permission(): void
    {
        $this->grantPermissions(['admin.settings', 'admin.users']);
        Sanctum::actingAs($this->user);

        $this->getJson('/api/admin/deployments')->assertStatus(403);
        $this->postJson('/api/admin/deployments/check')->assertStatus(403);
        $this->postJson('/api/admin/deployments')->assertStatus(403);
    }

    public function test_only_the_super_administrator_may_deploy(): void
    {
        // The seeders alone must be enough: a fresh production database gets
        // the Super Administrator role without anyone running a console command.
        (new PermissionSeeder)->run();
        (new RoleSeeder)->run();

        $registrar = app(PermissionRegistrar::class);
        $assign = function (string $role) use ($registrar): void {
            $this->revokeAllRoles();
            $registrar->setPermissionsTeamId($this->branch->id);
            $this->user->assignRole(Role::where('name', $role)->whereNull('branch_id')->firstOrFail());
            $registrar->setPermissionsTeamId(null);
            $registrar->forgetCachedPermissions();
            $this->user->unsetRelation('roles')->unsetRelation('permissions');
        };

        // Running the estate is not the same as replacing the code that runs it.
        $assign(CreateAdminUser::SYSTEM_ADMINISTRATOR);
        Sanctum::actingAs($this->user);
        $this->getJson('/api/admin/deployments')->assertStatus(403);
        $this->postJson('/api/admin/deployments')->assertStatus(403);

        $assign(CreateAdminUser::SUPER_ADMINISTRATOR);
        Sanctum::actingAs($this->user);
        $this->getJson('/api/admin/deployments')->assertOk();
    }
}
