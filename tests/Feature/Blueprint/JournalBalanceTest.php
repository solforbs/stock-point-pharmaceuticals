<?php

namespace Tests\Feature\Blueprint;

use App\Models\ChartOfAccount;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Sale;
use App\Models\StockLedger;
use App\Services\Finance\JournalPoster;
use App\Services\Finance\NoOpenPeriodException;
use App\Services\Finance\UnbalancedJournalException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Correction #4 (balanced double-entry) and acceptance test AT-3: every
 * posted journal balances, an unbalanced journal cannot be inserted, and
 * nothing posts into a closed period.
 */
class JournalBalanceTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_a_retail_cash_sale_posts_the_blueprint_posting_map_and_balances(): void
    {
        $this->receive('B-2405', now()->addDays(348)->toDateString(), '1800', '2.0500');

        // Trace 1: 2 units at 55.00, 16% VAT, cost 2.05 each.
        $this->checkout(
            [$this->saleLine('TAB', '2', '55.0000', ['tax_rate' => '16'])],
            [['method' => 'CASH', 'amount' => '127.6000']],
        );

        $journal = JournalEntry::where('source_doc_type', 'sale')->firstOrFail();
        $this->assertTrue($journal->isBalanced());

        $byRole = $journal->lines->mapWithKeys(fn ($l) => [
            $l->account->system_role => bcsub((string) $l->debit_amount, (string) $l->credit_amount, 4),
        ])->all();

        $this->assertSame([
            'CASH' => '127.6000',
            'SALES_RETAIL' => '-110.0000',
            'VAT_OUTPUT' => '-17.6000',
            'COGS' => '4.1000',
            'INVENTORY' => '-4.1000',
        ], $byRole);
    }

    public function test_every_posted_journal_balances(): void
    {
        $this->receive('B-2405', now()->addDays(348)->toDateString(), '1800', '2.0500');
        $this->checkout([$this->saleLine('BOX', '1', '500.0000')], [['method' => 'MPESA', 'amount' => '300.0000', 'reference' => 'QX1']], ['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id]);
        $this->checkout([$this->saleLine('STR', '2', '28.0000', ['discount_amount' => '6.0000'])], [['method' => 'CASH', 'amount' => '50.0000']]);

        $unbalanced = JournalEntry::all()->reject(fn (JournalEntry $j) => $j->isBalanced());

        $this->assertCount(3, JournalEntry::all(), 'One GRN accrual journal plus one per sale');
        $this->assertCount(0, $unbalanced);
    }

    public function test_goods_received_post_inventory_against_the_grn_accrual(): void
    {
        // Part 12.3: Dr Inventory (at landed cost) / Cr GRN accrual — 1,800 × 2.05.
        $this->receive('B-2405', now()->addDays(348)->toDateString(), '1800', '2.0500');

        $this->assertSame('3690.0000', $this->accountBalance('INVENTORY'));
        $this->assertSame('-3690.0000', $this->accountBalance('GRN_ACCRUAL'));
        $this->assertTrue(JournalEntry::where('source_doc_type', 'goods_receipt')->firstOrFail()->isBalanced());
    }

    public function test_an_unbalanced_journal_is_rejected_before_any_row_is_written(): void
    {
        $this->expectException(UnbalancedJournalException::class);

        try {
            app(JournalPoster::class)->post($this->header(), [
                ['account_role' => 'CASH', 'debit' => '100.0000'],
                ['account_role' => 'SALES_RETAIL', 'credit' => '99.9900'],
            ]);
        } finally {
            $this->assertSame(0, JournalEntry::count());
            $this->assertSame(0, JournalEntryLine::count());
        }
    }

    public function test_the_database_rejects_a_line_that_is_both_debit_and_credit(): void
    {
        $journal = app(JournalPoster::class)->post($this->header(), [
            ['account_role' => 'CASH', 'debit' => '10.0000'],
            ['account_role' => 'SALES_RETAIL', 'credit' => '10.0000'],
        ]);

        // The model also refuses this; going through the query builder proves
        // the CHECK constraint holds even for a write that bypasses Eloquent.
        $this->expectException(QueryException::class);

        DB::table('journal_entry_lines')->insert([
            'id' => (string) Str::uuid(),
            'journal_id' => $journal->id,
            'line_number' => 99,
            'account_id' => ChartOfAccount::byRole($this->org->id, 'CASH')->id,
            'debit_amount' => '5.0000',
            'credit_amount' => '5.0000',
        ]);
    }

    public function test_nothing_posts_into_a_closed_period_and_the_whole_sale_rolls_back(): void
    {
        $this->receive('B-2405', now()->addDays(348)->toDateString(), '1800', '2.0500');
        FinancialPeriod::query()->update(['status' => 'CLOSED']);
        $ledgerRowsBefore = StockLedger::count();

        try {
            $this->checkout([$this->saleLine('TAB', '2', '55.0000')], [['method' => 'CASH', 'amount' => '110.0000']]);
            $this->fail('Expected NoOpenPeriodException');
        } catch (NoOpenPeriodException) {
            // expected
        }

        $this->assertSame(0, Sale::count(), 'A sale must not exist without its journal');
        $this->assertSame($ledgerRowsBefore, StockLedger::count(), 'Stock must not move without its journal');
    }

    /**
     * @return array<string, mixed>
     */
    private function header(): array
    {
        return [
            'organisation_id' => $this->org->id,
            'branch_id' => $this->branch->id,
            'entry_date' => now(),
            'source_doc_type' => 'manual',
            'source_doc_id' => (string) Str::uuid(),
            'narration' => 'test',
            'posted_by' => $this->user->id,
        ];
    }
}
