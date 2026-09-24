<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 17 — a private channel is only as private as routes/channels.php.
 * These are the rules that stop a websocket becoming a way around the API.
 *
 * They are exercised through the broadcaster itself rather than over HTTP:
 * the suite's `null` broadcaster permits everything, so only the driver
 * production uses proves anything here.
 */
class BroadcastChannelTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['sale.view']);

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        // routes/channels.php was read at boot and its rules were registered
        // on the broadcaster that was default then (`null`). Reading it again
        // puts them on the one we just switched to.
        require base_path('routes/channels.php');
    }

    /** True when that user is allowed to listen to that channel. */
    private function mayJoin(?User $user, string $channel): bool
    {
        $request = Request::create('/broadcasting/auth', 'POST', ['channel_name' => $channel, 'socket_id' => '1.1']);
        $request->setUserResolver(fn () => $user);

        try {
            app(Broadcaster::class)->auth($request);

            return true;
        } catch (AccessDeniedHttpException) {
            return false;
        }
    }

    public function test_a_user_may_join_their_own_channel_and_nobody_elses(): void
    {
        $stranger = User::create([
            'name' => 'Someone Else', 'username' => 'stranger', 'email' => 'stranger@stockpoint.test',
            'password' => Hash::make('a-long-enough-password'), 'is_active' => true,
        ]);

        $this->assertTrue($this->mayJoin($this->user, 'private-users.'.$this->user->id));
        $this->assertFalse($this->mayJoin($this->user, 'private-users.'.$stranger->id), 'nobody reads another person\'s messages');
    }

    public function test_only_people_with_a_role_in_a_branch_may_listen_to_it(): void
    {
        // buildWorld gave this user a role in its branch.
        $this->assertTrue($this->mayJoin($this->user, 'private-branches.'.$this->branch->id));

        $other = Branch::create([
            'organisation_id' => $this->org->id, 'code' => 'NBO', 'name' => 'Nairobi', 'is_active' => true,
            'retail_enabled' => true, 'wholesale_enabled' => true,
        ]);
        $this->assertFalse($this->mayJoin($this->user, 'private-branches.'.$other->id), 'Lodwar never hears Nairobi');
    }

    public function test_a_stranger_cannot_join_anything(): void
    {
        $this->assertFalse($this->mayJoin(null, 'private-users.'.$this->user->id));
        $this->assertFalse($this->mayJoin(null, 'private-branches.'.$this->branch->id));
    }

    /**
     * Exercised over HTTP on purpose: the healthcare channel checks module
     * permissions, which are team-scoped to the active branch. Only the real
     * route proves branch.context runs on /broadcasting/auth — calling the
     * broadcaster directly (as the tests above do) would pass even without
     * that middleware, which is exactly the regression this guards against.
     */
    public function test_a_lab_tech_may_join_the_healthcare_channel_over_http(): void
    {
        $this->grantPermissions(['laboratory.view'], 'Laboratory Technician');

        $this->actingAs($this->user)
            ->withHeader('X-Branch-Id', (string) $this->branch->id)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-healthcare.'.$this->org->id,
                'socket_id' => '1.1',
            ])
            ->assertOk();
    }
}
