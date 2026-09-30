<?php

namespace Tests\Feature\Api;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\UserMessage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Client finance questions, 2026-09-30: who paid into which bank account,
 * petty cash, non-pharmaceutical supplies, the 13-week forecast, the M-PESA
 * register and alerts when the ledger moves.
 */
class FinanceCashHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('L1', now()->addYears(2)->toDateString(), '5000', '2.0000');
        $this->grantPermissions([
            'sale.view', 'payment.record', 'finance.ar.view', 'petty.cash.manage', 'report.financial.view', 'journal.post',
            'requisition.view', 'requisition.create', 'requisition.approve', 'po.create', 'admin.settings',
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_a_bank_receipt_keeps_the_account_paid_into_and_who_paid(): void
    {
        $account = BankAccount::create(['organisation_id' => $this->org->id, 'name' => 'Main current', 'bank_name' => 'KCB', 'account_number' => '1100223344']);

        $this->postJson('/api/payments', [
            'customer_id' => $this->customer->id, 'method' => 'BANK', 'reference' => 'TT-778', 'amount' => '2500',
            'bank_account_id' => $account->id, 'payer_name' => 'Tana River County Referral', 'payer_bank' => 'Equity Bank', 'payer_account' => '0170199',
        ])->assertCreated();

        $payment = Payment::where('reference', 'TT-778')->firstOrFail();
        $this->assertSame($account->id, $payment->bank_account_id);
        $this->assertSame('Equity Bank', $payment->payer_bank);

        $report = $this->getJson('/api/reports/finance.bank_receipts?from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString())->assertOk();
        $report->assertJsonPath('rows.0.bank_account', 'Main current')->assertJsonPath('rows.0.payer', 'Tana River County Referral')->assertJsonPath('rows.0.payer_detail', 'Equity Bank 0170199');

        $this->postJson('/api/bank-accounts', ['name' => 'Second', 'bank_name' => 'Equity', 'account_number' => '999'])->assertCreated();
        $this->getJson('/api/bank-accounts')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_customer_keeps_bank_and_mpesa_details(): void
    {
        $this->grantPermissions(['customer.manage']);

        $this->patchJson("/api/customers/{$this->customer->id}", ['bank_name' => 'Co-op Bank', 'bank_account_number' => '01100', 'mpesa_phone' => '0712345678'])
            ->assertOk()->assertJsonPath('bank_name', 'Co-op Bank')->assertJsonPath('mpesa_phone', '0712345678');
    }

    public function test_the_mpesa_register_marks_short_and_over_payments(): void
    {
        $sale = $this->checkout([$this->saleLine('BOX', '2', '500.0000')], [], ['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id]);
        $owed = $sale->grand_total;

        $this->postJson('/api/payments', ['customer_id' => $this->customer->id, 'method' => 'MPESA', 'reference' => 'SHORT1', 'amount' => '100'])->assertCreated();
        $this->travel(2)->seconds();
        $this->postJson('/api/payments', ['customer_id' => $this->customer->id, 'method' => 'MPESA', 'reference' => 'OVER1', 'amount' => bcadd((string) $owed, '5000', 4)])->assertCreated();

        $rows = $this->getJson('/api/reports/finance.mpesa_log?from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString())->assertOk()->json('rows');
        $this->assertSame(['SHORT1', 'OVER1'], array_column($rows, 'reference'), 'oldest first');
        $this->assertSame('SHORT_PAID', $rows[0]['status']);
        $this->assertSame('OVERPAID', $rows[1]['status']);
        $this->assertSame('5100.0000', $rows[1]['unallocated']);
    }

    public function test_petty_cash_is_topped_up_spent_and_voided_without_leaving_the_ledger(): void
    {
        $this->getJson('/api/finance/petty-cash')->assertOk()->assertJsonPath('balance', '0.0000');
        $expense = ChartOfAccount::where('organisation_id', $this->org->id)->where('code', '6110')->firstOrFail();
        $voucher = ['voucher_date' => now()->toDateString(), 'account_id' => $expense->id, 'amount' => '1200', 'payee' => 'Boda rider', 'description' => 'Courier to the county store'];

        $this->postJson('/api/finance/petty-cash/vouchers', $voucher)->assertStatus(422);

        $this->postJson('/api/finance/petty-cash/top-up', ['voucher_date' => now()->toDateString(), 'funding_source' => 'CASH', 'amount' => '5000', 'description' => 'Float'])->assertCreated();
        $this->assertSame('5000.0000', $this->accountBalance('PETTY_CASH'));

        $spent = $this->postJson('/api/finance/petty-cash/vouchers', $voucher)->assertCreated()->json();
        $this->assertSame('3800.0000', $this->accountBalance('PETTY_CASH'));
        $this->postJson('/api/finance/petty-cash/vouchers', array_merge($voucher, ['amount' => '4000']))->assertStatus(422);

        $this->postJson("/api/finance/petty-cash/vouchers/{$spent['id']}/void", ['reason' => 'Wrong branch'])->assertOk()->assertJsonPath('status', 'VOID');
        $this->assertSame('5000.0000', $this->accountBalance('PETTY_CASH'));
        $this->postJson("/api/finance/petty-cash/vouchers/{$spent['id']}/void", ['reason' => 'Again'])->assertStatus(422);

        $book = $this->getJson('/api/reports/finance.petty_cash_book?from='.now()->toDateString().'&to='.now()->toDateString())->assertOk();
        $book->assertJsonPath('totals.topped_up', '5000.0000')->assertJsonPath('totals.closing_float', '5000.0000');
    }

    public function test_supplies_are_requested_approved_by_someone_else_then_expensed(): void
    {
        $request = $this->postJson('/api/supply-requests', ['lines' => [
            ['item' => 'Broom', 'category' => 'CLEANING', 'qty' => 4, 'est_unit_cost' => 250],
            ['item' => 'Mop', 'category' => 'CLEANING', 'qty' => 2, 'est_unit_cost' => 400],
        ]])->assertCreated()->assertJsonPath('total_cost', '1800.0000')->json();

        $this->postJson("/api/supply-requests/{$request['id']}/submit")->assertOk();
        $this->postJson("/api/supply-requests/{$request['id']}/approve")->assertStatus(422);

        $approver = $this->colleague(['name' => 'Director', 'username' => 'dir', 'email' => 'dir@example.test', 'password' => 'password-long-enough']);
        $role = $this->grantPermissions(['requisition.view', 'requisition.approve', 'po.create'], 'Approver');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $approver->assignRole($role);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($approver);

        $this->postJson("/api/supply-requests/{$request['id']}/approve")->assertOk()->assertJsonPath('status', 'APPROVED');

        $lineIds = collect($this->getJson("/api/supply-requests/{$request['id']}")->json('lines'))->pluck('id');
        $this->postJson("/api/supply-requests/{$request['id']}/purchase", ['paid_from' => 'PETTY_CASH', 'lines' => $lineIds->mapWithKeys(fn ($id) => [$id => '300'])->all()])
            ->assertStatus(422); // no float yet

        $this->postJson("/api/supply-requests/{$request['id']}/purchase", ['paid_from' => 'CASH', 'supplier_name' => 'Naivas', 'lines' => $lineIds->mapWithKeys(fn ($id) => [$id => '300'])->all()])
            ->assertOk()->assertJsonPath('status', 'PURCHASED')->assertJsonPath('total_cost', '1800.0000');

        $this->assertSame('-1800.0000', $this->accountBalance('CASH'));
        $this->assertSame('1800.0000', $this->accountBalance('SUPPLIES_EXPENSE'));
        $this->postJson("/api/supply-requests/{$request['id']}/purchase", ['paid_from' => 'CASH', 'lines' => $lineIds->mapWithKeys(fn ($id) => [$id => '300'])->all()])->assertStatus(422);
    }

    public function test_the_thirteen_week_forecast_carries_opening_cash_receivables_and_a_closing_line(): void
    {
        $this->postJson('/api/finance/petty-cash/top-up', ['voucher_date' => now()->toDateString(), 'funding_source' => 'BANK', 'amount' => '4000', 'description' => 'Float'])->assertCreated();
        $this->checkout([$this->saleLine('BOX', '2', '500.0000')], [], ['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id]);

        $report = $this->getJson('/api/reports/finance.cashflow_13_week')->assertOk();
        $rows = $report->json('rows');
        $this->assertCount(13, $rows);
        $this->assertSame(1, $rows[0]['week']);
        $this->assertSame('0.0000', $report->json('totals.opening_cash'), 'petty cash was funded from the bank, so cash and bank net to zero');

        $received = array_sum(array_map(fn ($r) => (float) $r['ar_receipts'], $rows)) + (float) $report->json('totals.receivable_after_week_13');
        $this->assertGreaterThan(0, $received, 'the unpaid wholesale invoice appears somewhere in the horizon');
        $this->assertEqualsWithDelta((float) $rows[12]['closing'], (float) $report->json('totals.closing_cash_week_13'), 0.001);

        $half = $this->getJson('/api/reports/finance.cashflow_13_week?collection_pct=50')->assertOk();
        $this->assertEqualsWithDelta($received / 2, array_sum(array_map(fn ($r) => (float) $r['ar_receipts'], $half->json('rows'))) + (float) $half->json('totals.receivable_after_week_13'), 0.01);

        $catalogue = collect($this->getJson('/api/reports')->json('data'))->firstWhere('key', 'finance.cashflow_13_week');
        $this->assertNotContains('from', $catalogue['filters'], 'a forecast has no date range');
    }

    public function test_a_journal_at_or_above_the_alert_threshold_tells_the_people_who_post_journals(): void
    {
        $approver = $this->colleague(['name' => 'Finance', 'username' => 'fin', 'email' => 'fin@example.test', 'password' => 'password-long-enough']);
        $role = $this->grantPermissions(['journal.post'], 'Finance viewer');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $approver->assignRole($role);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $topUp = ['voucher_date' => now()->toDateString(), 'funding_source' => 'CASH', 'amount' => '5000', 'description' => 'Float'];
        $this->postJson('/api/finance/petty-cash/top-up', $topUp)->assertCreated();
        $this->assertSame(0, UserMessage::where('recipient_id', $approver->id)->count(), 'alerts are off until a threshold is set');

        Setting::create(['organisation_id' => $this->org->id, 'branch_id' => null, 'scope' => 'finance', 'key' => 'posting_alert_threshold', 'value_json' => '4000', 'effective_from' => now()->toDateString(), 'set_at' => now()]);
        $this->postJson('/api/finance/petty-cash/top-up', array_merge($topUp, ['amount' => '3000']))->assertCreated();
        $this->assertSame(0, UserMessage::where('recipient_id', $approver->id)->count(), 'below the threshold');

        $this->postJson('/api/finance/petty-cash/top-up', $topUp)->assertCreated();
        $message = UserMessage::where('recipient_id', $approver->id)->firstOrFail();
        $this->assertStringContainsString('5,000.00', $message->subject);
        $this->assertSame(0, UserMessage::where('recipient_id', $this->user->id)->count(), 'the person who posted is not told what they just did');
    }

    public function test_petty_cash_needs_its_permission(): void
    {
        $this->grantPermissions(['sale.view']);
        $this->getJson('/api/finance/petty-cash')->assertStatus(403);
        $this->postJson('/api/supply-requests', ['lines' => [['item' => 'Bin', 'qty' => 1]]])->assertStatus(403);
    }
}
