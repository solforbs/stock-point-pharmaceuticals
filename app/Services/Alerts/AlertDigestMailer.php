<?php

namespace App\Services\Alerts;

use App\Mail\AlertDigestMail;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

/**
 * Part 17 — mails the morning alert digest. Each recipient is sent only the
 * alerts their permissions in that branch allow them to see, so the same
 * run tells the storekeeper about shelf life and the credit controller
 * about money, and neither learns the other's business.
 */
class AlertDigestMailer
{
    /** The permissions that carry an alert, and so decide who hears about it. */
    public const ALERT_PERMISSIONS = ['finance.ar.view', 'finance.ap.view', 'stock.view'];

    /**
     * @return array{sent: int, skipped: int}
     */
    public function send(Branch $branch): array
    {
        if (! (bool) Setting::resolve($branch->organisation_id, $branch->id, 'alerts', 'email_digest_enabled', true)) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $alerts = Alert::where('branch_id', $branch->id)->open()->whereNull('acknowledged_at')
            ->orderByRaw("FIELD(severity, 'CRITICAL', 'WARNING', 'INFO')")
            ->orderByRaw('due_date IS NULL, due_date')
            ->get();

        if ($alerts->isEmpty()) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $registrar = app(PermissionRegistrar::class);
        $sent = 0;
        $skipped = 0;

        try {
            foreach ($this->candidates($branch) as $user) {
                $registrar->setPermissionsTeamId($branch->id);
                $user->unsetRelation('roles')->unsetRelation('permissions');

                $permissions = array_values(array_filter(self::ALERT_PERMISSIONS, fn (string $p) => $user->can($p)));
                $theirs = $alerts->whereIn('permission', $permissions);

                if ($theirs->isEmpty()) {
                    $skipped++;

                    continue;
                }

                try {
                    Mail::to($user->email, $user->name)->send(
                        new AlertDigestMail($user->name, "{$branch->code} · {$branch->name}", $theirs->values(), (string) config('app.url'))
                    );
                    $sent++;
                } catch (\Throwable $e) {
                    // One bad address must not stop the rest of the round.
                    Log::error('Alert digest failed', ['user_id' => $user->id, 'branch' => $branch->code, 'error' => $e->getMessage()]);
                    $skipped++;
                }
            }
        } finally {
            $registrar->setPermissionsTeamId(null);
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * Active users who hold a role in this branch, or a role that spans every
     * branch.
     *
     * @return Collection<int, User>
     */
    private function candidates(Branch $branch): Collection
    {
        $names = config('permission.table_names');
        $team = config('permission.column_names.team_foreign_key');

        $ids = DB::table($names['model_has_roles'])
            ->where('model_type', (new User)->getMorphClass())
            ->where(fn ($q) => $q->where($team, $branch->id)->orWhereNull($team))
            ->distinct()->pluck('model_id');

        return User::whereIn('id', $ids)->where('is_active', true)->whereNotNull('email')->orderBy('name')->get();
    }
}
