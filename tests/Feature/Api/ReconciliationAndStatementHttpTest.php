<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Payment;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 12.5 — receipts reconciled against the bank / M-PESA statement, and
 * Part 12.4 — the customer statement for sales and credit staff.
 */
class ReconciliationAndStatementHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('L1', now()->addYears(2)->toDateString(), '5000', '2.0000');
        $this->grantPermissions(['sale.view', 'payment.record', 'finance.ar.view', 'payment.reconcile']);
        Sanctum::actingAs($this->user);
    }

    public function test_receipts_are_reconciled_in_bulk_and_unreconciled_with_a_reason(): void
    {
        $mpesa = $this->receipt('MPESA', '1500', 'QGH1');
        $bank = $this->receipt('BANK', '2500', 'TT-9');
        $cash = $this->receipt('CASH', '700');
        $bounced = $this->receipt('CHEQUE', '400', 'CHQ-1');
        $this->postJson("/api/payments/{$bounced['id']}/void", ['reason' => 'Cheque bounced'])->assertOk();

        $list = $this->getJson('/api/finance/reconciliation')->assertOk()->assertJsonPath('total', 4)->json();
        $this->assertSame('1500.0000', collect($list['totals'])->firstWhere('method', 'MPESA')['unreconciled']);
        $this->assertNull(collect($list['totals'])->firstWhere('method', 'CHEQUE'), 'reversed receipts are excluded from the totals');
        $this->assertSame($this->user->id, $list['data'][0]['receiver']['id']);
        $this->getJson('/api/finance/reconciliation?method=BANK')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.reference', 'TT-9');

        $this->postJson('/api/finance/reconciliation/reconcile', ['payment_ids' => [$mpesa['id']]])->assertStatus(422)->assertJsonValidationErrors(['reconciliation_ref', 'statement_date']);
        $this->postJson('/api/finance/reconciliation/reconcile', ['payment_ids' => [$mpesa['id'], $bounced['id']], 'reconciliation_ref' => 'STMT-SEP-01', 'statement_date' => now()->toDateString()])
            ->assertStatus(422)->assertJsonPath('error.code', 'PAYMENT_REVERSED');

        $this->postJson('/api/finance/reconciliation/reconcile', [
            'payment_ids' => [$mpesa['id'], $bank['id']], 'reconciliation_ref' => 'STMT-SEP-01', 'statement_date' => now()->toDateString(),
            'statement_amounts' => [$bank['id'] => '2500.00'],
        ])->assertOk()->assertJsonPath('reconciled', 2);

        $this->assertSame('STMT-SEP-01', Payment::findOrFail($mpesa['id'])->reconciliation_ref);
        $this->assertSame('1500.0000', (string) Payment::findOrFail($mpesa['id'])->statement_amount);
        $this->assertSame(2, AuditLog::where('action', 'PAYMENT_RECONCILED')->count());

        $this->postJson('/api/finance/reconciliation/reconcile', ['payment_ids' => [$bank['id'], $cash['id']], 'reconciliation_ref' => 'STMT-2', 'statement_date' => now()->toDateString()])
            ->assertStatus(422)->assertJsonPath('error.code', 'ALREADY_RECONCILED')->assertJsonPath('error.details.payment_ids.0', $bank['id']);

        $this->getJson('/api/finance/reconciliation?status=unreconciled')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $cash['id']);
        $this->getJson('/api/finance/reconciliation?status=reconciled')->assertOk()->assertJsonPath('total', 2)->assertJsonPath('data.0.reconciler.id', $this->user->id);

        $this->postJson("/api/finance/reconciliation/{$bank['id']}/unreconcile", [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->postJson("/api/finance/reconciliation/{$bank['id']}/unreconcile", ['reason' => 'Matched to the wrong statement line'])
            ->assertOk()->assertJsonPath('reconciled_at', null)->assertJsonPath('reconciliation_ref', null);
        $this->postJson("/api/finance/reconciliation/{$bank['id']}/unreconcile", ['reason' => 'Again'])->assertStatus(422)->assertJsonPath('error.code', 'NOT_RECONCILED');
        $this->assertSame('Matched to the wrong statement line', AuditLog::where('action', 'PAYMENT_UNRECONCILED')->value('reason'));
    }

    public function test_reconciling_needs_payment_reconcile_and_viewing_needs_ar_view(): void
    {
        $mpesa = $this->receipt('MPESA', '1500', 'QGH2');

        $this->grantPermissions(['finance.ar.view']);
        $this->getJson('/api/finance/reconciliation')->assertOk();
        $this->postJson('/api/finance/reconciliation/reconcile', ['payment_ids' => [$mpesa['id']], 'reconciliation_ref' => 'S', 'statement_date' => now()->toDateString()])->assertStatus(403);
        $this->postJson("/api/finance/reconciliation/{$mpesa['id']}/unreconcile", ['reason' => 'nope'])->assertStatus(403);

        $this->grantPermissions([]);
        $this->getJson('/api/finance/reconciliation')->assertStatus(403);
    }

    public function test_the_customer_statement_runs_a_balance_and_ages_the_debt(): void
    {
        $sale = $this->checkout([$this->saleLine('BOX', '2', '500.0000')], [], ['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id]);
        $this->receipt('MPESA', '300', 'QGH3');

        $statement = $this->getJson("/api/customers/{$this->customer->id}/statement?from=".now()->startOfMonth()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('customer.code', 'TCRH')
            ->assertJsonPath('organisation.id', $this->org->id)
            ->assertJsonPath('branch.code', 'LDW')
            ->assertJsonPath('opening_balance', '0.0000')
            ->assertJsonPath('rows.0.type', 'OPENING_BALANCE')
            ->assertJsonCount(3, 'rows')
            ->json();

        $expected = bcsub((string) $sale->grand_total, '300', 4);
        $this->assertSame($expected, $statement['closing_balance']);
        $this->assertSame($expected, $statement['rows'][2]['balance']);
        $this->assertSame('300.0000', $statement['total_credit']);
        $this->assertSame($expected, $statement['ageing']['total']);

        $this->getJson('/api/customers/'.$this->customer->id.'/statement?from=2026-09-10&to=2026-09-01')->assertStatus(422)->assertJsonValidationErrors('to');

        // Sales staff without finance.ar.view are refused.
        $this->grantPermissions(['sale.view']);
        $this->getJson("/api/customers/{$this->customer->id}/statement")->assertStatus(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function receipt(string $method, string $amount, ?string $reference = null): array
    {
        return $this->postJson('/api/payments', ['customer_id' => $this->customer->id, 'method' => $method, 'reference' => $reference, 'amount' => $amount])->assertCreated()->json();
    }
}
