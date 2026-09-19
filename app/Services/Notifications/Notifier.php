<?php

namespace App\Services\Notifications;

use App\Events\UserMessageSent;
use App\Models\User;
use App\Models\UserMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Part 17 — one way for the application to tell people something happened.
 *
 * Every workflow notification goes through here, so they all behave the
 * same: stored (it survives a closed browser), broadcast (it arrives without
 * one), and addressed by permission rather than by name — the people who may
 * approve a requisition are whoever holds `requisition.approve` today, not a
 * list someone has to remember to update.
 */
class Notifier
{
    /**
     * Tells everyone who holds a permission in a branch. The person who
     * caused the event is skipped: nobody needs telling what they just did.
     *
     * @return int how many people were told
     */
    public function toPermission(
        string $permission,
        string $branchId,
        string $subject,
        string $body,
        string $category,
        ?string $link = null,
        string $priority = 'NORMAL',
        ?int $exceptUserId = null,
    ): int {
        return $this->toUsers($this->holdersOf($permission, $branchId, $exceptUserId), $branchId, $subject, $body, $category, $link, $priority);
    }

    /**
     * @param  Collection<int, User>|array<int, User>  $users
     */
    public function toUsers(
        Collection|array $users,
        string $branchId,
        string $subject,
        string $body,
        string $category,
        ?string $link = null,
        string $priority = 'NORMAL',
    ): int {
        $sent = 0;

        foreach ($users as $user) {
            $message = UserMessage::create([
                'branch_id' => $branchId,
                'sender_id' => null, // raised by the application, not a person
                'recipient_id' => $user->id,
                'subject' => $subject,
                'body' => $body,
                'category' => $category,
                'priority' => $priority,
                'link' => $link,
            ]);

            try {
                broadcast(new UserMessageSent($message));
            } catch (\Throwable $e) {
                // The websocket being down must never fail the business
                // action that triggered it; the message is already stored.
                Log::warning('Notification broadcast failed', ['message_id' => $message->id, 'error' => $e->getMessage()]);
            }

            $sent++;
        }

        return $sent;
    }

    /**
     * Active users holding a permission in this branch (or in a role that
     * spans every branch).
     *
     * @return Collection<int, User>
     */
    public function holdersOf(string $permission, string $branchId, ?int $exceptUserId = null): Collection
    {
        $names = config('permission.table_names');
        $team = config('permission.column_names.team_foreign_key');

        $ids = DB::table($names['model_has_roles'])
            ->where('model_type', (new User)->getMorphClass())
            ->where(fn ($q) => $q->where($team, $branchId)->orWhereNull($team))
            ->distinct()->pluck('model_id');

        return User::whereIn('id', $ids)
            ->where('is_active', true)
            ->when($exceptUserId, fn ($q, $id) => $q->whereKeyNot($id))
            ->get()
            ->filter(fn (User $user) => $this->can($user, $permission, $branchId))
            ->values();
    }

    /** Permission check in the context of one branch. */
    private function can(User $user, string $permission, string $branchId): bool
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($branchId);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $can = $user->can($permission);

        $registrar->setPermissionsTeamId(null);

        return $can;
    }
}
