<?php

namespace Tests\Feature\Api;

use App\Events\UserMessageSent;
use App\Models\Branch;
use App\Models\User;
use App\Models\UserMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 17 — messages between people: stored so they survive a closed
 * browser, and broadcast so they arrive without one.
 */
class MessagesHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    private User $colleague;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->colleague = $this->colleague([
            'name' => 'Counter Two', 'username' => 'counter2', 'email' => 'counter2@stockpoint.test',
            'password' => Hash::make('a-long-enough-password'), 'is_active' => true,
        ]);
        $this->grantPermissions(['sale.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_a_direct_message_is_stored_and_broadcast_to_that_person_alone(): void
    {
        Event::fake([UserMessageSent::class]);

        $this->postJson('/api/messages', [
            'recipient_id' => $this->colleague->id,
            'subject' => 'Price override needed',
            'body' => 'Till 2 has a customer waiting.',
            'priority' => 'HIGH',
        ])->assertCreated()->assertJsonPath('priority', 'HIGH');

        Event::assertDispatched(UserMessageSent::class, function (UserMessageSent $event) {
            $channels = $event->broadcastOn();
            $this->assertSame('private-users.'.$this->colleague->id, (string) $channels[0]);
            $this->assertSame('message.sent', $event->broadcastAs());
            $this->assertSame('Price override needed', $event->broadcastWith()['subject']);

            return true;
        });

        $this->assertSame(1, UserMessage::where('recipient_id', $this->colleague->id)->count());
    }

    public function test_a_message_to_everyone_goes_to_the_branch_channel(): void
    {
        Event::fake([UserMessageSent::class]);

        $this->postJson('/api/messages', ['subject' => 'Stock take at 5', 'body' => 'Please close your tills.'])
            ->assertCreated()->assertJsonPath('recipient_id', null);

        Event::assertDispatched(UserMessageSent::class, function (UserMessageSent $event) {
            $this->assertSame('private-branches.'.$this->branch->id, (string) $event->broadcastOn()[0]);
            $this->assertTrue($event->broadcastWith()['to_branch']);

            return true;
        });
    }

    public function test_the_inbox_shows_direct_and_branch_messages_but_never_another_persons_post(): void
    {
        $mine = UserMessage::create([
            'branch_id' => $this->branch->id, 'sender_id' => $this->colleague->id, 'recipient_id' => $this->user->id,
            'subject' => 'For you', 'body' => 'Only you.', 'priority' => 'NORMAL',
        ]);
        $everyone = UserMessage::create([
            'branch_id' => $this->branch->id, 'sender_id' => $this->colleague->id, 'recipient_id' => null,
            'subject' => 'For all', 'body' => 'Everyone.', 'priority' => 'NORMAL',
        ]);
        $theirs = UserMessage::create([
            'branch_id' => $this->branch->id, 'sender_id' => $this->user->id, 'recipient_id' => $this->colleague->id,
            'subject' => 'Not for you', 'body' => 'Private to them.', 'priority' => 'NORMAL',
        ]);

        $ids = collect($this->getJson('/api/messages')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertTrue($ids->contains($everyone->id));
        $this->assertFalse($ids->contains($theirs->id), 'a message addressed to someone else is never listed');

        $this->getJson('/api/messages/unread-count')->assertOk()->assertJsonPath('unread', 2);

        $this->postJson("/api/messages/{$mine->id}/read")->assertOk()->assertJsonPath('read_at', fn ($v) => $v !== null);
        $this->getJson('/api/messages/unread-count')->assertOk()->assertJsonPath('unread', 1);

        $this->postJson("/api/messages/{$theirs->id}/read")->assertNotFound();

        $this->postJson('/api/messages/read-all')->assertOk()->assertJsonPath('read', 1);
        $this->getJson('/api/messages/unread-count')->assertOk()->assertJsonPath('unread', 0);
    }

    public function test_a_message_from_another_branch_is_not_in_this_branchs_inbox(): void
    {
        $other = Branch::create([
            'organisation_id' => $this->org->id, 'code' => 'NBO', 'name' => 'Nairobi', 'is_active' => true,
            'retail_enabled' => true, 'wholesale_enabled' => true,
        ]);
        UserMessage::create([
            'branch_id' => $other->id, 'sender_id' => $this->colleague->id, 'recipient_id' => $this->user->id,
            'subject' => 'Nairobi only', 'body' => 'Different branch.', 'priority' => 'NORMAL',
        ]);

        $this->getJson('/api/messages')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/messages/unread-count')->assertOk()->assertJsonPath('unread', 0);
    }

    public function test_signing_out_ends_the_conversation(): void
    {
        app('auth')->forgetGuards();

        $this->getJson('/api/messages')->assertStatus(401);
        $this->postJson('/api/messages', ['subject' => 'x', 'body' => 'y'])->assertStatus(401);
    }
}
