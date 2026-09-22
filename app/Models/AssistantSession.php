<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One assistant sign-in: the emailed code, the tries against it, and the
 * read-only session it becomes once the code matches.
 *
 * The session is deliberately not a Sanctum token. It answers a fixed list
 * of read-only commands and nothing else, so a mailbox can never be used to
 * do what the password protects.
 *
 * @property-read string $email
 * @property-read int $attempts
 * @property-read Carbon $code_expires_at
 * @property-read Carbon|null $token_expires_at
 * @property-read Carbon|null $verified_at
 * @property-read Carbon|null $revoked_at
 */
class AssistantSession extends Model
{
    use HasUuids;

    /** How long an emailed code is worth typing. */
    public const CODE_MINUTES = 10;

    /** How long the session answers commands before the code is needed again. */
    public const SESSION_MINUTES = 30;

    /** Wrong codes allowed before the code is burned. */
    public const MAX_ATTEMPTS = 5;

    /** Codes requested from one address within the hour. */
    public const MAX_CODES_PER_HOUR = 5;

    protected $fillable = [
        'email', 'user_id', 'code_hash', 'attempts', 'code_expires_at', 'verified_at',
        'token_hash', 'token_expires_at', 'last_used_at', 'revoked_at', 'ip', 'user_agent', 'queries',
    ];

    protected $hidden = ['code_hash', 'token_hash'];

    protected function casts(): array
    {
        return [
            'code_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    /** True while the code may still be typed. */
    public function codeIsOpen(): bool
    {
        return $this->verified_at === null
            && $this->revoked_at === null
            && $this->attempts < self::MAX_ATTEMPTS
            && $this->code_expires_at->isFuture();
    }

    /** True while the session may still answer commands. */
    public function isLive(): bool
    {
        return $this->verified_at !== null
            && $this->revoked_at === null
            && $this->token_expires_at !== null
            && $this->token_expires_at->isFuture();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
