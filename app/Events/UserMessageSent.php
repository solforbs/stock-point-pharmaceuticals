<?php

namespace App\Events;

use App\Models\UserMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Part 17 — a message reaching its recipient the moment it is sent. It goes
 * to that person's private channel, or to the branch channel when it is
 * addressed to everyone.
 *
 * The payload is deliberately small and already permitted: it carries only
 * what the recipient may see anyway, so nothing leaks through the socket
 * that the API would have refused.
 */
class UserMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public UserMessage $message) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            $this->message->recipient_id === null
                ? new PrivateChannel('branches.'.$this->message->branch_id)
                : new PrivateChannel('users.'.$this->message->recipient_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => (string) $this->message->id,
            'subject' => $this->message->subject,
            'body' => $this->message->body,
            'priority' => $this->message->priority,
            'link' => $this->message->link,
            'created_at' => $this->message->created_at?->toIso8601String(),
            'sender' => [
                'id' => $this->message->sender_id,
                'name' => $this->message->sender?->name,
            ],
            'to_branch' => $this->message->recipient_id === null,
        ];
    }
}
