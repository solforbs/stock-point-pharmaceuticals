<?php

namespace Tests\Feature\Api;

use App\Models\AssistantSession;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 18.2 — the account must be good on every request, not only at sign-in:
 * deactivation, temporary passwords, exact passwords, a protected second
 * factor, and older password hashes being upgraded.
 */
class AccountStandingTest extends TestCase
{
    use BuildsBlueprintWorld;

    private const PASSWORD = 'password-long-enough';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_a_deactivated_account_is_refused_on_its_next_request(): void
    {
        Sanctum::actingAs($this->user);
        $this->getJson('/api/user')->assertOk();

        $this->user->forceFill(['is_active' => false])->save();

        $this->getJson('/api/user')->assertUnauthorized()->assertJsonPath('error.code', 'ACCOUNT_DISABLED');
        $this->getJson('/api/products')->assertUnauthorized()->assertJsonPath('error.code', 'ACCOUNT_DISABLED');
    }

    public function test_deactivating_a_user_removes_their_stored_sessions_and_tokens(): void
    {
        $colleague = $this->colleague(['name' => 'Grace', 'username' => 'grace', 'email' => 'grace@example.test', 'password' => self::PASSWORD]);
        $colleague->createToken('integration');
        $this->grantPermissions(['admin.users']);
        Sanctum::actingAs($this->user);
        config(['session.driver' => 'database']);
        foreach ([$colleague->id, $this->user->id] as $userId) {
            DB::table('sessions')->insert(['id' => "session-{$userId}", 'user_id' => $userId, 'payload' => '', 'last_activity' => time()]);
        }

        $this->patchJson("/api/admin/users/{$colleague->id}", ['is_active' => false])->assertOk();

        $this->assertDatabaseMissing('sessions', ['user_id' => $colleague->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $this->user->id]);
        $this->assertSame(0, $colleague->tokens()->count());
        $this->assertNotNull($colleague->fresh()->remember_token);
    }

    public function test_a_deactivated_accounts_assistant_pass_stops_working(): void
    {
        $token = 'assistant-pass-token';
        AssistantSession::create([
            'email' => $this->user->email, 'user_id' => $this->user->id,
            'code_hash' => AssistantSession::hash('123456'), 'code_expires_at' => now()->addMinutes(5),
        ])->forceFill(['verified_at' => now(), 'token_hash' => AssistantSession::hash($token), 'token_expires_at' => now()->addMinutes(10), 'last_used_at' => now()])->save();
        $this->withToken($token)->getJson('/api/assistant/commands')->assertOk();

        $this->user->forceFill(['is_active' => false])->save();

        $this->withToken($token)->getJson('/api/assistant/commands')
            ->assertUnauthorized()->assertJsonPath('error.code', 'ASSISTANT_SESSION_EXPIRED');
    }

    public function test_a_temporary_password_opens_nothing_until_it_is_replaced(): void
    {
        $this->user->forceFill(['must_change_password' => true])->save();
        Sanctum::actingAs($this->user);

        $this->getJson('/api/user')->assertOk()->assertJsonPath('must_change_password', true);
        $this->getJson('/api/products')->assertForbidden()->assertJsonPath('error.code', 'PASSWORD_CHANGE_REQUIRED');
        $this->patchJson('/api/account/email', ['email' => 'new@example.test', 'current_password' => self::PASSWORD])
            ->assertForbidden()->assertJsonPath('error.code', 'PASSWORD_CHANGE_REQUIRED');
        $this->postJson('/auth/mfa/setup', ['current_password' => self::PASSWORD])
            ->assertForbidden()->assertJsonPath('error.code', 'PASSWORD_CHANGE_REQUIRED');

        $this->patchJson('/api/account/password', ['current_password' => self::PASSWORD, 'password' => 'a-brand-new-phrase', 'password_confirmation' => 'a-brand-new-phrase'])
            ->assertOk();

        $this->getJson('/api/products')->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_only_the_exact_password_signs_in(): void
    {
        $this->user->forceFill(['password' => 'secret@phrase-for-login'])->save();

        foreach (['secret @phrase-for-login', 'secret@phrase -for-login'] as $altered) {
            $this->postJson('/auth/login', ['email' => 'cashier@example.test', 'password' => $altered])->assertStatus(422);
        }
        $this->assertGuest();

        $this->postJson('/auth/login', ['email' => 'cashier@example.test', 'password' => 'secret@phrase-for-login'])->assertOk();
    }

    public function test_restarting_the_second_factor_needs_the_current_password(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $this->user->forceFill(['mfa_secret' => $secret, 'mfa_required' => true])->save();
        $this->actingAs($this->user);

        $this->postJson('/auth/mfa/setup')->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->postJson('/auth/mfa/setup', ['current_password' => 'not-my-password'])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertTrue($this->user->fresh()->mfa_required);
        $this->assertSame($secret, $this->user->fresh()->mfa_secret);
    }

    public function test_wrong_second_factor_codes_lock_the_account(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $this->user->forceFill(['mfa_secret' => $secret, 'mfa_required' => true])->save();
        $this->postJson('/auth/login', ['email' => 'cashier@example.test', 'password' => self::PASSWORD])->assertStatus(202);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/auth/mfa/verify', ['code' => '000000'])->assertStatus(422);
        }

        $this->postJson('/auth/mfa/verify', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
            ->assertStatus(423)->assertJsonPath('error.code', 'ACCOUNT_LOCKED');
        $this->assertGuest();
        $this->assertSame(5, AuditLog::where('action', 'MFA_FAILED')->count());
    }

    public function test_signing_in_upgrades_an_older_bcrypt_hash_to_argon2id(): void
    {
        config(['hashing.driver' => 'argon2id', 'hashing.argon.verify' => false]);
        Hash::forgetDrivers();
        DB::table('users')->where('id', $this->user->id)->update(['password' => password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4])]);

        $this->postJson('/auth/login', ['email' => 'cashier@example.test', 'password' => self::PASSWORD])->assertOk();

        $this->assertStringStartsWith('$argon2id$', User::find($this->user->id)->password);
    }
}
