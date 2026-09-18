<?php

namespace Tests\Feature\Api;

use App\Models\JournalEntry;
use App\Models\PayrollBand;
use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\PayrollBandsSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 21.16 — a payroll run stamps the bands it used, needs a second
 * person to approve, posts the Part 12.3 journal and pays out of the bank.
 */
class PayrollHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        (new PayrollBandsSeeder)->run();
        $this->grantPermissions(['payroll.view', 'payroll.process', 'journal.post', 'payment.record', 'report.financial.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_a_payroll_run_from_employee_to_bank(): void
    {
        $cashier = $this->postJson('/api/payroll/employees', ['employee_no' => 'E001', 'name' => 'Akai Lokwang', 'job_title' => 'Cashier', 'basic_salary' => '50000', 'kra_pin' => 'A001234567Z', 'bank_name' => 'KCB', 'bank_account' => '1234567890'])
            ->assertCreated()->assertJsonPath('employee_no', 'E001')->assertJsonMissingPath('bank_account')->json();
        $this->postJson('/api/payroll/employees', ['employee_no' => 'E002', 'name' => 'Ekai Nakwawi', 'job_title' => 'Storekeeper', 'basic_salary' => '10000'])->assertCreated();
        $this->postJson('/api/payroll/employees', ['employee_no' => 'E003', 'name' => 'Left Last Year', 'basic_salary' => '80000', 'date_left' => '2025-01-31', 'is_active' => false])->assertCreated();

        $this->getJson('/api/payroll/bands?as_of=2026-09-30')->assertOk()->assertJsonCount(11, 'data');

        $run = $this->postJson('/api/payroll/runs', ['period_year' => 2026, 'period_month' => 9])->assertCreated()->assertJsonPath('status', 'DRAFT')->json();
        $this->postJson('/api/payroll/runs', ['period_year' => 2026, 'period_month' => 9])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');

        $computed = $this->postJson("/api/payroll/runs/{$run['id']}/compute", ['inputs' => [['employee_id' => $cashier['id'], 'overtime' => '2000']]])
            ->assertOk()->assertJsonPath('status', 'COMPUTED')->assertJsonCount(2, 'lines')->json();
        $lineByNo = collect($computed['lines'])->keyBy(fn ($l) => $l['employee']['employee_no']);
        $this->assertSame('52000.0000', $lineByNo['E001']['gross'], 'basic plus the month\'s overtime');
        $this->assertSame('300.0000', $lineByNo['E002']['shif'], 'SHIF floor for the low earner');
        $this->assertSame('2026-09-30', substr((string) $computed['bands_as_of'], 0, 10));
        $this->assertCount(11, $computed['bands_snapshot_json'], 'the band version is stamped on the run');

        // Separation of duties: the preparer cannot approve.
        $this->postJson("/api/payroll/runs/{$run['id']}/approve")->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
        $approver = User::create(['name' => 'Finance Officer', 'username' => 'finance', 'email' => 'finance@example.test', 'password' => 'password-long-enough']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $approver->assignRole(Role::where('name', 'Test role')->firstOrFail());
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Sanctum::actingAs($approver);
        $this->postJson("/api/payroll/runs/{$run['id']}/approve")->assertOk()->assertJsonPath('status', 'APPROVED');

        // Post: Dr Salaries / Cr PAYE, NSSF, SHIF, housing levy, net pay — balanced.
        $posted = $this->postJson("/api/payroll/runs/{$run['id']}/post")->assertOk()->assertJsonPath('status', 'POSTED')->json();
        $journal = JournalEntry::findOrFail($posted['journal_id'])->load('lines.account');
        $this->assertTrue($journal->isBalanced());
        $byRole = $journal->lines->groupBy(fn ($l) => $l->account->system_role)->map(fn ($ls) => (string) $ls->sum(fn ($l) => (float) $l->credit_amount));
        $this->assertSame($posted['total_net'], number_format((float) $byRole['NET_PAY_PAYABLE'], 4, '.', ''));
        $this->assertSame(bcadd($posted['total_nssf_employee'], $posted['total_nssf_employer'], 4), number_format((float) $byRole['NSSF_PAYABLE'], 4, '.', ''));
        $salaries = $journal->lines->filter(fn ($l) => $l->account->system_role === 'SALARIES_EXPENSE')->sum(fn ($l) => (float) $l->debit_amount);
        $this->assertSame(bcadd(bcadd($posted['total_gross'], $posted['total_nssf_employer'], 4), $posted['total_housing_levy_employer'], 4), number_format($salaries, 4, '.', ''));

        // Pay: net pay leaves the bank.
        $paid = $this->postJson("/api/payroll/runs/{$run['id']}/pay", ['reference' => 'KCB-BULK-0917'])->assertOk()->assertJsonPath('status', 'PAID')->json();
        $bank = JournalEntry::findOrFail($paid['payment_journal_id'])->load('lines.account');
        $this->assertSame($posted['total_net'], (string) $bank->lines->firstWhere(fn ($l) => $l->account->system_role === 'BANK')->credit_amount);

        $this->getJson("/api/payroll/runs/{$run['id']}/payslips/{$cashier['id']}")->assertOk()
            ->assertJsonPath('employee.employee_no', 'E001')
            ->assertJsonPath('line.gross', '52000.0000')
            ->assertJsonStructure(['line' => ['breakdown_json' => ['paye_bands', 'nssf_tiers', 'personal_relief']]]);

        $this->getJson('/api/reports/finance.trial-balance')->assertStatus(404);
        $this->getJson('/api/finance/trial-balance')->assertOk()->assertJsonPath('balanced', true);
    }

    public function test_an_old_run_recomputes_from_its_own_band_snapshot(): void
    {
        $this->postJson('/api/payroll/employees', ['employee_no' => 'E010', 'name' => 'Snapshot Test', 'basic_salary' => '50000'])->assertCreated();
        $run = $this->postJson('/api/payroll/runs', ['period_year' => 2026, 'period_month' => 8])->assertCreated()->json();
        $first = $this->postJson("/api/payroll/runs/{$run['id']}/compute")->assertOk()->json();

        // Statute changes: SHIF becomes 3% from next month. The August run must not move.
        PayrollBand::where('band_type', 'SHIF')->whereNull('organisation_id')->update(['effective_to' => '2026-08-31']);
        PayrollBand::create(['band_type' => 'SHIF', 'sequence' => 1, 'effective_from' => '2026-09-01', 'lower' => '0', 'rate_pct' => '3', 'meta_json' => ['minimum' => '300'], 'source' => 'test']);

        $again = $this->postJson("/api/payroll/runs/{$run['id']}/compute")->assertOk()->json();
        $this->assertSame($first['total_shif'], $again['total_shif'], 'the run re-uses its stamped bands');
        $this->assertSame('1375.0000', $again['total_shif']);

        $september = $this->postJson('/api/payroll/runs', ['period_year' => 2026, 'period_month' => 9])->assertCreated()->json();
        $this->assertSame('1500.0000', $this->postJson("/api/payroll/runs/{$september['id']}/compute")->assertOk()->json('total_shif'), 'the new run picks up the new rate');
        $this->assertSame(1, PayrollRun::where('id', $september['id'])->count());
    }

    public function test_payroll_needs_its_permissions(): void
    {
        $this->grantPermissions(['payroll.view']);
        $this->getJson('/api/payroll/employees')->assertOk();
        $this->postJson('/api/payroll/employees', ['employee_no' => 'E100', 'name' => 'X', 'basic_salary' => '1'])->assertStatus(403);
        $this->postJson('/api/payroll/runs', ['period_year' => 2026, 'period_month' => 9])->assertStatus(403);
    }
}
