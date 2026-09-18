<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Services\Reports\ReportCatalogue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 20 — every report reads posted transactions, is permission-gated
 * individually, and exports with an audit trail.
 */
class ReportsHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        // P2 is inside the 90-day minimum shelf life, so FEFO sells from P1 at 2.00.
        $this->receive('P1', now()->addYears(2)->toDateString(), '2000', '2.0000');
        $this->receive('P2', now()->addDays(60)->toDateString(), '1000', '2.5000');
        // 2 boxes retail cash at 500 (cost 2.00 × 400 = 800) and 1 box wholesale on credit.
        $this->checkout([$this->saleLine('BOX', '2', '500.0000')], [['method' => 'CASH', 'amount' => '1000.0000']]);
        $this->checkout([$this->saleLine('BOX', '1', '500.0000')], [], ['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id]);
        $this->grantPermissions(['report.view', 'report.financial.view', 'product.cost.view', 'audit.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_the_catalogue_lists_only_what_the_user_may_run(): void
    {
        $all = $this->getJson('/api/reports')->assertOk()->json('data');
        $this->assertCount(count(ReportCatalogue::all()), $all);

        $this->grantPermissions(['report.view']);
        $keys = collect($this->getJson('/api/reports')->assertOk()->json('data'))->pluck('key');
        $this->assertTrue($keys->contains('sales.daily_summary'));
        $this->assertFalse($keys->contains('margin.by_product'), 'margin needs the cost permission');
        $this->assertFalse($keys->contains('finance.profit_and_loss'));
        $this->assertFalse($keys->contains('exceptions.voids'));
        $this->getJson('/api/reports/finance.profit_and_loss')->assertStatus(403);
        $this->getJson('/api/reports/no.such.report')->assertStatus(404);
    }

    public function test_every_report_in_the_catalogue_runs(): void
    {
        foreach (array_keys(ReportCatalogue::all()) as $key) {
            $params = $key === 'finance.customer_statement' ? '?customer_id='.$this->customer->id : '';
            $this->getJson("/api/reports/{$key}{$params}")
                ->assertOk()
                ->assertJsonPath('key', $key)
                ->assertJsonStructure(['columns', 'rows', 'totals', 'from', 'to', 'generated_at']);
        }
    }

    public function test_sales_and_margin_reports_agree_with_the_posted_documents(): void
    {
        $daily = $this->getJson('/api/reports/sales.daily_summary')->assertOk()->json();
        $this->assertSame(2, $daily['totals']['transactions']);
        $this->assertSame('1000.0000', $daily['totals']['retail_net']);
        $this->assertSame('500.0000', $daily['totals']['wholesale_net']);
        $this->assertSame('1200.0000', $daily['totals']['cogs'], '600 tablets at 2.00');
        $this->assertSame('300.0000', $daily['totals']['gross_profit']);

        $product = $this->getJson('/api/reports/margin.by_product')->assertOk()->json('rows.0');
        $this->assertSame('AMOX500', $product['code']);
        $this->assertSame('1500.0000', $product['net_sales']);
        $this->assertSame('20.00', $product['margin_pct']);
        $this->assertSame('25.00', $product['markup_pct']);

        $abc = $this->getJson('/api/reports/management.abc_analysis')->assertOk()->json();
        $this->assertSame('A', $abc['rows'][0]['class']);

        $kpi = $this->getJson('/api/reports/management.kpi_scorecard')->assertOk()->json('rows.0');
        $this->assertSame('500.0000', $kpi['ar_outstanding'], 'the credit sale is outstanding');
        $this->assertSame('100.00', $kpi['fefo_compliance_pct']);
    }

    public function test_inventory_and_finance_reports_read_the_ledger_and_journals(): void
    {
        $valuation = $this->getJson('/api/reports/inventory.valuation')->assertOk()->json();
        $this->assertSame('2400.0000', $valuation['totals']['qty_base'], '3000 received minus 600 sold');
        $this->assertSame('5300.0000', $valuation['totals']['value_at_cost'], '1400 × 2.00 + 1000 × 2.50');

        $expiry = $this->getJson('/api/reports/inventory.expiry_risk')->assertOk()->json();
        $this->assertSame('WARNING_90', $expiry['rows'][0]['tier']);
        $this->assertSame('2500.0000', $expiry['totals']['value_warning_90'], '1000 tablets of P2 at 2.50 expire within 90 days');

        $pl = $this->getJson('/api/reports/finance.profit_and_loss')->assertOk()->json('totals');
        $this->assertSame('1500.0000', $pl['revenue']);
        $this->assertSame('1200.0000', $pl['cost_of_sales']);
        $this->assertSame('300.0000', $pl['gross_profit']);

        $bs = $this->getJson('/api/reports/finance.balance_sheet')->assertOk()->json('totals');
        $this->assertTrue($bs['balances'], 'Assets = Liabilities + Equity');
        $this->assertSame('5300.0000', $bs['inventory_per_ledger']);

        $statement = $this->getJson('/api/reports/finance.customer_statement?customer_id='.$this->customer->id)->assertOk()->json();
        $this->assertSame('500.0000', $statement['totals']['closing_balance']);

        $checklist = $this->getJson('/api/reports/finance.period_close_checklist')->assertOk()->json();
        $inventoryCheck = collect($checklist['rows'])->firstWhere('check', 'Inventory ledger value equals GL account 1200');
        $this->assertTrue($inventoryCheck['passes'], $inventoryCheck['detail']);
    }

    public function test_exports_are_csv_and_audited(): void
    {
        $response = $this->get('/api/reports/sales.by_product?format=csv')->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('AMOX500', $response->getContent());
        $this->assertSame(1, AuditLog::where('action', 'REPORT_EXPORTED')->where('entity_id', 'sales.by_product')->count());
    }
}
