<?php

namespace Tests\Feature\Api;

use App\Mail\ScheduledReportMail;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduledReport;
use App\Models\ScheduledReportRun;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 20.3 / client item 13 — every scheduled-report run is archived with
 * its CSV in a Report inbox, where it can be previewed, downloaded and
 * marked verified or flagged. Runs never cross institutions.
 */
class ScheduledReportRunHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['report.schedule', 'report.view']);
        Sanctum::actingAs($this->user);
        Mail::fake();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_scheduled_run_and_a_manual_run_are_both_archived_with_their_csv(): void
    {
        Carbon::setTestNow('2026-09-22 11:00:00');
        $schedule = $this->createSchedule('12:00');

        Carbon::setTestNow('2026-09-22 12:05:00');
        $this->artisan('reports:run-scheduled')->expectsOutputToContain('1 sent')->assertSuccessful();

        $scheduled = ScheduledReportRun::sole();
        $this->assertSame($schedule['id'], $scheduled->scheduled_report_id);
        $this->assertSame('SCHEDULED', $scheduled->trigger);
        $this->assertSame('SUCCESS', $scheduled->status);
        $this->assertSame('UNREVIEWED', $scheduled->review_status);
        $this->assertSame('Daily sales summary', $scheduled->report_title);
        $this->assertSame('2026-09-21', $scheduled->period_from->toDateString());
        $this->assertSame(['owner@stockpoint.test'], $scheduled->emailed_to);
        $this->assertSame('sales-daily_summary-2026-09-21-2026-09-21.csv', $scheduled->csv_filename);
        Storage::disk('local')->assertExists((string) $scheduled->csv_path);
        $this->assertStringStartsWith('Day,', (string) Storage::disk('local')->get((string) $scheduled->csv_path));
        Mail::assertSent(ScheduledReportMail::class, 1);

        Carbon::setTestNow('2026-09-22 16:00:00');
        $runId = $this->postJson("/api/scheduled-reports/{$schedule['id']}/run-now")->assertOk()->assertJsonPath('status', 'SENT')->json('run_id');
        $manual = ScheduledReportRun::findOrFail($runId);
        $this->assertSame('MANUAL', $manual->trigger);
        $this->assertSame($this->user->id, $manual->triggered_by);
        Mail::assertSent(ScheduledReportMail::class, 2);

        $this->getJson('/api/scheduled-report-runs')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $runId)
            ->assertJsonPath('data.0.requester.name', 'Test Cashier')
            ->assertJsonPath('data.1.id', $scheduled->id)
            ->assertJsonPath('counts.UNREVIEWED', 2)
            ->assertJsonMissingPath('data.0.csv_path');
    }

    public function test_a_failed_run_is_archived_without_a_file_and_cannot_be_reviewed(): void
    {
        $schedule = $this->createSchedule('18:00');
        ScheduledReport::whereKey($schedule['id'])->update(['recipients' => '[]']);

        $this->postJson("/api/scheduled-reports/{$schedule['id']}/run-now")->assertOk()->assertJsonPath('status', 'FAILED');

        $run = ScheduledReportRun::sole();
        $this->assertSame('FAILED', $run->status);
        $this->assertNull($run->csv_path);
        $this->assertSame([], $run->emailed_to);
        $this->assertStringContainsString('no recipients', (string) $run->error);

        $this->grantPermissions(['report.schedule', 'report.view', 'report.review']);
        $this->getJson("/api/scheduled-report-runs/{$run->id}")->assertOk()->assertJsonPath('has_file', false)->assertJsonPath('preview', null);
        $this->get("/api/scheduled-report-runs/{$run->id}/download")->assertNotFound();
        $this->postJson("/api/scheduled-report-runs/{$run->id}/review", ['review_status' => 'VERIFIED'])->assertStatus(422)->assertJsonPath('error.code', 'NOTHING_TO_REVIEW');
    }

    public function test_a_run_is_previewed_downloaded_and_marked_verified_or_flagged_by_a_reviewer(): void
    {
        $schedule = $this->createSchedule('12:00');
        $runId = $this->postJson("/api/scheduled-reports/{$schedule['id']}/run-now")->assertOk()->json('run_id');

        $this->getJson("/api/scheduled-report-runs/{$runId}")->assertOk()
            ->assertJsonPath('has_file', true)
            ->assertJsonPath('preview.columns.0.label', 'Day')
            ->assertJsonPath('preview.truncated', false)
            ->assertJsonPath('preview.totals.rows', 0);

        $download = $this->get("/api/scheduled-report-runs/{$runId}/download")->assertOk();
        $this->assertStringContainsString('sales-daily_summary-', (string) $download->headers->get('content-disposition'));
        $this->assertStringStartsWith('Day,', $download->streamedContent());

        // Scheduling reports is not the same as signing them off.
        $this->postJson("/api/scheduled-report-runs/{$runId}/review", ['review_status' => 'VERIFIED'])->assertForbidden();

        $this->grantPermissions(['report.schedule', 'report.view', 'report.review']);
        $this->postJson("/api/scheduled-report-runs/{$runId}/review", ['review_status' => 'FLAGGED'])->assertStatus(422)->assertJsonValidationErrors('notes');
        $this->postJson("/api/scheduled-report-runs/{$runId}/review", ['review_status' => 'FLAGGED', 'notes' => 'Monday cash sales look short.'])->assertOk()
            ->assertJsonPath('review_status', 'FLAGGED')
            ->assertJsonPath('review_notes', 'Monday cash sales look short.')
            ->assertJsonPath('reviewer.name', 'Test Cashier');
        $this->getJson('/api/scheduled-report-runs?review_status=FLAGGED')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('counts.FLAGGED', 1)->assertJsonPath('counts.UNREVIEWED', 0);
        $this->getJson('/api/scheduled-report-runs?review_status=UNREVIEWED')->assertOk()->assertJsonCount(0, 'data');

        $this->postJson("/api/scheduled-report-runs/{$runId}/review", ['review_status' => 'VERIFIED'])->assertOk()->assertJsonPath('review_status', 'VERIFIED')->assertJsonPath('review_notes', null);
        $this->assertSame(2, AuditLog::where('action', 'SCHEDULED_REPORT_RUN_REVIEWED')->count());

        // A colleague who only reviews sees who verified it, and when.
        $colleague = $this->colleague(['name' => 'Jane Auditor', 'username' => 'jane', 'email' => 'jane@example.test', 'password' => 'password-long-enough']);
        $this->assignRole($colleague, $this->org->id, $this->branch->id, ['report.view', 'report.review']);
        Sanctum::actingAs($colleague);
        $this->getJson("/api/scheduled-report-runs/{$runId}")->assertOk()
            ->assertJsonPath('review_status', 'VERIFIED')
            ->assertJsonPath('reviewer.name', 'Test Cashier')
            ->assertJsonPath('reviewed_at', fn ($v) => is_string($v) && $v !== '');

        // Someone with neither permission cannot open the inbox.
        $this->assignRole($colleague, $this->org->id, $this->branch->id, ['report.view']);
        $this->getJson('/api/scheduled-report-runs')->assertForbidden();
        $this->getJson("/api/scheduled-report-runs/{$runId}")->assertForbidden();
    }

    public function test_another_institution_cannot_see_download_or_review_a_run(): void
    {
        $schedule = $this->createSchedule('12:00');
        $runId = $this->postJson("/api/scheduled-reports/{$schedule['id']}/run-now")->assertOk()->json('run_id');

        $orgB = Organisation::create(['name' => 'Nairobi Chemists Ltd', 'legal_name' => 'Nairobi Chemists Ltd', 'base_currency' => 'KES', 'fiscal_year_start' => 1]);
        $orgB->forceFill(['is_complimentary' => true])->save();
        $branchB = app(TenantContext::class)->run($orgB->id, fn () => Branch::create(['organisation_id' => $orgB->id, 'code' => 'NBI', 'name' => 'Nairobi CBD', 'retail_enabled' => true, 'wholesale_enabled' => true]));
        $userB = User::create(['name' => 'B Admin', 'username' => 'badmin', 'email' => 'badmin@example.test', 'password' => 'a-long-enough-password']);
        $userB->forceFill(['organisation_id' => $orgB->id])->save();
        $this->assignRole($userB, $orgB->id, $branchB->id, ['report.view', 'report.schedule', 'report.review']);

        Sanctum::actingAs($userB);
        $this->getJson('/api/scheduled-report-runs')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('counts.UNREVIEWED', 0);
        $this->getJson("/api/scheduled-report-runs/{$runId}")->assertNotFound();
        $this->get("/api/scheduled-report-runs/{$runId}/download")->assertNotFound();
        $this->postJson("/api/scheduled-report-runs/{$runId}/review", ['review_status' => 'VERIFIED'])->assertNotFound();

        $this->assertSame('UNREVIEWED', ScheduledReportRun::withoutGlobalScopes()->findOrFail($runId)->review_status);
    }

    public function test_runs_older_than_the_retention_period_are_pruned_with_their_files(): void
    {
        $schedule = $this->createSchedule('12:00');
        $oldId = $this->postJson("/api/scheduled-reports/{$schedule['id']}/run-now")->assertOk()->json('run_id');
        $newId = $this->postJson("/api/scheduled-reports/{$schedule['id']}/run-now")->assertOk()->json('run_id');
        ScheduledReportRun::whereKey($oldId)->update(['generated_at' => now()->subDays(181)]);
        ScheduledReportRun::whereKey($newId)->update(['generated_at' => now()->subDays(179)]);
        $oldPath = (string) ScheduledReportRun::findOrFail($oldId)->csv_path;

        config(['reports.run_retention_days' => 180]);
        $this->artisan('reports:prune-runs')->expectsOutputToContain('1 report run(s) older than 180 days removed.')->assertSuccessful();

        $this->assertNull(ScheduledReportRun::find($oldId));
        Storage::disk('local')->assertMissing($oldPath);
        $this->assertNotNull(ScheduledReportRun::find($newId));
        Storage::disk('local')->assertExists((string) ScheduledReportRun::findOrFail($newId)->csv_path);
    }

    /**
     * @return array<string, mixed>
     */
    private function createSchedule(string $runAt): array
    {
        return $this->postJson('/api/scheduled-reports', [
            'report_key' => 'sales.daily_summary', 'frequency' => 'DAILY', 'run_at' => $runAt, 'recipients' => ['owner@stockpoint.test'],
        ])->assertCreated()->json();
    }

    /**
     * Gives $user exactly $permissions in $branchId through one role of its own.
     *
     * @param  list<string>  $permissions
     */
    private function assignRole(User $user, string $organisationId, string $branchId, array $permissions): void
    {
        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $registrar = app(PermissionRegistrar::class);

        app(TenantContext::class)->run($organisationId, function () use ($user, $organisationId, $branchId, $permissions, $registrar) {
            $role = Role::firstOrCreate(['organisation_id' => $organisationId, 'name' => "Role for {$user->username}", 'guard_name' => 'web', 'branch_id' => null]);
            $role->syncPermissions($permissions);
            $registrar->setPermissionsTeamId($branchId);
            $user->syncRoles([$role]);
        });

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }
}
