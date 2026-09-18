<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Part 8.3 — batches past expiry leave free-to-sell every night.
Schedule::command('inventory:expire-batches')->dailyAt('00:05');

// Part 7.5 — abandoned orders release their stock automatically.
Schedule::command('inventory:release-expired-reservations')->hourly();

// Part 12.4 — the current and next month are always open for posting.
Schedule::command('finance:open-periods')->dailyAt('00:01');

// Part 7.1 / 12.4 — the reconciliation that must return zero rows.
Schedule::command('inventory:reconcile-ledger')->dailyAt('00:30');

// Part 17 — payment deadlines and shelf-life risk, recomputed before the
// counter opens so the morning's alerts are the day's truth.
Schedule::command('alerts:scan')->dailyAt('05:30')->withoutOverlapping();

// Part 20.3 — scheduled reports are mailed when due (run_at is HH:MM, so a quarter-hour tick is enough).
Schedule::command('reports:run-scheduled')->everyFifteenMinutes()->withoutOverlapping();

// Part 17 — operations: the System Health screen reads this heartbeat to
// prove the scheduler cron is installed and running.
Schedule::call(fn () => Cache::forever('scheduler:last_run', now()->toIso8601String()))->everyMinute()->name('scheduler-heartbeat');

// Part 17 — operations: nightly database backup, pruned to BACKUP_RETENTION_DAYS.
Schedule::command('backup:run')->dailyAt('02:00');
