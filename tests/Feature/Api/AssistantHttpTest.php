<?php

namespace Tests\Feature\Api;

use App\Mail\AssistantCodeMail;
use App\Models\AssistantSession;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Organisation;
use App\Models\Sale;
use App\Models\Store;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * The assistant on the sign-in page: an emailed code, then a fixed list of
 * read-only questions.
 *
 * What these tests are really guarding is that a mailbox buys less than a
 * password: only the listed questions, only this user's own permissions,
 * only their own institution, and nothing that writes.
 */
class AssistantHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        Mail::fake();
    }

    /** The whole conversation: ask, verify, question, answer, sign off. */
    public function test_a_code_verifies_and_the_session_answers_questions(): void
    {
        $this->grantPermissions(['sale.view', 'stock.view']);
        $this->receive('B-1', now()->addDays(400)->toDateString(), '2000', '2.0000');
        $sale = $this->checkout([$this->saleLine('TAB', '10', '5.0000')], [['method' => 'CASH', 'amount' => '50.0000']]);

        $this->postJson('/api/assistant/request-code', ['email' => 'CASHIER@example.test'])
            ->assertOk()->assertJsonPath('sent', true);

        $code = null;
        Mail::assertSent(AssistantCodeMail::class, function (AssistantCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('cashier@example.test');
        });
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $code);

        $token = $this->postJson('/api/assistant/verify', ['email' => 'cashier@example.test', 'code' => $code])
            ->assertOk()
            ->assertJsonPath('user.email', 'cashier@example.test')
            ->json('session_token');
        $this->assertIsString($token);

        $commands = $this->withToken($token)->getJson('/api/assistant/commands')->assertOk()->json('data');
        $offered = array_column($commands, 'command');
        $this->assertContains('/recent_sales', $offered);
        $this->assertContains('/low_stock', $offered);
        $this->assertNotContains('/debtors', $offered, 'a role without finance.ar.view is never offered the debtors list');

        $answer = $this->withToken($token)->postJson('/api/assistant/ask', ['command' => '/recent_sales'])->assertOk()->json();
        $this->assertSame('Recent sales', $answer['title']);
        $this->assertSame($sale->doc_number, $answer['rows'][0]['doc_number']);
        $this->assertSame('KES 50.00', $answer['rows'][0]['total']);

        // Typed without the slash, and in capitals, is still the same question.
        $this->withToken($token)->postJson('/api/assistant/ask', ['command' => 'Sales_Today'])
            ->assertOk()->assertJsonPath('rows.0.count', 1);

        $this->withToken($token)->postJson('/api/assistant/ask', ['command' => '/help'])
            ->assertOk()->assertJsonPath('title', 'What I can answer');

        $this->assertDatabaseHas('audit_logs', ['action' => 'ASSISTANT_SIGNED_IN', 'reference' => 'cashier@example.test']);
        $this->assertSame(3, AuditLog::where('action', 'ASSISTANT_QUERY')->count());

        $this->withToken($token)->postJson('/api/assistant/end')->assertOk();
        $this->withToken($token)->postJson('/api/assistant/ask', ['command' => '/recent_sales'])
            ->assertStatus(401)->assertJsonPath('error.code', 'ASSISTANT_SESSION_EXPIRED');
    }

    /** An address with no account is answered exactly like one that has. */
    public function test_an_unknown_address_is_answered_the_same_way_and_never_verifies(): void
    {
        $known = $this->postJson('/api/assistant/request-code', ['email' => 'cashier@example.test'])->assertOk()->json();
        $unknown = $this->postJson('/api/assistant/request-code', ['email' => 'nobody@example.test'])->assertOk()->json();
        $this->assertSame($known, $unknown);

        Mail::assertSent(AssistantCodeMail::class, 1);
        Mail::assertNotSent(AssistantCodeMail::class, fn (AssistantCodeMail $mail) => $mail->hasTo('nobody@example.test'));

        // A row exists either way, so the timing does not give it away, but no
        // code in the world opens it.
        $this->assertDatabaseHas('assistant_sessions', ['email' => 'nobody@example.test', 'user_id' => null]);
        $this->postJson('/api/assistant/verify', ['email' => 'nobody@example.test', 'code' => '123456'])
            ->assertStatus(422)->assertJsonPath('error.code', 'ASSISTANT_CODE_INVALID');
    }

    public function test_a_wrong_code_is_refused_and_five_wrong_tries_burn_it(): void
    {
        $this->postJson('/api/assistant/request-code', ['email' => 'cashier@example.test'])->assertOk();
        $code = $this->sentCode();

        for ($try = 0; $try < AssistantSession::MAX_ATTEMPTS; $try++) {
            $this->postJson('/api/assistant/verify', ['email' => 'cashier@example.test', 'code' => '000000'])->assertStatus(422);
        }

        // The real code no longer works: the tries are spent.
        $this->postJson('/api/assistant/verify', ['email' => 'cashier@example.test', 'code' => $code])->assertStatus(422);
        $this->assertDatabaseMissing('assistant_sessions', ['email' => 'cashier@example.test', 'attempts' => 0]);
    }

    public function test_a_code_and_a_session_both_expire(): void
    {
        $this->grantPermissions(['sale.view']);
        $this->postJson('/api/assistant/request-code', ['email' => 'cashier@example.test'])->assertOk();
        $code = $this->sentCode();

        $this->travel(AssistantSession::CODE_MINUTES + 1)->minutes();
        $this->postJson('/api/assistant/verify', ['email' => 'cashier@example.test', 'code' => $code])->assertStatus(422);

        $this->travelBack();
        $this->postJson('/api/assistant/request-code', ['email' => 'cashier@example.test'])->assertOk();
        $token = $this->postJson('/api/assistant/verify', ['email' => 'cashier@example.test', 'code' => $this->sentCode()])
            ->assertOk()->json('session_token');

        $this->withToken($token)->postJson('/api/assistant/ask', ['command' => '/recent_sales'])->assertOk();
        $this->travel(AssistantSession::SESSION_MINUTES + 1)->minutes();
        $this->withToken($token)->postJson('/api/assistant/ask', ['command' => '/recent_sales'])->assertStatus(401);
        $this->travelBack();
    }

    /** The command list, and the refusal, follow the same permissions as the screens. */
    public function test_a_question_the_role_may_not_ask_is_refused(): void
    {
        $this->grantPermissions(['stock.view']);
        $token = $this->signIn();

        $this->withToken($token)->postJson('/api/assistant/ask', ['command' => '/low_stock'])->assertOk();
        $this->withToken($token)->postJson('/api/assistant/ask', ['command' => '/debtors'])
            ->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
        $this->withToken($token)->postJson('/api/assistant/ask', ['command' => '/drop_everything'])
            ->assertStatus(422)->assertJsonPath('error.code', 'ASSISTANT_UNKNOWN_COMMAND');
    }

    /** The token is not a login: it opens nothing outside the assistant. */
    public function test_the_session_token_cannot_be_used_against_the_rest_of_the_api(): void
    {
        $this->grantPermissions(['sale.view', 'stock.view']);
        $token = $this->signIn();

        $this->withToken($token)->getJson('/api/sales')->assertUnauthorized();
        $this->withToken($token)->getJson('/api/dashboard/summary')->assertUnauthorized();
        $this->withToken($token)->postJson('/api/customers', ['name' => 'Mine now'])->assertUnauthorized();
    }

    /** Another institution's figures are out of reach, as everywhere else. */
    public function test_the_answers_never_cross_institutions(): void
    {
        $this->grantPermissions(['sale.view']);
        $this->receive('B-1', now()->addDays(400)->toDateString(), '2000', '2.0000');
        $mine = $this->checkout([$this->saleLine('TAB', '10', '5.0000')], [['method' => 'CASH', 'amount' => '50.0000']]);

        $orgB = Organisation::create(['name' => 'Nairobi Chemists Ltd', 'legal_name' => 'Nairobi Chemists Ltd', 'base_currency' => 'KES', 'fiscal_year_start' => 1]);
        $theirs = app(TenantContext::class)->run($orgB->id, function () use ($orgB) {
            $branchB = Branch::create(['organisation_id' => $orgB->id, 'code' => 'NBO', 'name' => 'Nairobi CBD', 'retail_enabled' => true, 'wholesale_enabled' => true]);
            $storeB = Store::create(['branch_id' => $branchB->id, 'code' => 'NBO-MAIN', 'name' => 'Nairobi Store', 'store_type' => 'RETAIL', 'is_sellable' => true]);

            return Sale::create([
                'organisation_id' => $orgB->id, 'branch_id' => $branchB->id, 'store_id' => $storeB->id, 'user_id' => $this->user->id,
                'sale_mode' => 'RETAIL', 'doc_number' => 'NBO-1', 'status' => 'POSTED', 'posted_at' => now(), 'idempotency_key' => 'nbo-1',
                'subtotal' => '900.0000', 'discount_total' => '0.0000', 'tax_total' => '0.0000', 'grand_total' => '900.0000', 'cost_total' => '0.0000',
            ]);
        });

        $answer = $this->withToken($this->signIn())->postJson('/api/assistant/ask', ['command' => '/recent_sales'])->assertOk()->json();
        $numbers = array_column($answer['rows'], 'doc_number');
        $this->assertContains($mine->doc_number, $numbers);
        $this->assertNotContains($theirs->doc_number, $numbers);
    }

    /** A user who has been switched off cannot be spoken for. */
    public function test_a_deactivated_user_gets_no_code(): void
    {
        $this->user->forceFill(['is_active' => false])->save();

        $this->postJson('/api/assistant/request-code', ['email' => 'cashier@example.test'])->assertOk();
        Mail::assertNothingSent();
        $this->assertDatabaseHas('assistant_sessions', ['email' => 'cashier@example.test', 'user_id' => null]);
    }

    /** Asking over and over does not keep sending codes. */
    public function test_repeated_requests_stop_sending_after_five_in_an_hour(): void
    {
        for ($i = 0; $i < AssistantSession::MAX_CODES_PER_HOUR + 2; $i++) {
            $this->postJson('/api/assistant/request-code', ['email' => 'cashier@example.test'])->assertOk();
        }

        Mail::assertSent(AssistantCodeMail::class, AssistantSession::MAX_CODES_PER_HOUR);
    }

    private function sentCode(): string
    {
        $code = null;
        Mail::assertSent(AssistantCodeMail::class, function (AssistantCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return (string) $code;
    }

    /** Requests a code and verifies it, returning the session token. */
    private function signIn(): string
    {
        $this->postJson('/api/assistant/request-code', ['email' => 'cashier@example.test'])->assertOk();

        return (string) $this->postJson('/api/assistant/verify', ['email' => 'cashier@example.test', 'code' => $this->sentCode()])
            ->assertOk()->json('session_token');
    }
}
