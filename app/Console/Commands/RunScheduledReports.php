<?php

namespace App\Console\Commands;

use App\Services\Reports\ScheduledReportService;
use Illuminate\Console\Command;

/**
 * Part 20.3 — mails every scheduled report that has fallen due. Runs every
 * fifteen minutes; a failing schedule is recorded and skipped, never fatal.
 */
class RunScheduledReports extends Command
{
    protected $signature = 'reports:run-scheduled';

    protected $description = 'Run and email every scheduled report that is due (Part 20.3)';

    public function handle(ScheduledReportService $service): int
    {
        $counts = $service->runDue();

        $this->info("{$counts['run']} scheduled report(s) run: {$counts['sent']} sent, {$counts['failed']} failed.");

        return self::SUCCESS;
    }
}
