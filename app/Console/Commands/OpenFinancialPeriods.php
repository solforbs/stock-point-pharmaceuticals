<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\FinancialPeriod;
use App\Models\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Part 12.4 — nothing posts outside an open period, so the current month and
 * the ones ahead of it must always exist. Runs daily; only creates periods
 * that are missing and never reopens a closed one.
 */
class OpenFinancialPeriods extends Command
{
    protected $signature = 'finance:open-periods {--ahead=1 : How many months after the current one to open}';

    protected $description = 'Make sure the current and upcoming monthly financial periods exist (Part 12.4)';

    public function handle(): int
    {
        $ahead = max(0, (int) $this->option('ahead'));

        foreach (Organisation::all() as $organisation) {
            for ($offset = 0; $offset <= $ahead; $offset++) {
                $month = Carbon::now()->startOfMonth()->addMonthsNoOverflow($offset);
                $period = FinancialPeriod::firstOrCreate(
                    ['organisation_id' => $organisation->id, 'fiscal_year' => (int) $month->format('Y'), 'period_no' => $month->month],
                    ['start_date' => $month->toDateString(), 'end_date' => $month->copy()->endOfMonth()->toDateString(), 'status' => 'OPEN'],
                );

                if ($period->wasRecentlyCreated) {
                    AuditLog::record('PERIOD_OPENED', 'financial_period', $period->id, ['reference' => $month->format('Y-m')]);
                    $this->line("Opened {$month->format('Y-m')} for {$organisation->name}");
                }
            }
        }

        return self::SUCCESS;
    }
}
