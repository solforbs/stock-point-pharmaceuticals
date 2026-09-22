<?php

namespace App\Http\Controllers\Api;

use App\Models\AssistantSession;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Assistant\AssistantAuthenticator;
use App\Services\Assistant\AssistantCommands;
use App\Services\Assistant\AssistantPermissionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The assistant that answers from the sign-in page, before anyone signs in.
 *
 * Three steps: ask for a code, verify it, then run commands. Every answer is
 * read-only, drawn from the same queries and gated by the same permissions
 * as the screen that shows it, and confined to the user's own institution
 * and branch by the ordinary tenant middleware.
 */
class AssistantController extends ApiController
{
    /** POST /api/assistant/request-code */
    public function requestCode(Request $request, AssistantAuthenticator $assistant): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $assistant->requestCode($data['email'], $request);

        // Deliberately the same answer whether or not that address has an
        // account, so the chat box cannot be used to find out who works here.
        return response()->json([
            'sent' => true,
            'message' => 'If that address belongs to an account, a six-digit code is on its way. It expires in '.AssistantSession::CODE_MINUTES.' minutes.',
        ]);
    }

    /** POST /api/assistant/verify */
    public function verify(Request $request, AssistantAuthenticator $assistant): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $verified = $assistant->verify($data['email'], $data['code']);
        if ($verified === null) {
            return $this->error('ASSISTANT_CODE_INVALID', 'That code is wrong or has expired. Ask for a new one.', 422, ['field' => 'code']);
        }

        ['token' => $token, 'session' => $session, 'user' => $user] = $verified;

        // The command list is fetched with the token, so it is built inside
        // the same tenant and branch context every answer is built in.
        return response()->json([
            'session_token' => $token,
            'expires_at' => $session->token_expires_at?->toIso8601String(),
            'user' => ['name' => $user->name, 'email' => $user->email],
        ]);
    }

    /** POST /api/assistant/ask — one command, one answer. */
    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate(['command' => ['required', 'string', 'max:50']]);
        $user = $request->user();
        $commands = new AssistantCommands($this->organisationId($request), $this->branchId($request));

        try {
            $answer = $commands->run($data['command'], $user);
        } catch (AssistantPermissionException $e) {
            return $this->error('FORBIDDEN', $e->getMessage(), 403);
        }

        if ($answer === null) {
            return $this->error('ASSISTANT_UNKNOWN_COMMAND', 'I do not know that one. Type /help to see what I can answer.', 422, [
                'field' => 'command',
                'commands' => $commands->availableTo($user),
            ]);
        }

        $session = $request->attributes->get('assistant_session');
        if ($session instanceof AssistantSession) {
            $session->increment('queries');
        }
        AuditLog::record('ASSISTANT_QUERY', 'assistant_session', (string) ($session?->id ?? '—'), [
            'reference' => $answer['command'],
            'after_json' => ['rows' => count($answer['rows'])],
        ]);

        return response()->json($answer);
    }

    /** GET /api/assistant/commands — what this session may ask. */
    public function commands(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->commandsFor($request, $request->user())]);
    }

    /** POST /api/assistant/end */
    public function end(Request $request): JsonResponse
    {
        $session = $request->attributes->get('assistant_session');
        if ($session instanceof AssistantSession) {
            app(AssistantAuthenticator::class)->end($session);
        }

        return response()->json(['ended' => true]);
    }

    /**
     * @return list<array{command: string, title: string, hint: string}>
     */
    private function commandsFor(Request $request, User $user): array
    {
        return (new AssistantCommands($this->organisationId($request), $this->branchId($request)))->availableTo($user);
    }
}
