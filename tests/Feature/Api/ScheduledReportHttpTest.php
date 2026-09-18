<?php

namespace Tests\Feature\Api;

use App\Mail\ScheduledReportMail;
use App\Models\AuditLog;
use App\Models\ScheduledReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 20.3 — catalogue reports mailed as CSV on a schedule, run with the
 * creator's permissions; a failing schedule is recorded, never fatal.
 */
class ScheduledReportHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['report.schedule', 'report.view']);
        Sanctum::actingAs($this->user);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_schedule_is_created_with_its_next_run_and_the_command_mails_it_when_due(): void
    {
        Carbon::setTestNow('2026-09-16 10:00:00'); // a Wednesday

        $schedule = $this->postJson('/api/scheduled-reports', [
            'report_key' => 'sales.daily_summary', 'frequency' => 'WEEKLY', 'run_at' => '07:30', 'weekday' => 1,
            'recipients' => ['Owner@Example.test', 'finance@example.test'],
        ])->assertCreated()
            ->assertJsonPath('report_title', 'Daily sales summary')
            ->assertJsonPath('recipients', ['owner@example.test', 'finance@example.test'])
            ->assertJsonPath('is_active', true)
            ->json();
        $this->assertStringStartsWith('2026-09-21T07:30', $schedule['next_run_at'], 'the next Monday at 07:30');
        $this->getJson('/api/scheduled-reports')->assertOk()->assertJsonCount(1, 'data');

        // Not yet due: nothing is sent.
        $this->artisan('reports:run-scheduled')->assertSuccessful();
        Mail::assertNothingSent();

        Carbon::setTestNow('2026-09-21 07:45:00');
        $this->artisan('reports:run-scheduled')->expectsOutputToContain('1 sent')->assertSuccessful();

        Mail::assertSent(ScheduledReportMail::class, fn (ScheduledReportMail $mail) => $mail->hasTo('owner@example.test') && $mail->hasTo('finance@example.test')
            && $mail->filename === 'sales-daily_summary-2026-09-14-2026-09-20.csv'
            && $mail->periodFrom === '2026-09-14' && $mail->periodTo === '2026-09-20' && str_starts_with($mail->csv, 'Day,'));

        $fresh = ScheduledReport::findOrFail($schedule['id']);
        $this->assertSame('SENT', $fresh->last_status);
        $this->assertSame('2026-09-28 07:30:00', $fresh->next_run_at?->toDateTimeString(), 'advanced to the following Monday');
        $this->assertSame(1, AuditLog::where('action', 'SCHEDULED_REPORT_RUN')->count());
    }

    public function test_run_now_sends_immediately_and_a_failure_is_recorded_not_thrown(): void
    {
        $schedule = $this->postJson('/api/scheduled-reports', [
            'report_key' => 'inventory.movement_classes', 'filters' => ['dead_days' => 60, 'customer_id' => null], 'frequency' => 'MONTHLY', 'run_at' => '06:00', 'month_day' => 1,
            'recipients' => ['ops@example.test'],
        ])->assertCreated()->assertJsonPath('filters_json.dead_days', 60)->json();
        $nextRun = $schedule['next_run_at'];

        $this->postJson("/api/scheduled-reports/{$schedule['id']}/run-now")->assertOk()->assertJsonPath('status', 'SENT')->assertJsonPath('schedule.next_run_at', $nextRun);
        Mail::assertSent(ScheduledReportMail::class, 1);

        // The creator loses report.view: the schedule fails on its next run, and says why.
        $this->grantPermissions(['report.schedule']);
        $this->postJson("/api/scheduled-reports/{$schedule['id']}/run-now")->assertOk()
            ->assertJsonPath('status', 'FAILED')->assertJsonPath('schedule.last_status', 'FAILED');
        $this->assertStringContainsString('report.view', (string) ScheduledReport::findOrFail($schedule['id'])->last_error);
        Mail::assertSent(ScheduledReportMail::class, 1);

        // A broken schedule does not stop the command.
        ScheduledReport::whereKey($schedule['id'])->update(['next_run_at' => now()->subMinute()]);
        $this->artisan('reports:run-scheduled')->expectsOutputToContain('1 failed')->assertSuccessful();
    }

    public function test_schedules_are_validated_edited_deactivated_and_deleted(): void
    {
        $this->postJson('/api/scheduled-reports', ['report_key' => 'no.such.report', 'frequency' => 'DAILY', 'run_at' => '07:00', 'recipients' => ['a@example.test']])->assertStatus(422);
        $this->postJson('/api/scheduled-reports', ['report_key' => 'sales.daily_summary', 'frequency' => 'WEEKLY', 'run_at' => '07:00', 'recipients' => ['a@example.test']])->assertStatus(422);
        $this->postJson('/api/scheduled-reports', ['report_key' => 'sales.daily_summary', 'frequency' => 'DAILY', 'run_at' => '25:00', 'recipients' => ['a@example.test']])->assertStatus(422);
        $this->postJson('/api/scheduled-reports', ['report_key' => 'sales.daily_summary', 'frequency' => 'DAILY', 'run_at' => '07:00', 'recipients' => ['not-an-email']])->assertStatus(422);
        $this->postJson('/api/scheduled-reports', ['report_key' => 'sales.daily_summary', 'frequency' => 'DAILY', 'run_at' => '07:00', 'recipients' => []])->assertStatus(422);
        // A report the creator cannot open cannot be scheduled.
        $this->postJson('/api/scheduled-reports', ['report_key' => 'finance.vat_return', 'frequency' => 'DAILY', 'run_at' => '07:00', 'recipients' => ['a@example.test']])
            ->assertStatus(403)->assertJsonPath('error.code', 'REPORT_FORBIDDEN');

        $schedule = $this->postJson('/api/scheduled-reports', ['report_key' => 'sales.daily_summary', 'frequency' => 'DAILY', 'run_at' => '07:00', 'recipients' => ['a@example.test']])->assertCreated()->json();

        $this->patchJson("/api/scheduled-reports/{$schedule['id']}", ['frequency' => 'MONTHLY', 'month_day' => 15, 'run_at' => '08:15'])->assertOk()
            ->assertJsonPath('frequency', 'MONTHLY')->assertJsonPath('month_day', 15)->assertJsonPath('run_at', '08:15');
        $this->patchJson("/api/scheduled-reports/{$schedule['id']}", ['is_active' => false])->assertOk()->assertJsonPath('is_active', false)->assertJsonPath('next_run_at', null);
        $this->patchJson("/api/scheduled-reports/{$schedule['id']}", ['is_active' => true])->assertOk()->assertJsonPath('is_active', true);
        $this->assertNotNull(ScheduledReport::findOrFail($schedule['id'])->next_run_at);

        $this->deleteJson("/api/scheduled-reports/{$schedule['id']}")->assertOk();
        $this->getJson("/api/scheduled-reports/{$schedule['id']}")->assertNotFound();
        $this->assertSame(1, AuditLog::where('action', 'SCHEDULED_REPORT_DELETED')->count());

        $this->grantPermissions(['report.view']);
        $this->getJson('/api/scheduled-reports')->assertStatus(403);
        $this->postJson('/api/scheduled-reports', ['report_key' => 'sales.daily_summary', 'frequency' => 'DAILY', 'run_at' => '07:00', 'recipients' => ['a@example.test']])->assertStatus(403);
    }
}
