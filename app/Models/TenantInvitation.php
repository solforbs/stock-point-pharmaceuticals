<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A one-time link emailed by the platform: REGISTER lets an approved
 * prospect set up their institution, ACTIVATE lets the admin of an
 * institution the platform created choose a password. The link dies the
 * moment it is opened; opening it starts a short form session instead.
 *
 * @property-read string $purpose
 * @property-read string $email
 * @property-read Carbon $expires_at
 * @property-read Carbon|null $opened_at
 * @property-read Carbon|null $session_expires_at
 * @property-read Carbon|null $used_at
 * @property-read Carbon|null $revoked_at
 */
class TenantInvitation extends Model
{
    use HasUuids;

    /** How long an unopened link stays valid. */
    public const LINK_DAYS = 7;

    /** How long the form stays open once the link has been opened. */
    public const SESSION_MINUTES = 60;

    protected $fillable = [
        'purpose', 'email', 'tenant_request_id', 'organisation_id', 'user_id', 'token_hash', 'expires_at', 'created_by',
    ];

    protected $hidden = ['token_hash', 'session_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'opened_at' => 'datetime',
            'session_expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<TenantRequest, $this>
     */
    public function tenantRequest(): BelongsTo
    {
        return $this->belongsTo(TenantRequest::class);
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
