<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\ChartOfAccount;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Client request, 2026-10-26: petty cash expense categories were fixed by
 * the seeder; an institution now adds, renames, switches off and deletes
 * its own, starting with telephone and communication for airtime.
 */
class ExpenseCategoryHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['petty.cash.manage', 'record.delete']);
        Sanctum::actingAs($this->user);
    }

    public function test_telephone_and_communication_is_a_standard_category_offered_by_petty_cash(): void
    {
        $this->getJson('/api/finance/petty-cash')->assertOk()
            ->assertJsonFragment(['code' => '6160', 'name' => 'Telephone and communication']);

        $listed = collect($this->getJson('/api/finance/expense-categories')->assertOk()->json('data'))->keyBy('code');
        $this->assertTrue($listed['6160']['is_standard']);
        $this->assertFalse($listed['6160']['can_delete']);
        $this->assertTrue($listed['6010']['is_protected']);
        $this->assertArrayNotHasKey('2320', $listed->all());
    }

    public function test_a_new_category_gets_the_next_free_code_and_can_be_spent_against(): void
    {
        $created = $this->postJson('/api/finance/expense-categories', ['name' => 'Bank charges'])->assertCreated()
            ->assertJsonPath('code', '6200')->assertJsonPath('can_delete', true);
        $this->postJson('/api/finance/expense-categories', ['name' => 'Licences', 'code' => '6555'])->assertCreated()->assertJsonPath('code', '6555');
        $this->postJson('/api/finance/expense-categories', ['name' => 'Fuel'])->assertCreated()->assertJsonPath('code', '6210');

        $this->postJson('/api/finance/expense-categories', ['name' => 'bank CHARGES'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/finance/expense-categories', ['name' => 'Other', 'code' => '6160'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/finance/expense-categories', ['name' => 'Other', 'code' => '5000'])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->getJson('/api/finance/petty-cash')->assertOk()->assertJsonFragment(['id' => $created->json('id'), 'code' => '6200', 'name' => 'Bank charges']);
        $this->assertSame(1, AuditLog::where('action', 'EXPENSE_CATEGORY_CREATED')->where('entity_id', $created->json('id'))->count());
    }

    public function test_a_switched_off_category_is_no_longer_offered_and_a_system_one_cannot_be_switched_off(): void
    {
        $id = $this->postJson('/api/finance/expense-categories', ['name' => 'Airtime'])->json('id');

        $this->putJson("/api/finance/expense-categories/{$id}", ['name' => 'Airtime and data', 'is_active' => false])->assertOk()
            ->assertJsonPath('name', 'Airtime and data')->assertJsonPath('is_active', false);
        $this->assertNotContains($id, collect($this->getJson('/api/finance/petty-cash')->json('expense_accounts'))->pluck('id'));

        $salaries = ChartOfAccount::where('organisation_id', $this->org->id)->where('code', '6010')->value('id');
        $this->putJson("/api/finance/expense-categories/{$salaries}", ['is_active' => false])->assertStatus(409)->assertJsonPath('error.code', 'PROTECTED_CATEGORY');
    }

    public function test_only_an_unused_category_the_institution_added_can_be_deleted(): void
    {
        $unused = $this->postJson('/api/finance/expense-categories', ['name' => 'Licences'])->json('id');
        $used = $this->postJson('/api/finance/expense-categories', ['name' => 'Airtime'])->json('id');
        $this->postJson('/api/finance/petty-cash/top-up', ['voucher_date' => now()->toDateString(), 'funding_source' => 'CASH', 'amount' => '1000', 'description' => 'Float'])->assertCreated();
        $this->postJson('/api/finance/petty-cash/vouchers', ['voucher_date' => now()->toDateString(), 'account_id' => $used, 'amount' => '200', 'description' => 'Safaricom airtime'])->assertCreated();
        $standard = ChartOfAccount::where('organisation_id', $this->org->id)->where('code', '6160')->value('id');

        $this->deleteJson("/api/finance/expense-categories/{$used}")->assertStatus(409)->assertJsonPath('error.code', 'CATEGORY_IN_USE');
        $this->deleteJson("/api/finance/expense-categories/{$standard}")->assertStatus(409);
        $this->deleteJson("/api/finance/expense-categories/{$unused}")->assertOk();

        $this->assertDatabaseMissing('chart_of_accounts', ['id' => $unused]);
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $used]);
    }

    public function test_managing_categories_needs_the_petty_cash_permission(): void
    {
        $this->revokeAllRoles();
        $this->grantPermissions(['record.delete']);

        $this->getJson('/api/finance/expense-categories')->assertForbidden();
        $this->postJson('/api/finance/expense-categories', ['name' => 'Airtime'])->assertForbidden();
    }
}
