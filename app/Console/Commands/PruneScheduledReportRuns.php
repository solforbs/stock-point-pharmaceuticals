<?php

namespace App\Console\Commands;

use App\Services\Reports\ScheduledReportService;
use Illuminate\Console\Command;

/**
 * Part 20.3 — archived scheduled-report runs, and their CSV files, are kept
 * for config('reports.run_retention_days') days and then removed.
 */
class PruneScheduledReportRuns extends Command
{
    protected $signature = 'reports:prune-runs {--days= : Keep runs this many days (defaults to REPORT_RUN_RETENTION_DAYS)}';

    protected $description = 'Remove archived scheduled-report runs older than the retention period (Part 20.3)';

    public function handle(ScheduledReportService $service): int
    {
        $days = max(1, (int) ($this->option('days') ?? config('reports.run_retention_days', 180)));

        $removed = $service->pruneRuns($days);

        $this->info("{$removed} report run(s) older than {$days} days removed.");

        return self::SUCCESS;
    }
}
