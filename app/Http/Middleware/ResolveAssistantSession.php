<?php

namespace App\Http\Middleware;

use App\Services\Assistant\AssistantAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns an assistant token into the user behind it, so everything after this
 * point — the tenant scope, the branch team, the permission checks — is the
 * same machinery the signed-in application uses. The token is accepted only
 * on the assistant's own routes, and it is never a Sanctum token, so it
 * cannot be replayed against the rest of the API.
 */
class ResolveAssistantSession
{
    public function __construct(private readonly AssistantAuthenticator $assistant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: (string) $request->input('session_token');
        $session = $token === '' ? null : $this->assistant->sessionFor($token);

        if ($session === null || $session->user === null) {
            return response()->json(['error' => [
                'code' => 'ASSISTANT_SESSION_EXPIRED',
                'message' => 'This assistant session has ended. Ask for a new code to carry on.',
                'details' => (object) [],
            ]], 401);
        }

        $session->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('assistant_session', $session);
        $request->setUserResolver(fn () => $session->user);

        return $next($request);
    }
}
