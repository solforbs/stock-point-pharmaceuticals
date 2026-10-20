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
        return static::create(array_merge(static::context($attributes), [
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ], $attributes));
    }

    /**
     * Record one entry per entity in a single insert — the bulk form of
     * record(), for operations that touch thousands of rows at once.
     *
     * @param  array<string, array<string, mixed>>  $entries  attributes keyed by entity id
     */
    public static function recordMany(string $action, string $entityType, array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $context = static::context(reset($entries));
        $model = new static;
        $rows = [];
        foreach ($entries as $entityId => $attributes) {
            // Filled through the model so casts (dates, JSON) apply exactly as in create().
            $row = (new static)->forceFill(array_merge($context, [
                'id' => $model->newUniqueId(),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => (string) $entityId,
            ], $attributes))->getAttributes();
            $rows[] = $row;
        }

        // Every row carries the same columns, which a multi-row insert requires.
        $columns = array_unique(array_merge(...array_map('array_keys', $rows)));
        $rows = array_map(fn (array $row) => array_merge(array_fill_keys($columns, null), $row), $rows);

        foreach (array_chunk($rows, 500) as $chunk) {
            static::query()->insert($chunk);
        }
    }

    /**
     * Who, where and from which request — the columns every entry shares.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function context(array $attributes): array
    {
        $user = Auth::user();

        return [
            'occurred_at' => now(),
            // The acting institution: the request's, else that of the user the
            // event is about (a failed second factor happens before sign-in).
            'organisation_id' => app(TenantContext::class)->organisationId()
                ?? $user?->organisation_id
                ?? (isset($attributes['user_id']) ? User::whereKey($attributes['user_id'])->value('organisation_id') : null),
            'user_id' => $user?->getKey(),
            'username_snapshot' => $user ? ($user->username ?? $user->name) : null,
            'branch_id' => RequestFacade::instance()->attributes->get('active_branch_id'),
            'ip' => RequestFacade::ip(),
            'user_agent' => RequestFacade::userAgent(),
            'request_id' => RequestFacade::header('X-Request-Id') ?? (string) Str::uuid(),
        ];
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
