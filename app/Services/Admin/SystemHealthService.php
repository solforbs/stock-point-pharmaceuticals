<?php

namespace App\Services\Admin;

use App\Models\FinancialPeriod;
use App\Services\Inventory\LedgerReconciliation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Part 17 — operations: one read of everything an administrator needs to
 * know the system is healthy — database, queue, scheduler, the nightly
 * ledger reconciliation (Part 7.1 / 12.4), disk, backups and the financial
 * period. Every check carries a traffic-light status and a plain sentence
 * saying why, so the screen never has to interpret raw numbers.
 */
class SystemHealthService
{
    public const HEARTBEAT_KEY = 'scheduler:last_run';

    public function __construct(
        private readonly LedgerReconciliation $reconciliation,
        private readonly BackupService $backups,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(string $organisationId): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'app' => $this->app(),
            'checks' => [
                'database' => $this->database(),
                'queue' => $this->queue(),
                'scheduler' => $this->scheduler(),
                'reconciliation' => $this->reconciliationCheck(),
                'storage' => $this->storage(),
                'backup' => $this->backup(),
                'financial_period' => $this->financialPeriod($organisationId),
            ],
        ];
    }

    /**
     * The 10 most recent failed jobs.
     *
     * @return list<array{id: int, uuid: ?string, connection: string, queue: string, job: ?string, exception: string, failed_at: string}>
     */
    public function recentFailures(int $limit = 10): array
    {
        return DB::table('failed_jobs')->orderByDesc('failed_at')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (object $job) => [
                'id' => (int) $job->id,
                'uuid' => $job->uuid ?? null,
                'connection' => (string) $job->connection,
                'queue' => (string) $job->queue,
                'job' => json_decode((string) $job->payload, true)['displayName'] ?? null,
                'exception' => Str::limit(trim((string) strtok((string) $job->exception, "\n")), 300),
                'failed_at' => Carbon::parse($job->failed_at)->toIso8601String(),
            ])->values()->all();
    }

    /**
     * @return array<string, string|null>
     */
    private function app(): array
    {
        return [
            'version' => $this->version(),
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'timezone' => (string) config('app.timezone'),
            'server_time' => now()->toIso8601String(),
        ];
    }

    private function version(): string
    {
        return Cache::remember('system-health:version', 300, function (): string {
            try {
                $process = new Process(['git', 'rev-parse', '--short', 'HEAD'], base_path(), null, null, 3);
                $process->run();
                $hash = trim($process->getOutput());
                if ($process->isSuccessful() && preg_match('/^[0-9a-f]{4,40}$/', $hash) === 1) {
                    return $hash;
                }
            } catch (\Throwable) {
                // Not a git checkout, or git is not installed — fall through.
            }

            return (string) config('app.version', 'dev');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function database(): array
    {
        try {
            $started = hrtime(true);
            DB::select('select 1');
            $latency = round((hrtime(true) - $started) / 1_000_000, 1);

            $size = DB::table('information_schema.tables')
                ->where('table_schema', DB::connection()->getDatabaseName())
                ->selectRaw('COALESCE(SUM(data_length + index_length), 0) as bytes')->value('bytes');
            $sizeMb = round(((float) $size) / 1_048_576, 1);

            return $latency > 200
                ? $this->check('amber', "Connected, but a trivial query took {$latency} ms; the database server is under load.", ['latency_ms' => $latency, 'size_mb' => $sizeMb])
                : $this->check('green', "Connected in {$latency} ms; the database is {$sizeMb} MB.", ['latency_ms' => $latency, 'size_mb' => $sizeMb]);
        } catch (\Throwable $e) {
            return $this->check('red', 'The database cannot be reached: '.Str::limit($e->getMessage(), 200), ['latency_ms' => null, 'size_mb' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        $connection = (string) config('queue.default');
        $pending = DB::table('jobs')->count();
        $oldest = DB::table('jobs')->min('created_at');
        $failed = DB::table('failed_jobs')->count();
        $oldestMinutes = $oldest ? (int) floor((now()->timestamp - (int) $oldest) / 60) : null;

        $data = ['connection' => $connection, 'pending' => $pending, 'oldest_pending_minutes' => $oldestMinutes, 'failed' => $failed];

        if ($failed > 0) {
            return $this->check('amber', "{$failed} job(s) failed. Retry them once the cause is fixed, or forget them if they are no longer needed.", $data);
        }
        if ($oldestMinutes !== null && $oldestMinutes > 15) {
            return $this->check('red', "{$pending} job(s) waiting, the oldest for {$oldestMinutes} minutes — the queue worker is not running.", $data);
        }
        if ($connection === 'sync') {
            return $this->check('green', 'Jobs run inline (QUEUE_CONNECTION=sync); there is no worker to monitor.', $data);
        }

        return $this->check('green', $pending > 0 ? "{$pending} job(s) waiting; the worker is keeping up." : 'No jobs waiting and none failed.', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduler(): array
    {
        $lastRun = Cache::get(self::HEARTBEAT_KEY);
        if (! $lastRun) {
            return $this->check('red', 'The scheduler has never run. Add the cron entry `* * * * * php artisan schedule:run` so nightly expiry, reconciliation and backups happen.', ['last_run_at' => null, 'minutes_ago' => null]);
        }

        $minutes = (int) floor(Carbon::parse((string) $lastRun)->diffInSeconds(now(), true) / 60);
        $data = ['last_run_at' => (string) $lastRun, 'minutes_ago' => $minutes];

        return $minutes < 3
            ? $this->check('green', 'The scheduler ran '.($minutes === 0 ? 'less than a minute' : "{$minutes} minute(s)").' ago.', $data)
            : $this->check('red', "The scheduler last ran {$minutes} minutes ago; the cron entry has stopped. Nightly jobs are not running.", $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function reconciliationCheck(): array
    {
        $drift = $this->reconciliation->balanceDrift()->count();
        $orphans = $this->reconciliation->orphanLedgerRows();
        $negative = $this->reconciliation->negativeBalances();
        $gl = $this->reconciliation->glComparison();
        $glMismatches = count(array_filter($gl, fn (array $row) => ! $row['matches']));

        $data = ['drift_rows' => $drift, 'orphan_ledger_rows' => $orphans, 'negative_balances' => $negative, 'gl_mismatches' => $glMismatches, 'gl' => $gl];

        if ($drift > 0 || $orphans > 0 || $glMismatches > 0) {
            $parts = array_filter([
                $drift > 0 ? "{$drift} balance row(s) differ from the ledger" : null,
                $orphans > 0 ? "{$orphans} ledger row(s) have no balance" : null,
                $glMismatches > 0 ? 'ledger inventory value differs from the GL' : null,
            ]);

            return $this->check('red', 'Reconciliation FAILED: '.implode('; ', $parts).'. This is a P1 incident (Part 7.1).', $data);
        }
        if ($negative > 0) {
            return $this->check('amber', "{$negative} negative balance(s); legal only from offline sync — investigate.", $data);
        }

        return $this->check('green', 'Balances equal the ledger and the ledger equals the GL.', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function storage(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        if ($free === false || $total === false || $total <= 0) {
            return $this->check('amber', 'Disk space could not be read on this server.', ['free_bytes' => null, 'total_bytes' => null, 'free_pct' => null]);
        }

        $pct = round($free / $total * 100, 1);
        $freeGb = round($free / 1_073_741_824, 1);
        $data = ['free_bytes' => (int) $free, 'total_bytes' => (int) $total, 'free_pct' => $pct];

        return match (true) {
            $pct < 5 => $this->check('red', "Only {$freeGb} GB ({$pct}%) free — backups and uploads will start failing.", $data),
            $pct < 15 => $this->check('amber', "{$freeGb} GB ({$pct}%) free; plan to clear old backups or grow the disk.", $data),
            default => $this->check('green', "{$freeGb} GB ({$pct}%) free.", $data),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function backup(): array
    {
        $latest = collect($this->backups->list())->firstWhere('kind', 'database');
        if (! $latest) {
            return $this->check('red', 'No database backup exists. Take one now from the Backup screen.', ['last' => null, 'hours_ago' => null]);
        }

        $hours = (int) floor(Carbon::parse($latest['created_at'])->diffInMinutes(now(), true) / 60);
        $data = ['last' => $latest, 'hours_ago' => $hours];

        return match (true) {
            $hours > 72 => $this->check('red', "The last backup is {$hours} hours old; the nightly backup is not running.", $data),
            $hours > 26 => $this->check('amber', "The last backup is {$hours} hours old; the nightly backup was missed.", $data),
            default => $this->check('green', 'Last backup '.($hours === 0 ? 'within the hour' : "{$hours} hour(s) ago").'.', $data),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function financialPeriod(string $organisationId): array
    {
        $period = FinancialPeriod::openPeriodFor($organisationId, now());
        if (! $period) {
            return $this->check('red', 'No financial period is open for today, so nothing can post. Run `php artisan finance:open-periods` or open one in Finance → Periods.', ['period' => null]);
        }

        return $this->check('green', "Period {$period->fiscal_year}/{$period->period_no} is open (".Carbon::parse($period->start_date)->toDateString().' to '.Carbon::parse($period->end_date)->toDateString().').', [
            'period' => $period->only(['id', 'fiscal_year', 'period_no', 'status']) + ['start_date' => Carbon::parse($period->start_date)->toDateString(), 'end_date' => Carbon::parse($period->end_date)->toDateString()],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function check(string $status, string $message, array $data = []): array
    {
        return ['status' => $status, 'message' => $message] + $data;
    }
}
