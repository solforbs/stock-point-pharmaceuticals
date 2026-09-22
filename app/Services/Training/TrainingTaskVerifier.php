<?php

namespace App\Services\Training;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Checks a practice task against the books: did this trainee actually create
 * the record the task asks for, after they started the task? A task whose
 * catalogue entry carries no `verify` rule can only be self-confirmed.
 *
 * A rule names a table, the column holding the acting user, optional column
 * filters and the timestamp that must fall after the task was started:
 *
 *     ['table' => 'quotations', 'user_column' => 'created_by',
 *      'where' => ['status' => ['ACCEPTED', 'CONVERTED']], 'since_column' => 'updated_at',
 *      'label' => 'a quotation you raised and accepted']
 *
 * The user column confines the check to the trainee's own records, and a
 * user belongs to exactly one institution, so no other tenant's row can
 * ever satisfy it.
 */
class TrainingTaskVerifier
{
    /**
     * @param  array<string, mixed>  $task
     * @return array{verified: bool, detail: string}
     */
    public function verify(array $task, User $user, Carbon $startedAt): array
    {
        $rule = $task['verify'] ?? null;
        if (! is_array($rule)) {
            return ['verified' => false, 'detail' => 'Self-confirmed: this task cannot be checked automatically.'];
        }

        $table = (string) $rule['table'];
        $since = (string) ($rule['since_column'] ?? 'created_at');
        if (! Schema::hasTable($table)) {
            return ['verified' => false, 'detail' => 'Self-confirmed: the records for this task are not available.'];
        }

        $query = DB::table($table)
            ->where((string) $rule['user_column'], $user->id)
            // A minute's grace for the clock between the task starting and the record's timestamp.
            ->where($since, '>=', $startedAt->copy()->subMinute());

        foreach ($rule['where'] ?? [] as $column => $value) {
            is_array($value) ? $query->whereIn($column, $value) : $query->where($column, $value);
        }

        $count = $query->count();
        $label = (string) ($rule['label'] ?? 'the record this task asks for');

        return $count > 0
            ? ['verified' => true, 'detail' => "Verified by the system: found {$label} ({$count})."]
            : ['verified' => false, 'detail' => "Self-confirmed: the system found no {$label} since you started the task."];
    }
}
