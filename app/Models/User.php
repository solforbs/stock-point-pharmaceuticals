<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\Tenancy\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property-read int $id
 * @property-read string|null $username
 * @property-read string $email
 * @property-read bool $is_active
 * @property-read string|null $organisation_id the institution; NULL for a platform-only account
 * @property-read bool $is_platform_admin runs the platform itself (backups, deployment, tenants)
 * @property-read bool $mfa_required
 * @property-read string|null $mfa_secret
 * @property-read int $failed_login_attempts
 * @property-read Carbon|null $locked_until
 * @property-read Carbon|null $last_login_at
 */
#[Fillable(['name', 'username', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token', 'mfa_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /** Mirrors the column default, so a freshly created instance is not read as deactivated. */
    protected $attributes = ['is_active' => true];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mfa_secret' => 'encrypted', // Part 18.2: TOTP secret encrypted at rest
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
            'mfa_required' => 'boolean',
            'must_change_password' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * A user created while acting for an institution belongs to it. Users
     * are deliberately not globally scoped: authentication must find any
     * account by email, so tenant filtering of users is explicit.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->organisation_id)) {
                $user->organisation_id = app(TenantContext::class)->organisationId();
            }
        });
    }

    /**
     * Signs the account out everywhere: stored sessions, the "remember me"
     * cookie, API tokens and assistant passes. Reactivating the account later
     * must not revive any of them.
     */
    public function endAllSessions(): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table'))->where('user_id', $this->getKey())->delete();
        }

        $this->forceFill(['remember_token' => Str::random(60)])->save();
        $this->tokens()->delete();
        AssistantSession::where('user_id', $this->getKey())->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
