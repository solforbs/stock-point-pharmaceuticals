<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/**
 * Part 18.2 / V6 17.2 — session login for the first-party SPA with the
 * blueprint's controls: Argon2id hashing (config), lockout after five
 * failures with exponential back-off, TOTP second factor for roles that
 * require it, and an audit entry for every attempt. The error message
 * never reveals whether the username exists (Part 22.1).
 */
class AuthController extends Controller
{
    private const LOCKOUT_THRESHOLD = 5;

    private const LOCKOUT_BASE_MINUTES = 15;

    private const CREDENTIALS_MESSAGE = 'Incorrect username or password.';

    public function __construct(private readonly Google2FA $google2fa) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            $this->audit('LOGIN_LOCKED', $user);

            return response()->json([
                'error' => [
                    'code' => 'ACCOUNT_LOCKED',
                    'message' => 'Too many attempts — try again in '.max(1, (int) ceil(now()->diffInMinutes($user->locked_until, true))).' minutes.',
                    'details' => ['locked_until' => $user->locked_until->toIso8601String()],
                ],
            ], 423);
        }

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            if ($user) {
                $this->recordFailure($user);
            }

            throw ValidationException::withMessages(['email' => self::CREDENTIALS_MESSAGE]);
        }

        if ($user->mfa_required && $user->mfa_secret) {
            $request->session()->put('mfa.pending_user_id', $user->id);
            $request->session()->put('mfa.remember', $request->boolean('remember'));

            return response()->json(['mfa_required' => true, 'message' => 'Enter the code from your authenticator app.'], 202);
        }

        return $this->finishLogin($request, $user, $request->boolean('remember'));
    }

    /**
     * Second factor. Two cases share one endpoint (Part 21.2 /auth/mfa/verify):
     * a pending login supplies the code to finish signing in; a signed-in
     * user supplies the first code from a new authenticator to enable MFA.
     */
    public function mfaVerify(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);

        $pendingId = $request->session()->get('mfa.pending_user_id');
        $user = $pendingId ? User::find($pendingId) : $request->user();

        if (! $user || ! $user->mfa_secret) {
            throw ValidationException::withMessages(['code' => 'No authenticator is set up for this account.']);
        }

        if (! $this->google2fa->verifyKey($user->mfa_secret, $data['code'])) {
            $this->audit('MFA_FAILED', $user);
            throw ValidationException::withMessages(['code' => 'That code is not valid.']);
        }

        if ($pendingId) {
            $remember = (bool) $request->session()->pull('mfa.remember', false);
            $request->session()->forget('mfa.pending_user_id');

            return $this->finishLogin($request, $user, $remember);
        }

        $user->forceFill(['mfa_required' => true])->save();
        $this->audit('MFA_ENABLED', $user);

        return response()->json(['mfa_required' => true]);
    }

    /**
     * Generates a fresh TOTP secret for the signed-in user. MFA is only
     * enforced once the user proves the authenticator works via mfaVerify.
     */
    public function mfaSetup(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->google2fa->generateSecretKey(32);

        $user->forceFill(['mfa_secret' => $secret, 'mfa_required' => false])->save();
        $this->audit('MFA_SETUP_STARTED', $user);

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret),
        ]);
    }

    public function logout(Request $request): Response
    {
        $user = $request->user();

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user) {
            $this->audit('LOGOUT', $user);
        }

        return response()->noContent();
    }

    private function finishLogin(Request $request, User $user, bool $remember): JsonResponse
    {
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null, 'last_login_at' => now()])->save();
        $this->audit('LOGIN_SUCCESS', $user);

        return response()->json($user->fresh()->load('roles', 'permissions'));
    }

    /**
     * Part 18.2 — 5 failed attempts → 15 minute lock, exponential thereafter.
     */
    private function recordFailure(User $user): void
    {
        $attempts = (int) $user->failed_login_attempts + 1;
        $update = ['failed_login_attempts' => $attempts];

        if ($attempts % self::LOCKOUT_THRESHOLD === 0) {
            $lockNumber = intdiv($attempts, self::LOCKOUT_THRESHOLD);
            $update['locked_until'] = now()->addMinutes(self::LOCKOUT_BASE_MINUTES * (2 ** ($lockNumber - 1)));
        }

        $user->forceFill($update)->save();
        $this->audit('LOGIN_FAILED', $user, ['attempts' => $attempts, 'locked_until' => $user->locked_until?->toIso8601String()]);
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function audit(string $action, User $user, array $after = []): void
    {
        AuditLog::record($action, 'user', (string) $user->id, [
            'user_id' => $user->id,
            'username_snapshot' => $user->username ?? $user->name,
            'reference' => $user->email,
            'after_json' => $after ?: null,
        ]);
    }
}
