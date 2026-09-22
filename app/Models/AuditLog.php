<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    use BelongsToOrganisation, HasUuids;

    public $timestamps = false; // append-only — occurred_at is the only timestamp

    protected $fillable = [
        'organisation_id', 'occurred_at', 'user_id', 'username_snapshot', 'branch_id', 'terminal_id',
        'ip', 'user_agent', 'action', 'entity_type', 'entity_id', 'reference',
        'before_json', 'after_json', 'changed_fields', 'reason', 'approval_id', 'request_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'before_json' => 'array',
            'after_json' => 'array',
            'changed_fields' => 'array',
        ];
    }

    /**
     * Record an audit entry. This is the only supported way to write to this
     * table — rows are never updated or deleted once written (Part 19.1).
     */
    public static function record(string $action, string $entityType, string $entityId, array $attributes = []): self
    {
        $user = Auth::user();
        $username = $user ? ($user->username ?? $user->name) : null;

        return static::create(array_merge([
            'occurred_at' => now(),
            // The acting institution: the request's, else that of the user the
            // event is about (a failed second factor happens before sign-in).
            'organisation_id' => app(TenantContext::class)->organisationId()
                ?? $user?->organisation_id
                ?? (isset($attributes['user_id']) ? User::whereKey($attributes['user_id'])->value('organisation_id') : null),
            'user_id' => $user?->getKey(),
            'username_snapshot' => $username,
            'branch_id' => RequestFacade::instance()->attributes->get('active_branch_id'),
            'ip' => RequestFacade::ip(),
            'user_agent' => RequestFacade::userAgent(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'request_id' => RequestFacade::header('X-Request-Id') ?? (string) Str::uuid(),
        ], $attributes));
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('audit_logs is append-only — records are never updated.');
    }

    public function delete(): ?bool
    {
        throw new \LogicException('audit_logs is append-only — records are never deleted.');
    }
}
