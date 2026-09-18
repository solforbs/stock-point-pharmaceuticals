<?php

namespace Tests\Feature\Api;

use App\Console\Commands\ImportOpeningStock;
use App\Models\ChartOfAccount;
use App\Models\FinancialPeriod;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\TaxCode;
use Database\Seeders\ProductTaxDefaultSeeder;
use Database\Seeders\TaxCodeSeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Go-live essentials: the opening stock take (Part 7.1), periods that open
 * themselves (Part 12.4) and the 16% VAT default chosen by the business.
 */
class OpeningStockAndPeriodsTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['stock.count.post', 'stock.view', 'sale.create', 'sale.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_opening_stock_posts_released_batches_the_ledger_and_a_balanced_journal(): void
    {
        $rows = [
            ['product_code' => 'AMOX500', 'batch_number' => 'OB-1', 'expiry_date' => now()->addYears(2)->toDateString(), 'qty' => '1,000', 'unit_cost' => '2.5'],
            ['product_code' => 'AMOX500', 'batch_number' => 'OB-OLD', 'expiry_date' => now()->subMonth()->toDateString(), 'qty' => '40', 'unit_cost' => '2'],
        ];

        $this->postJson('/api/inventory/opening-stock', ['store_id' => $this->store->id, 'rows' => $rows, 'dry_run' => true])->assertOk()->assertJsonPath('valid', true);
        $this->assertSame(0, ProductBatch::count(), 'a dry run posts nothing');

        $summary = $this->postJson('/api/inventory/opening-stock', ['store_id' => $this->store->id, 'rows' => $rows])
            ->assertCreated()
            ->assertJsonPath('lines', 2)
            ->assertJsonPath('total_qty', '1040.0000')
            ->assertJsonPath('total_value', '2580.0000')
            ->assertJsonPath('expired_lines', 1)
            ->json();

        $this->assertSame('RELEASED', ProductBatch::where('batch_number', 'OB-1')->value('status'));
        $this->assertSame('EXPIRED', ProductBatch::where('batch_number', 'OB-OLD')->value('status'));
        $this->assertSame(2, StockLedger::where('txn_type', 'OPENING_BALANCE')->count());
        $this->assertSame('40.0000', (string) StockBalance::where('batch_id', ProductBatch::where('batch_number', 'OB-OLD')->value('id'))->value('qty_quarantined'));

        $equity = ChartOfAccount::where('system_role', 'OPENING_BALANCE_EQUITY')->value('id');
        $inventory = ChartOfAccount::where('system_role', 'INVENTORY')->value('id');
        $this->assertSame('2580.0000', (string) JournalEntryLine::where('journal_id', $summary['journal_id'])->where('account_id', $inventory)->value('debit_amount'));
        $this->assertSame('2580.0000', (string) JournalEntryLine::where('journal_id', $summary['journal_id'])->where('account_id', $equity)->value('credit_amount'));

        // The stock is immediately sellable at the POS.
        $this->postJson('/api/pricing/quote', ['sale_mode' => 'RETAIL', 'store_id' => $this->store->id, 'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['TAB']->id, 'quantity' => '10']]])->assertOk();
    }

    public function test_a_single_bad_row_posts_nothing_and_every_error_is_reported(): void
    {
        $response = $this->postJson('/api/inventory/opening-stock', ['store_id' => $this->store->id, 'rows' => [
            ['product_code' => 'AMOX500', 'batch_number' => 'OK-1', 'expiry_date' => '2030-01-31', 'qty' => '5', 'unit_cost' => '1'],
            ['product_code' => 'NOPE', 'batch_number' => 'X', 'expiry_date' => 'soon', 'qty' => '-1', 'unit_cost' => 'abc'],
            ['product_code' => 'AMOX500', 'batch_number' => 'OK-1', 'expiry_date' => '2030-01-31', 'qty' => '1.5', 'unit_cost' => '1'],
        ]])->assertStatus(422)->assertJsonPath('error.code', 'OPENING_STOCK_INVALID')->json('error.details.rows');

        $this->assertArrayNotHasKey('1', $response);
        $this->assertCount(4, $response['2']);
        $this->assertStringContainsString('appears twice', implode(' ', $response['3']));
        $this->assertStringContainsString('whole number', implode(' ', $response['3']));
        $this->assertSame(0, ProductBatch::count());
        $this->assertSame(0, StockLedger::count());
    }

    public function test_a_store_that_has_already_traded_cannot_take_an_opening_balance(): void
    {
        $this->receive('G1', now()->addYear()->toDateString(), '10', '2');

        $this->postJson('/api/inventory/opening-stock', ['store_id' => $this->store->id, 'rows' => [
            ['product_code' => 'AMOX500', 'batch_number' => 'OB-9', 'expiry_date' => '2030-01-31', 'qty' => '5', 'unit_cost' => '1'],
        ]])->assertStatus(422)->assertJsonPath('error.code', 'OPENING_STOCK_INVALID');
    }

    public function test_the_csv_command_reads_a_file_and_supports_a_dry_run(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ob').'.csv';
        file_put_contents($path, "\xEF\xBB\xBFProduct_Code,batch_number,expiry_date,qty,unit_cost\nAMOX500,CSV-1,2030-06-30,\"2,500\",1.75\n");

        $this->assertSame([['product_code' => 'AMOX500', 'batch_number' => 'CSV-1', 'expiry_date' => '2030-06-30', 'qty' => '2,500', 'unit_cost' => '1.75']], ImportOpeningStock::readCsv($path));

        $this->artisan('inventory:import-opening-stock', ['file' => $path, '--store' => 'MAIN', '--user' => $this->user->email, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, ProductBatch::count());

        $this->artisan('inventory:import-opening-stock', ['file' => $path, '--store' => 'MAIN', '--user' => $this->user->email])
            ->expectsOutputToContain('value 4375.0000')->assertSuccessful();
        $this->assertSame('2500.0000', (string) StockBalance::sum('qty_on_hand'));
    }

    public function test_periods_open_themselves_and_are_never_duplicated(): void
    {
        Carbon::setTestNow('2026-09-28 10:00:00');
        $this->artisan('finance:open-periods')->assertSuccessful();
        $this->artisan('finance:open-periods')->assertSuccessful();

        $this->assertTrue(FinancialPeriod::where('fiscal_year', 2026)->where('period_no', 10)->where('status', 'OPEN')->exists());
        $this->assertSame(1, FinancialPeriod::where('fiscal_year', 2026)->where('period_no', 10)->count());

        Carbon::setTestNow('2026-12-15 10:00:00');
        $this->artisan('finance:open-periods')->assertSuccessful();
        $this->assertTrue(FinancialPeriod::where('fiscal_year', 2027)->where('period_no', 1)->exists());
        Carbon::setTestNow();
    }

    public function test_the_vat_default_only_fills_products_without_a_tax_code(): void
    {
        (new TaxCodeSeeder)->run();
        $exempt = TaxCode::where('code', 'VAT_EXEMPT')->value('id');
        $other = Product::create(['organisation_id' => $this->org->id, 'code' => 'EX1', 'name' => 'Exempt item', 'base_uom_id' => $this->uoms['TAB']->id, 'tax_code_id' => $exempt]);

        (new ProductTaxDefaultSeeder)->run();

        $this->assertSame(TaxCode::where('code', 'VAT_STD')->value('id'), $this->amox->fresh()->tax_code_id);
        $this->assertSame($exempt, $other->fresh()->tax_code_id);
    }
}
