<?php

use Illuminate\Support\Facades\Schedule;

// Part 8.3 — batches past expiry leave free-to-sell every night.
Schedule::command('inventory:expire-batches')->dailyAt('00:05');

// Part 7.5 — abandoned orders release their stock automatically.
Schedule::command('inventory:release-expired-reservations')->hourly();

// Part 7.1 / 12.4 — the reconciliation that must return zero rows.
Schedule::command('inventory:reconcile-ledger')->dailyAt('00:30');
