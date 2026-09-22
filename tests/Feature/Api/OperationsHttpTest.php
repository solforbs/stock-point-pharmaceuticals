<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\StockBalance;
use App\Services\Admin\BackupService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 17 — operations: system health, database backups and the sync centre.
 */
class OperationsHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->backupDir = storage_path('framework/testing/backups-'.Str::random(8));
        File::ensureDirectoryExists($this->backupDir);
        config(['backup.path' => $this->backupDir, 'backup.retention_days' => 30]);
        Cache::forget('scheduler:last_run');
        $this->grantPermissions(['admin.settings']);
        // Backups and system health span every institution on the platform.
        $this->user->forceFill(['is_platform_admin' => true])->save();
        Sanctum::actingAs($this->user);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupDir);
        parent::tearDown();
    }

    public function test_system_health_reports_every_check_with_a_traffic_light(): void
    {
        $health = $this->getJson('/api/admin/system-health')->assertOk()
            ->assertJsonStructure([
                'app' => ['version', 'environment', 'php_version', 'laravel_version', 'timezone'],
                'checks' => [
                    'database' => ['status', 'message', 'latency_ms', 'size_mb'],
                    'queue' => ['status', 'message', 'pending', 'failed'],
                    'scheduler' => ['status', 'message', 'last_run_at'],
                    'reconciliation' => ['status', 'message', 'drift_rows'],
                    'storage' => ['status', 'message', 'free_bytes', 'total_bytes'],
                    'backup' => ['status', 'message', 'last'],
                    'financial_period' => ['status', 'message'],
                ],
                'failed_jobs',
            ])
            ->assertJsonPath('checks.database.status', 'green')
            ->assertJsonPath('checks.scheduler.status', 'red')
            ->assertJsonPath('checks.backup.status', 'red')
            ->assertJsonPath('checks.reconciliation.status', 'green')
            ->json();
        $this->assertSame(PHP_VERSION, $health['app']['php_version']);

        // The scheduled heartbeat closure turns the scheduler green.
        Cache::forever('scheduler:last_run', now()->subMinute()->toIso8601String());
        $this->getJson('/api/admin/system-health')->assertJsonPath('checks.scheduler.status', 'green');
        Cache::forever('scheduler:last_run', now()->subMinutes(10)->toIso8601String());
        $this->getJson('/api/admin/system-health')->assertJsonPath('checks.scheduler.status', 'red')->assertJsonPath('checks.scheduler.minutes_ago', 10);

        // A backup on disk turns the backup check green.
        file_put_contents($this->backupDir.'/db-'.now()->format('Ymd-His').'.sql.gz', gzencode('-- dump'));
        $this->getJson('/api/admin/system-health')->assertJsonPath('checks.backup.status', 'green');
    }

    public function test_system_health_runs_the_same_reconciliation_as_the_nightly_command(): void
    {
        $batch = $this->receive('L1', now()->addYear()->toDateString(), '100', '2.0000');
        $this->artisan('inventory:reconcile-ledger')->assertSuccessful();

        StockBalance::where('batch_id', $batch->id)->update(['qty_on_hand' => '90.0000']);

        $this->getJson('/api/admin/system-health')->assertOk()
            ->assertJsonPath('checks.reconciliation.status', 'red')
            ->assertJsonPath('checks.reconciliation.drift_rows', 1);
        $this->artisan('inventory:reconcile-ledger')->assertFailed();
    }

    public function test_failed_jobs_are_listed_and_can_be_retried_or_forgotten(): void
    {
        $retryUuid = $this->failJob('RetryMe');
        $forgetUuid = $this->failJob('ForgetMe');

        $this->getJson('/api/admin/system-health')->assertOk()
            ->assertJsonPath('checks.queue.status', 'amber')
            ->assertJsonPath('checks.queue.failed', 2)
            ->assertJsonCount(2, 'failed_jobs')
            ->assertJsonFragment(['uuid' => $retryUuid, 'job' => 'RetryMe', 'exception' => 'RuntimeException: supplier API timed out']);

        $jobsBefore = DB::table('jobs')->count();
        $this->postJson('/api/admin/system-health/retry-failed-jobs', ['ids' => [$retryUuid]])->assertOk()
            ->assertJsonPath('retried', 1)->assertJsonPath('remaining_failed', 1);
        $this->assertSame($jobsBefore + 1, DB::table('jobs')->count());

        $this->postJson("/api/admin/system-health/forget-failed-job/{$forgetUuid}")->assertOk()->assertJsonPath('forgotten', $forgetUuid);
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->postJson("/api/admin/system-health/forget-failed-job/{$forgetUuid}")->assertNotFound()->assertJsonPath('error.code', 'FAILED_JOB_NOT_FOUND');

        $this->assertSame(1, AuditLog::where('action', 'FAILED_JOBS_RETRIED')->count());
        $this->assertSame(1, AuditLog::where('action', 'FAILED_JOB_FORGOTTEN')->count());
    }

    public function test_backups_are_listed_created_and_downloaded(): void
    {
        $this->fakeDump();
        file_put_contents($this->backupDir.'/files-20260101-020000.tar.gz', 'archive');
        file_put_contents($this->backupDir.'/notes.txt', 'not a backup');

        $created = $this->postJson('/api/admin/backups')->assertCreated()->assertJsonPath('kind', 'database')->json();
        $this->assertMatchesRegularExpression(BackupService::NAME_PATTERN, $created['name']);
        $this->assertSame('-- fake dump', gzdecode((string) file_get_contents($this->backupDir.'/'.$created['name'])));

        $this->getJson('/api/admin/backups')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', $created['name'])
            ->assertJsonPath('data.1.name', 'files-20260101-020000.tar.gz')
            ->assertJsonPath('retention_days', 30);

        $download = $this->get("/api/admin/backups/{$created['name']}/download")->assertOk();
        $this->assertStringContainsString($created['name'], (string) $download->headers->get('Content-Disposition'));
        $this->assertSame(1, AuditLog::where('action', 'BACKUP_CREATED')->count());
        $this->assertSame(1, AuditLog::where('action', 'BACKUP_DOWNLOADED')->count());
    }

    public function test_backup_download_refuses_anything_not_in_the_listing(): void
    {
        file_put_contents($this->backupDir.'/notes.txt', 'secret');
        file_put_contents(dirname($this->backupDir).'/db-20260101-000000.sql.gz', 'outside');

        foreach (['notes.txt', '..%2F.env', '..%5C..%5C.env', 'db-20260101-000000.sql.gz', '..%2Fdb-20260101-000000.sql.gz', 'db-20260101-000000.sql.gz%00.txt'] as $name) {
            $this->get("/api/admin/backups/{$name}/download")->assertNotFound();
        }

        @unlink(dirname($this->backupDir).'/db-20260101-000000.sql.gz');
    }

    public function test_a_missing_mysqldump_is_reported_clearly(): void
    {
        config(['backup.mysqldump' => $this->backupDir.'/no-such-mysqldump']);

        $this->getJson('/api/admin/backups')->assertOk()->assertJsonPath('mysqldump_available', false);
        $this->postJson('/api/admin/backups')->assertStatus(422)
            ->assertJsonPath('error.code', 'BACKUP_FAILED')
            ->assertJsonPath('error.message', fn (string $m) => str_contains($m, 'MYSQLDUMP_PATH'));
        $this->assertSame([], glob($this->backupDir.'/db-*'));
    }

    public function test_backup_run_creates_a_dump_and_prunes_past_retention(): void
    {
        $this->fakeDump();
        $old = $this->backupDir.'/db-20250101-020000.sql.gz';
        file_put_contents($old, 'old');
        touch($old, now()->subDays(40)->timestamp);

        $this->artisan('backup:run')->assertSuccessful();

        $this->assertFileDoesNotExist($old);
        $this->assertCount(1, glob($this->backupDir.'/db-*.sql.gz'));
    }

    public function test_operations_endpoints_require_admin_settings(): void
    {
        $this->revokeAllRoles();
        $this->grantPermissions(['product.view'], 'Viewer');

        $this->getJson('/api/admin/system-health')->assertForbidden();
        $this->postJson('/api/admin/system-health/retry-failed-jobs')->assertForbidden();
        $this->getJson('/api/admin/backups')->assertForbidden();
        $this->postJson('/api/admin/backups')->assertForbidden();
        $this->get('/api/admin/backups/db-20260101-000000.sql.gz/download')->assertForbidden();
        $this->getJson('/api/admin/sync-status')->assertForbidden();
    }

    public function test_sync_status_reports_session_terminal_activity_and_the_etims_queue(): void
    {
        $this->revokeAllRoles();
        $this->grantPermissions(['sale.view'], 'Supervisor');

        $this->receive('L1', now()->addYear()->toDateString(), '2000', '2.0000');
        $this->checkout([$this->saleLine('BOX', '1', '500.0000')], [['method' => 'CASH', 'amount' => '500.0000']], ['terminal_id' => 'TILL-1']);
        $this->checkout([$this->saleLine('BOX', '1', '500.0000')], [['method' => 'CASH', 'amount' => '500.0000']], ['terminal_id' => 'TILL-1']);
        $this->checkout([$this->saleLine('BOX', '1', '500.0000')], [['method' => 'CASH', 'amount' => '500.0000']], ['terminal_id' => 'TILL-2']);

        $this->getJson('/api/admin/sync-status')->assertOk()
            ->assertJsonPath('session.user_id', $this->user->id)
            ->assertJsonPath('session.branch.code', 'LDW')
            ->assertJsonPath('offline_queue.available', true)
            ->assertJsonPath('offline_queue.conflicts', 0)
            ->assertJsonPath('replays.recorded', false)
            ->assertJsonPath('activity_24h.sales', 3)
            ->assertJsonCount(2, 'activity_24h.terminals')
            ->assertJsonFragment(['terminal_id' => 'TILL-1', 'sales' => 2, 'voided' => 0])
            ->assertJsonStructure(['etims' => ['pending', 'submitted', 'failed', 'not_configured', 'enabled', 'driver']]);
    }

    private function failJob(string $displayName): string
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['uuid' => $uuid, 'displayName' => $displayName, 'attempts' => 3, 'data' => []]),
            'exception' => "RuntimeException: supplier API timed out\n#0 /app/Jobs/Thing.php(12)",
            'failed_at' => now(),
        ]);

        return $uuid;
    }

    public function test_a_full_backup_holds_the_database_and_the_uploaded_files_in_one_zip(): void
    {
        $this->fakeDump();
        $uploads = storage_path('app'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'licences');
        File::ensureDirectoryExists($uploads);
        file_put_contents($uploads.DIRECTORY_SEPARATOR.'ppb-licence.pdf', '%PDF-1.4 licence scan');

        $created = $this->postJson('/api/admin/backups', ['kind' => 'full'])->assertCreated()->assertJsonPath('kind', 'full')->json();
        $this->assertStringEndsWith('.zip', $created['name']);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($this->backupDir.'/'.$created['name']) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = (string) $zip->getNameIndex($i);
        }

        $sql = array_values(array_filter($names, fn (string $n) => str_ends_with($n, '.sql')));
        $this->assertCount(1, $sql);
        $this->assertSame('-- fake dump', $zip->getFromName($sql[0]));
        $this->assertContains('files/private/licences/ppb-licence.pdf', $names);
        $this->assertSame('%PDF-1.4 licence scan', $zip->getFromName('files/private/licences/ppb-licence.pdf'));
        $this->assertStringContainsString('Restore:', (string) $zip->getFromName('MANIFEST.txt'));
        $this->assertEmpty(array_filter($names, fn (string $n) => str_contains($n, 'backups/')), 'a full backup never swallows the older backups');
        $zip->close();

        // It downloads as a zip and is listed beside the database dumps.
        $download = $this->get("/api/admin/backups/{$created['name']}/download")->assertOk();
        $this->assertSame('application/zip', $download->headers->get('Content-Type'));
        $this->getJson('/api/admin/backups')->assertOk()->assertJsonPath('data.0.name', $created['name']);

        unlink($uploads.DIRECTORY_SEPARATOR.'ppb-licence.pdf');
    }

    private function fakeDump(): void
    {
        $this->app->instance(BackupService::class, new class extends BackupService
        {
            protected function runDump(\Closure $write): void
            {
                $write('-- fake dump');
            }
        });
    }
}
