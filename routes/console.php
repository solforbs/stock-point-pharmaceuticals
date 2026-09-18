<?php

use Illuminate\Support\Facades\Schedule;

// Part 8.3 — batches past expiry leave free-to-sell every night.
Schedule::command('inventory:expire-batches')->dailyAt('00:05');

// Part 7.5 — abandoned orders release their stock automatically.
Schedule::command('inventory:release-expired-reservations')->hourly();

// Part 12.4 — the current and next month are always open for posting.
Schedule::command('finance:open-periods')->dailyAt('00:01');

// Part 7.1 / 12.4 — the reconciliation that must return zero rows.
Schedule::command('inventory:reconcile-ledger')->dailyAt('00:30');
