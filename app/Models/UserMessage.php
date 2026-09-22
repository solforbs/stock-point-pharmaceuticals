<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenantBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Part 17 — one message from a person to another, or to a whole branch.
 * Broadcast when it is created; kept so it survives a closed browser.
 */
class UserMessage extends Model
{
    use BelongsToTenantBranch, HasUuids;

    public const PRIORITIES = ['NORMAL', 'HIGH', 'URGENT'];

    protected $fillable = [
        'branch_id', 'sender_id', 'recipient_id', 'subject', 'body', 'priority', 'category', 'link', 'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Messages this user should see: their own, and the ones addressed to
     * everyone in the branch.
     *
     * @param  Builder<UserMessage>  $query
     * @return Builder<UserMessage>
     */
    public function scopeVisibleTo(Builder $query, User $user, string $branchId): Builder
    {
        return $query->where('branch_id', $branchId)
            ->where(fn (Builder $q) => $q->where('recipient_id', $user->id)->orWhereNull('recipient_id'));
    }
}
