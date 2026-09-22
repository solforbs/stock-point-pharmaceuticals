<?php

namespace App\Http\Controllers\Api;

use App\Events\UserMessageSent;
use App\Models\User;
use App\Models\UserMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Part 17 — messages between the people using the system. Anyone signed in
 * may send one; the recipient gets it over the websocket at once and finds
 * it waiting here next time they sign in. Messages are marked read, never
 * deleted, so "nobody told me" can always be checked.
 */
class MessageController extends ApiController
{
    /** GET /api/messages?unread_only=&per_page= */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $messages = UserMessage::visibleTo($user, $this->branchId($request))
            ->when($request->boolean('unread_only'), fn ($q) => $q->whereNull('read_at'))
            ->with(['sender:id,name,username', 'recipient:id,name'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($request->integer('per_page', 30));

        return response()->json($messages);
    }

    /** GET /api/messages/unread-count — what the bell shows. */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = UserMessage::visibleTo($request->user(), $this->branchId($request))
            ->whereNull('read_at')->count();

        return response()->json(['unread' => $count]);
    }

    /** POST /api/messages */
    public function store(Request $request): JsonResponse
    {
        $branchId = $this->branchId($request);

        $data = $request->validate([
            // Null addresses everyone working in the branch.
            'recipient_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)->where('organisation_id', $this->organisationId($request))],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(UserMessage::PRIORITIES)],
            'link' => ['nullable', 'string', 'max:255'],
        ]);

        $message = UserMessage::create([
            ...$data,
            'branch_id' => $branchId,
            'sender_id' => $request->user()->id,
            'priority' => $data['priority'] ?? 'NORMAL',
        ]);

        broadcast(new UserMessageSent($message->load('sender:id,name,username')))->toOthers();

        return response()->json($message->load(['sender:id,name,username', 'recipient:id,name']), 201);
    }

    /** POST /api/messages/{message}/read */
    public function markRead(Request $request, string $message): JsonResponse
    {
        $message = UserMessage::visibleTo($request->user(), $this->branchId($request))->findOrFail($message);

        if ($message->read_at === null) {
            $message->update(['read_at' => now()]);
        }

        return response()->json($message);
    }

    /** POST /api/messages/read-all */
    public function markAllRead(Request $request): JsonResponse
    {
        $count = UserMessage::visibleTo($request->user(), $this->branchId($request))
            ->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['read' => $count]);
    }

    /** GET /api/messages/recipients — who can be written to in this branch. */
    public function recipients(Request $request): JsonResponse
    {
        $names = config('permission.table_names');
        $team = config('permission.column_names.team_foreign_key');
        $branchId = $this->branchId($request);

        $ids = DB::table($names['model_has_roles'])
            ->where('model_type', (new User)->getMorphClass())
            ->where(fn ($q) => $q->where($team, $branchId)->orWhereNull($team))
            ->distinct()->pluck('model_id');

        return response()->json(
            User::whereIn('id', $ids)->where('organisation_id', $this->organisationId($request))->where('is_active', true)
                ->whereKeyNot($request->user()->id)
                ->orderBy('name')->get(['id', 'name', 'username'])
        );
    }
}
