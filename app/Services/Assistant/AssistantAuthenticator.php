<?php

namespace App\Services\Assistant;

use App\Mail\AssistantCodeMail;
use App\Models\AssistantSession;
use App\Models\AuditLog;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Signing in to the assistant with an emailed code.
 *
 * Two rules shape this class. An address that has no account must be
 * answered exactly like one that has, or the chat box becomes a way to
 * discover who works here. And the session it issues is not a login: it is
 * a read-only pass to a fixed list of questions, short-lived and revocable,
 * so a mailbox can never do what a password does.
 */
class AssistantAuthenticator
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * Sends a code if the address belongs to an active user of an active
     * institution. The caller is told nothing either way.
     */
    public function requestCode(string $email, Request $request): void
    {
        $email = strtolower(trim($email));

        $recent = AssistantSession::where('email', $email)->where('created_at', '>=', now()->subHour())->count();
        if ($recent >= AssistantSession::MAX_CODES_PER_HOUR) {
            return;
        }

        $user = User::where('email', $email)->first();
        if ($user !== null && ! $this->mayUseAssistant($user)) {
            $user = null;
        }

        $code = (string) random_int(100000, 999999);
        $session = AssistantSession::create([
            'email' => $email,
            'user_id' => $user?->id,
            'code_hash' => AssistantSession::hash($code),
            'code_expires_at' => now()->addMinutes(AssistantSession::CODE_MINUTES),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        // Only a real account is written to, and only ever to the address on
        // that account. An unknown address leaves a row and sends nothing.
        if ($user !== null) {
            Mail::to($user->email)->send(new AssistantCodeMail($user, $code, $session));
        }
    }

    /**
     * @return array{token: string, session: AssistantSession, user: User}|null Null when the code is wrong, stale or spent.
     */
    public function verify(string $email, string $code): ?array
    {
        $email = strtolower(trim($email));

        return DB::transaction(function () use ($email, $code) {
            $session = AssistantSession::where('email', $email)
                ->whereNull('verified_at')
                ->whereNull('revoked_at')
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if ($session === null || ! $session->codeIsOpen()) {
                return null;
            }

            // Counted before it is compared, so a wrong guess costs a try
            // even if the request is abandoned midway.
            $session->increment('attempts');

            if (! hash_equals($session->code_hash, AssistantSession::hash(trim($code)))) {
                return null;
            }

            $user = $session->user_id === null ? null : User::find($session->user_id);
            if ($user === null || ! $this->mayUseAssistant($user)) {
                return null;
            }

            $token = Str::random(48);
            $session->forceFill([
                'verified_at' => now(),
                'token_hash' => AssistantSession::hash($token),
                'token_expires_at' => now()->addMinutes(AssistantSession::SESSION_MINUTES),
                'last_used_at' => now(),
            ])->save();

            $this->tenant->run((string) $user->organisation_id, fn () => AuditLog::record('ASSISTANT_SIGNED_IN', 'assistant_session', $session->id, [
                'reference' => $user->email,
                'after_json' => ['ip' => $session->ip, 'expires_at' => $session->token_expires_at?->toIso8601String()],
            ]));

            return ['token' => $token, 'session' => $session, 'user' => $user];
        });
    }

    /** The live session behind a token, or null. */
    public function sessionFor(string $token): ?AssistantSession
    {
        $session = AssistantSession::where('token_hash', AssistantSession::hash($token))->first();

        return $session?->isLive() === true ? $session : null;
    }

    public function end(AssistantSession $session): void
    {
        $session->forceFill(['revoked_at' => now()])->save();
    }

    /**
     * Who the assistant will talk to at all: an active user who belongs to an
     * institution that is not suspended. Platform accounts are excluded —
     * they have no institution to answer about.
     */
    private function mayUseAssistant(User $user): bool
    {
        if (! $user->is_active || $user->organisation_id === null) {
            return false;
        }

        $organisation = $user->organisation;

        return $organisation !== null && $organisation->accessState() !== Organisation::ACCESS_SUSPENDED;
    }
}
