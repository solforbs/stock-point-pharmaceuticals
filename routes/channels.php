<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

/**
 * Part 17 — who may listen to what. A private channel is only as private as
 * this file: every subscription is authorised here against the same rules
 * the HTTP API uses, so a websocket can never become a way around them.
 */

/** A person's own channel: direct messages and their alerts. */
Broadcast::channel('users.{id}', fn (User $user, string $id) => (int) $user->id === (int) $id);

/**
 * Everyone working in one branch. Only users who hold a role there may
 * listen, so a cashier in Lodwar never hears Nairobi's traffic.
 */
Broadcast::channel('branches.{branchId}', function (User $user, string $branchId) {
    $names = config('permission.table_names');
    $team = config('permission.column_names.team_foreign_key');

    return DB::table($names['model_has_roles'])
        ->where('model_type', $user->getMorphClass())
        ->where('model_id', $user->getKey())
        ->where(fn ($q) => $q->where($team, $branchId)->orWhereNull($team))
        ->exists();
});
