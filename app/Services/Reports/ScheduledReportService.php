<?php

namespace App\Services\Reports;

use App\Mail\ScheduledReportMail;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ScheduledReport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Part 20.3 — runs a scheduled report as the user who scheduled it, in the
 * branch it was scheduled for, and mails the CSV to its recipients. A
 * failure is recorded on the schedule, never thrown, so one broken
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
     * @return array{status: string, error: ?string, rows: ?int, from: ?string, to: ?string}
     */
    public function run(ScheduledReport $schedule, ?Carbon $at = null, bool $advance = false): array
    {
        $at ??= now();
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = $registrar->getPermissionsTeamId();
        $window = $schedule->windowFor($at);
        $rows = null;
        $error = null;

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

            $branch = Branch::find($schedule->branch_id);
            $filename = str_replace('.', '-', $schedule->report_key)."-{$report['from']}-{$report['to']}.csv";
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

        $schedule->forceFill([
            'last_run_at' => $at,
            'last_status' => $status,
            'last_error' => $error,
            'next_run_at' => $advance ? $schedule->nextRunAfter($at) : $schedule->next_run_at,
        ])->save();

        AuditLog::record('SCHEDULED_REPORT_RUN', 'scheduled_report', $schedule->id, [
            'reference' => $schedule->report_key,
            'after_json' => ['status' => $status, 'rows' => $rows, 'from' => $window['from'], 'to' => $window['to'], 'recipients' => $schedule->recipients],
            'reason' => $error,
        ]);

        return ['status' => $status, 'error' => $error, 'rows' => $rows, 'from' => $window['from'], 'to' => $window['to']];
    }
}
