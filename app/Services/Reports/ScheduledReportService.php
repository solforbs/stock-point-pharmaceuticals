<?php

namespace App\Services\Reports;

use App\Mail\ScheduledReportMail;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ScheduledReport;
use App\Models\ScheduledReportRun;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Part 20.3 — runs a scheduled report as the user who scheduled it, in the
 * branch it was scheduled for, and mails the CSV to its recipients. Every
 * run is archived with its CSV for review in the Report inbox. A failure
 * is recorded on the schedule and the run, never thrown, so one broken
 * schedule cannot stop the others.
 */
class ScheduledReportService
{
    public function __construct(private readonly ReportRunner $runner) {}

    /**
     * Runs every active schedule whose next run time has come.
     *
     * @return array{run: int, sent: int, failed: int}
     */
    public function runDue(?Carbon $now = null): array
    {
        $now ??= now();
        $counts = ['run' => 0, 'sent' => 0, 'failed' => 0];

        $due = ScheduledReport::where('is_active', true)->whereNotNull('next_run_at')->where('next_run_at', '<=', $now)->orderBy('next_run_at')->get();
        foreach ($due as $schedule) {
            $counts['run']++;
            try {
                $result = $this->run($schedule, $now, advance: true);
                $counts[$result['status'] === 'SENT' ? 'sent' : 'failed']++;
            } catch (\Throwable $e) {
                // Even the bookkeeping failed (e.g. the database blipped): count it and move on.
                report($e);
                $counts['failed']++;
            }
        }

        return $counts;
    }

    /**
     * Runs one schedule. $triggeredBy is the person who pressed "Run now";
     * null means the scheduler ran it.
     *
     * @return array{status: string, error: ?string, rows: ?int, from: ?string, to: ?string, run_id: ?string}
     */
    public function run(ScheduledReport $schedule, ?Carbon $at = null, bool $advance = false, ?User $triggeredBy = null): array
    {
        // A schedule runs as its own institution, never the scheduler's.
        return app(TenantContext::class)->run($schedule->organisation_id, fn () => $this->runForTenant($schedule, $at, $advance, $triggeredBy));
    }

    /**
     * Removes archived runs, and their files, generated more than $days days ago.
     */
    public function pruneRuns(int $days): int
    {
        $removed = 0;
        ScheduledReportRun::withoutGlobalScopes()
            ->where('generated_at', '<', now()->subDays($days))
            ->chunkById(200, function ($runs) use (&$removed) {
                foreach ($runs as $run) {
                    if ($run->csv_path !== null) {
                        Storage::disk('local')->delete($run->csv_path);
                    }
                    $run->delete();
                    $removed++;
                }
            });

        return $removed;
    }

    /**
     * @return array{status: string, error: ?string, rows: ?int, from: ?string, to: ?string, run_id: ?string}
     */
    private function runForTenant(ScheduledReport $schedule, ?Carbon $at, bool $advance, ?User $triggeredBy): array
    {
        $at ??= now();
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = $registrar->getPermissionsTeamId();
        $window = $schedule->windowFor($at);
        $rows = null;
        $error = null;
        $report = null;
        $csvPath = null;
        $filename = null;

        try {
            $user = $schedule->created_by ? User::find($schedule->created_by) : null;
            if (! $user || ! $user->is_active) {
                throw new \RuntimeException('The user who scheduled this report no longer exists or is inactive; reassign the schedule.');
            }
            if (($schedule->recipients ?? []) === []) {
                throw new \RuntimeException('The schedule has no recipients.');
            }

            // Permissions are evaluated in the schedule's branch, exactly as on screen.
            $registrar->setPermissionsTeamId($schedule->branch_id);
            $user->unsetRelation('roles')->unsetRelation('permissions');

            $filters = array_filter(($schedule->filters_json ?? []) + $window, fn ($v) => $v !== null && $v !== '');
            $report = $this->runner->run($schedule->report_key, $user, $schedule->organisation_id, $schedule->branch_id, $filters);
            $csv = $this->runner->toCsv($report);
            $rows = count($report['rows']);

            // Archived before mailing, so the report can still be read in the app if the mail fails.
            $filename = str_replace('.', '-', $schedule->report_key)."-{$report['from']}-{$report['to']}.csv";
            $csvPath = "scheduled-reports/{$schedule->organisation_id}/{$at->format('Y-m')}/".Str::uuid().'.csv';
            if (! Storage::disk('local')->put($csvPath, $csv)) {
                $csvPath = null;
                throw new \RuntimeException('The report could not be saved to storage.');
            }

            $branch = Branch::find($schedule->branch_id);
            Mail::to($schedule->recipients)->send(new ScheduledReportMail(
                (string) $report['title'], (string) $report['from'], (string) $report['to'], $rows, (string) ($branch->name ?? ''), $csv, $filename,
            ));
            $status = 'SENT';
        } catch (\Throwable $e) {
            $status = 'FAILED';
            $error = Str::limit($e->getMessage(), 1000);
            report($e);
        } finally {
            $registrar->setPermissionsTeamId($previousTeam);
        }

        $run = ScheduledReportRun::create([
            'organisation_id' => $schedule->organisation_id,
            'branch_id' => $schedule->branch_id,
            'scheduled_report_id' => $schedule->id,
            'report_key' => $schedule->report_key,
            'report_title' => (string) ($report['title'] ?? ReportCatalogue::find($schedule->report_key)['title'] ?? $schedule->report_key),
            'period_from' => $report['from'] ?? $window['from'],
            'period_to' => $report['to'] ?? $window['to'],
            'trigger' => $triggeredBy ? 'MANUAL' : 'SCHEDULED',
            'triggered_by' => $triggeredBy?->id,
            'generated_at' => $at,
            'row_count' => $rows,
            'columns_json' => $report['columns'] ?? null,
            'totals_json' => $report['totals'] ?? null,
            'csv_path' => $csvPath,
            'csv_filename' => $csvPath ? $filename : null,
            'emailed_to' => $status === 'SENT' ? $schedule->recipients : [],
            'status' => $status === 'SENT' ? 'SUCCESS' : 'FAILED',
            'error' => $error,
            'review_status' => 'UNREVIEWED',
        ]);

        $schedule->forceFill([
            'last_run_at' => $at,
            'last_status' => $status,
            'last_error' => $error,
            'next_run_at' => $advance ? $schedule->nextRunAfter($at) : $schedule->next_run_at,
        ])->save();

        AuditLog::record('SCHEDULED_REPORT_RUN', 'scheduled_report', $schedule->id, [
            'reference' => $schedule->report_key,
            'after_json' => ['status' => $status, 'rows' => $rows, 'from' => $window['from'], 'to' => $window['to'], 'recipients' => $schedule->recipients, 'run_id' => $run->id],
            'reason' => $error,
        ]);

        return ['status' => $status, 'error' => $error, 'rows' => $rows, 'from' => $window['from'], 'to' => $window['to'], 'run_id' => $run->id];
    }
}
