<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 18.2 — lockout after five failures with exponential back-off, TOTP
 * as a second factor, and an audit entry for every attempt.
 */
class LoginSecurityTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_five_failed_attempts_lock_the_account_for_fifteen_minutes_even_for_the_right_password(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/auth/login', ['email' => 'cashier@example.test', 'password' => 'wrong'])->assertStatus(422);
        }

        $this->assertSame(5, $this->user->fresh()->failed_login_attempts);
        $this->assertEqualsWithDelta(15, now()->diffInMinutes($this->user->fresh()->locked_until), 1);

        $this->postJson('/auth/login', ['email' => 'cashier@example.test', 'password' => 'password-long-enough'])
            ->assertStatus(423)
            ->assertJsonPath('error.code', 'ACCOUNT_LOCKED');
        $this->assertGuest();
        $this->assertSame(5, AuditLog::where('action', 'LOGIN_FAILED')->count());
        $this->assertSame(1, AuditLog::where('action', 'LOGIN_LOCKED')->count());
    }

    public function test_the_lock_doubles_on_every_further_block_and_clears_on_success(): void
    {
        $this->user->forceFill(['failed_login_attempts' => 9])->save();

        $this->postJson('/auth/login', ['email' => 'cashier@example.test', 'password' => 'wrong'])->assertStatus(422);
        $this->assertEqualsWithDelta(30, now()->diffInMinutes($this->user->fresh()->locked_until), 1, 'Second lock is 15 × 2');

        $this->user->forceFill(['locked_until' => now()->subMinute()])->save();
        $this->postJson('/auth/login', ['email' => 'cashier@example.test', 'password' => 'password-long-enough'])->assertOk();

        $this->assertSame(0, $this->user->fresh()->failed_login_attempts);
        $this->assertNull($this->user->fresh()->locked_until);
        $this->assertNotNull($this->user->fresh()->last_login_at);
    }

    public function test_an_inactive_user_cannot_log_in(): void
    {
        $this->user->forceFill(['is_active' => false])->save();

        $this->postJson('/auth/login', ['email' => 'cashier@example.test', 'password' => 'password-long-enough'])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_mfa_setup_then_verify_enables_the_second_factor_and_login_requires_it(): void
    {
        $this->actingAs($this->user);
        $setup = $this->postJson('/auth/mfa/setup')->assertOk()->json();
        $this->assertStringStartsWith('otpauth://totp/', $setup['otpauth_url']);
        $this->assertFalse($this->user->fresh()->mfa_required, 'Not enforced until the authenticator is proven');

        $code = app(Google2FA::class)->getCurrentOtp($setup['secret']);
        $this->postJson('/auth/mfa/verify', ['code' => $code])->assertOk()->assertJsonPath('mfa_required', true);
        $this->assertTrue($this->user->fresh()->mfa_required);
        $this->assertNotSame($setup['secret'], $this->user->fresh()->getRawOriginal('mfa_secret'), 'Secret is encrypted at rest');

        // A fresh login now stops at the second factor.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->postJson('/auth/login', ['email' => 'cashier@example.test', 'password' => 'password-long-enough'])
            ->assertStatus(202)
            ->assertJsonPath('mfa_required', true);
        $this->assertGuest();

        $this->postJson('/auth/mfa/verify', ['code' => '000000'])->assertStatus(422);
        $this->assertGuest();

        $this->postJson('/auth/mfa/verify', ['code' => app(Google2FA::class)->getCurrentOtp($setup['secret'])])
            ->assertOk()
            ->assertJsonPath('email', 'cashier@example.test');
        $this->assertAuthenticatedAs($this->user);
        $this->assertSame(1, AuditLog::where('action', 'MFA_FAILED')->count());
    }
}
