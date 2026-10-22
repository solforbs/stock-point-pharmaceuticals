<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Every signed-in person changes their own login email and password, each
 * confirmed with the current password.
 */
class AccountSelfServiceTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        Sanctum::actingAs($this->user);
    }

    public function test_a_user_changes_their_own_login_email(): void
    {
        $this->patchJson('/api/account/email', ['email' => 'new@example.test', 'current_password' => 'wrong-password'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->patchJson('/api/account/email', ['email' => 'New@Example.test', 'current_password' => 'password-long-enough'])
            ->assertOk()->assertJsonPath('email', 'new@example.test');

        $this->assertSame('new@example.test', $this->user->fresh()->email);
        $this->assertTrue(AuditLog::where('action', 'ACCOUNT_EMAIL_CHANGED')->exists());
    }

    public function test_a_user_changes_their_own_password(): void
    {
        $this->user->forceFill(['must_change_password' => true])->save();

        $this->patchJson('/api/account/password', ['current_password' => 'password-long-enough', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->patchJson('/api/account/password', ['current_password' => 'password-long-enough', 'password' => 'a-brand-new-phrase', 'password_confirmation' => 'something-else'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->patchJson('/api/account/password', ['current_password' => 'password-long-enough', 'password' => 'a-brand-new-phrase', 'password_confirmation' => 'a-brand-new-phrase'])
            ->assertOk();

        $user = $this->user->fresh();
        $this->assertTrue(Hash::check('a-brand-new-phrase', $user->password));
        $this->assertFalse((bool) $user->must_change_password);
    }
}
