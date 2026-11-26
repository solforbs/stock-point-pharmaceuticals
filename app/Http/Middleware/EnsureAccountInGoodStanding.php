<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Part 18.2 — signing in is not enough; the account must still be good on
 * every request. A deactivated account loses its session at once, and an
 * account holding a temporary password can do nothing but replace it.
 * Runs directly after auth:sanctum.
 */
class EnsureAccountInGoodStanding
{
    /** Reachable with a temporary password: who am I, and replacing it. */
    private const OPEN_WITH_TEMPORARY_PASSWORD = ['api/user', 'api/account/password'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            if ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return $this->refuse('ACCOUNT_DISABLED', 'This account has been deactivated.', 401);
        }

        if ($user?->must_change_password && ! $request->is(...self::OPEN_WITH_TEMPORARY_PASSWORD)) {
            return $this->refuse('PASSWORD_CHANGE_REQUIRED', 'Choose a new password before carrying on.', 403);
        }

        return $next($request);
    }

    private function refuse(string $code, string $message, int $status): Response
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'details' => (object) []]], $status);
    }
}
