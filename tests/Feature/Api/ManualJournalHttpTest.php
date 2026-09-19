<?php

namespace Tests\Feature\Api;

use App\Models\ChartOfAccount;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

class ManualJournalHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        Sanctum::actingAs($this->user);
    }

    public function test_posting_manual_journal_requires_journal_post_permission(): void
    {
        $this->grantPermissions(['sale.view']);

        $this->postJson('/api/finance/journals', [
            'entry_date' => now()->toDateString(),
            'narration' => 'Test manual journal',
            'lines' => [
                ['account_id' => ChartOfAccount::byRole($this->org->id, 'CASH')->id, 'debit' => 100],
                ['account_id' => ChartOfAccount::byRole($this->org->id, 'SALES_RETAIL')->id, 'credit' => 100],
            ],
        ])->assertStatus(403);
    }

    public function test_posting_balanced_manual_journal_succeeds(): void
    {
        $this->grantPermissions(['journal.post']);

        $cash = ChartOfAccount::byRole($this->org->id, 'CASH');
        $sales = ChartOfAccount::byRole($this->org->id, 'SALES_RETAIL');

        $res = $this->postJson('/api/finance/journals', [
            'entry_date' => now()->toDateString(),
            'narration' => 'Cash adjustment for petty cash reconciliation',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => '250.0000', 'narration' => 'Debit Cash'],
                ['account_id' => $sales->id, 'credit' => '250.0000', 'narration' => 'Credit Sales'],
            ],
        ])->assertStatus(201);

        $res->assertJsonPath('source_doc_type', 'manual')
            ->assertJsonPath('narration', 'Cash adjustment for petty cash reconciliation')
            ->assertJsonCount(2, 'lines');

        $journalId = $res->json('id');
        $this->assertDatabaseHas('journal_entries', [
            'id' => $journalId,
            'organisation_id' => $this->org->id,
            'source_doc_type' => 'manual',
            'narration' => 'Cash adjustment for petty cash reconciliation',
        ]);

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_id' => $journalId,
            'account_id' => $cash->id,
            'debit_amount' => '250.0000',
            'credit_amount' => '0.0000',
        ]);

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_id' => $journalId,
            'account_id' => $sales->id,
            'debit_amount' => '0.0000',
            'credit_amount' => '250.0000',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'MANUAL_JOURNAL_POSTED',
            'entity_type' => 'journal_entry',
            'entity_id' => $journalId,
        ]);
    }

    public function test_unbalanced_manual_journal_is_rejected_with_422(): void
    {
        $this->grantPermissions(['journal.post']);

        $cash = ChartOfAccount::byRole($this->org->id, 'CASH');
        $sales = ChartOfAccount::byRole($this->org->id, 'SALES_RETAIL');

        $res = $this->postJson('/api/finance/journals', [
            'entry_date' => now()->toDateString(),
            'narration' => 'Unbalanced entry',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => '100.0000'],
                ['account_id' => $sales->id, 'credit' => '99.5000'],
            ],
        ])->assertStatus(422);

        $res->assertJsonPath('error.code', 'UNBALANCED_JOURNAL');
    }

    public function test_line_with_both_debit_and_credit_is_rejected(): void
    {
        $this->grantPermissions(['journal.post']);

        $cash = ChartOfAccount::byRole($this->org->id, 'CASH');
        $sales = ChartOfAccount::byRole($this->org->id, 'SALES_RETAIL');

        $res = $this->postJson('/api/finance/journals', [
            'entry_date' => now()->toDateString(),
            'narration' => 'Invalid two-sided line',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => '100.0000', 'credit' => '10.0000'],
                ['account_id' => $sales->id, 'credit' => '90.0000'],
            ],
        ])->assertStatus(422);

        $res->assertJsonPath('error.code', 'INVALID_LINE');
    }

    public function test_posting_to_non_postable_account_is_rejected(): void
    {
        $this->grantPermissions(['journal.post']);

        $cash = ChartOfAccount::byRole($this->org->id, 'CASH');
        $headerAccount = ChartOfAccount::create([
            'organisation_id' => $this->org->id,
            'code' => '1000-HDR',
            'name' => 'Current Assets Header',
            'account_type' => 'ASSET',
            'is_postable' => false,
            'is_active' => true,
        ]);

        $res = $this->postJson('/api/finance/journals', [
            'entry_date' => now()->toDateString(),
            'narration' => 'Post to summary header',
            'lines' => [
                ['account_id' => $headerAccount->id, 'debit' => '100.0000'],
                ['account_id' => $cash->id, 'credit' => '100.0000'],
            ],
        ])->assertStatus(422);

        $res->assertJsonPath('error.code', 'NON_POSTABLE_ACCOUNT');
    }

    public function test_single_journal_can_be_retrieved(): void
    {
        $this->grantPermissions(['journal.post']);

        $cash = ChartOfAccount::byRole($this->org->id, 'CASH');
        $sales = ChartOfAccount::byRole($this->org->id, 'SALES_RETAIL');

        $createRes = $this->postJson('/api/finance/journals', [
            'entry_date' => now()->toDateString(),
            'narration' => 'Lookup test',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => '50.0000'],
                ['account_id' => $sales->id, 'credit' => '50.0000'],
            ],
        ])->assertStatus(201);

        $id = $createRes->json('id');

        $getRes = $this->getJson("/api/finance/journals/{$id}")->assertOk();
        $getRes->assertJsonPath('id', $id)
            ->assertJsonPath('narration', 'Lookup test')
            ->assertJsonCount(2, 'lines');
    }

    public function test_reversing_journal_succeeds_and_prevents_duplicate_reversal(): void
    {
        $this->grantPermissions(['journal.post', 'journal.reverse']);

        $cash = ChartOfAccount::byRole($this->org->id, 'CASH');
        $sales = ChartOfAccount::byRole($this->org->id, 'SALES_RETAIL');

        $createRes = $this->postJson('/api/finance/journals', [
            'entry_date' => now()->toDateString(),
            'narration' => 'Entry to reverse',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => '75.0000'],
                ['account_id' => $sales->id, 'credit' => '75.0000'],
            ],
        ])->assertStatus(201);

        $id = $createRes->json('id');

        // Reverse the journal
        $revRes = $this->postJson("/api/finance/journals/{$id}/reverse", [
            'reason' => 'Duplicate posting correction',
        ])->assertStatus(201);

        $revRes->assertJsonPath('reverses_journal_id', $id)
            ->assertJsonPath('source_doc_type', 'journal_reversal');

        // Reversal lines should have swapped amounts: Cash is now Credit 75, Sales is Debit 75
        $reversalLines = $revRes->json('lines');
        $cashLine = collect($reversalLines)->firstWhere('account_id', $cash->id);
        $salesLine = collect($reversalLines)->firstWhere('account_id', $sales->id);

        $this->assertSame('0.0000', $cashLine['debit_amount']);
        $this->assertSame('75.0000', $cashLine['credit_amount']);
        $this->assertSame('75.0000', $salesLine['debit_amount']);
        $this->assertSame('0.0000', $salesLine['credit_amount']);

        // Attempting to reverse the same journal a second time must fail
        $secondRev = $this->postJson("/api/finance/journals/{$id}/reverse", [
            'reason' => 'Attempt duplicate reversal',
        ])->assertStatus(422);

        $secondRev->assertJsonPath('error.code', 'ALREADY_REVERSED');
    }
}
