<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CustomerCredit;
use App\Models\GoodsReceiptLine;
use App\Models\Sale;
use App\Models\StockLedger;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Posted goods receipts and sales invoices can be corrected through
 * reversing and adjusting entries: stock, cost, the accruals and AR end up
 * as if the right figures had been posted, and the trail is kept.
 */
class DocumentCorrectionTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        Sanctum::actingAs($this->user);
    }

    public function test_a_receipt_of_25_strips_is_corrected_to_250_with_stock_cost_and_accrual_right(): void
    {
        $this->grantPermissions(['grn.create', 'grn.correct']);
        $batch = $this->receive('THEO-01', now()->addYears(2)->toDateString(), '25', '167', 'STR');
        $line = GoodsReceiptLine::where('batch_id', $batch->id)->firstOrFail();

        $this->postJson("/api/goods-receipts/{$line->goods_receipt_id}/correct", [
            'reason' => 'Received 250 strips, keyed 25 by mistake',
            'lines' => [$this->grnChange($line, ['qty_accepted' => '250'])],
        ])->assertOk()->assertJsonPath('lines.0.qty_accepted', '250.0000');

        $balance = $this->balance($batch->id);
        $this->assertSame('2500.0000', (string) $balance->qty_on_hand, '250 strips of 10 tablets');
        $this->assertSame('16.7000', (string) $balance->wac);
        $this->assertSame('2500.0000', $this->ledgerSum($batch->id));
        $this->assertSame('41750.0000', $this->accountBalance('INVENTORY'));
        $this->assertSame('-41750.0000', $this->accountBalance('GRN_ACCRUAL'), 'What is owed to the supplier for 250 strips');
        $this->assertSame(1, StockLedger::where('txn_type', 'GRN_RECEIPT')->count(), 'The original receipt row is kept');
        $this->assertSame(1, StockLedger::where('txn_type', 'GRN_CORRECTION')->count());

        $audit = AuditLog::where('action', 'GRN_CORRECTED')->firstOrFail();
        $this->assertSame('Received 250 strips, keyed 25 by mistake', $audit->reason);
        $this->assertSame('25.0000', $audit->before_json['lines'][0]['qty_accepted']);
        $this->assertSame('250.0000', $audit->after_json['lines'][0]['qty_accepted']);
    }

    public function test_a_unit_and_cost_correction_after_part_was_sold_revalues_the_rest_and_moves_cost_of_sales(): void
    {
        $this->grantPermissions(['grn.correct']);
        // Keyed as 25 boxes (200 tablets) at 167 a box; really 25 boxes at 1,670.
        $batch = $this->receive('THEO-02', now()->addYears(2)->toDateString(), '25', '167', 'BOX');
        $this->checkout([$this->saleLine('TAB', '1000', '20')], [['method' => 'CASH', 'amount' => '20000']]);
        $line = GoodsReceiptLine::where('batch_id', $batch->id)->firstOrFail();

        $this->postJson("/api/goods-receipts/{$line->goods_receipt_id}/correct", [
            'reason' => 'Box price is 1,670 not 167',
            'lines' => [$this->grnChange($line, ['unit_cost' => '1670'])],
        ])->assertOk();

        $balance = $this->balance($batch->id);
        $this->assertSame('4000.0000', (string) $balance->qty_on_hand);
        $this->assertSame('8.3500', (string) $balance->wac);
        $this->assertSame('33400.0000', $this->accountBalance('INVENTORY'), 'The 4,000 tablets left, at 8.35');
        $this->assertSame('-41750.0000', $this->accountBalance('GRN_ACCRUAL'));
        $this->assertSame('8350.0000', $this->accountBalance('COGS'), '1,000 tablets sold at the corrected 8.35');
    }

    public function test_a_reduction_is_refused_when_the_stock_has_already_been_sold(): void
    {
        $this->grantPermissions(['grn.correct']);
        $batch = $this->receive('THEO-03', now()->addYears(2)->toDateString(), '25', '167', 'STR');
        $this->checkout([$this->saleLine('TAB', '200', '20')], [['method' => 'CASH', 'amount' => '4000']]);
        $line = GoodsReceiptLine::where('batch_id', $batch->id)->firstOrFail();
        $trialBefore = $this->trialBalance();

        $this->postJson("/api/goods-receipts/{$line->goods_receipt_id}/correct", [
            'reason' => 'Only 10 strips came',
            'lines' => [$this->grnChange($line, ['qty_accepted' => '10'])],
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'GRN_CORRECTION_BLOCKED')
            ->assertJsonFragment(['message' => 'Line Amoxicillin 500mg Capsules (batch THEO-03): the correction takes back 150 base units, but only 50 are still free in the receiving store — the rest has already been sold, moved or reserved. Bring the stock back to the store or record a stock adjustment instead.']);

        $this->assertSame('50.0000', (string) $this->balance($batch->id)->qty_on_hand);
        $this->assertSame($trialBefore, $this->trialBalance());
        $this->assertSame('25.0000', (string) $line->fresh()->qty_accepted);
    }

    public function test_correcting_a_receipt_needs_the_permission(): void
    {
        $this->grantPermissions(['grn.create']);
        $batch = $this->receive('THEO-04', now()->addYears(2)->toDateString(), '25', '167', 'STR');
        $line = GoodsReceiptLine::where('batch_id', $batch->id)->firstOrFail();

        $this->postJson("/api/goods-receipts/{$line->goods_receipt_id}/correct", [
            'reason' => 'Received 250',
            'lines' => [$this->grnChange($line, ['qty_accepted' => '250'])],
        ])->assertForbidden();
    }

    public function test_an_invoice_date_and_price_correction_keeps_ar_vat_and_revenue_consistent(): void
    {
        $this->grantPermissions(['sale.correct']);
        $this->receive('B-1', now()->addYears(2)->toDateString(), '5', '300', 'BOX');
        $sale = $this->checkout(
            [$this->saleLine('BOX', '2', '500', ['tax_rate' => '16'])],
            [],
            ['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id],
        );
        $this->assertSame('1160.0000', (string) $sale->grand_total);
        $yesterday = now()->subDay()->toDateString();

        $this->postJson("/api/sales/{$sale->id}/correct", [
            'reason' => 'Agreed price was 450 a box, invoiced a day late',
            'posted_at' => $yesterday,
            'lines' => [['id' => $sale->lines[0]->id, 'qty' => '3', 'unit_price' => '450']],
        ])->assertOk()->assertJsonPath('grand_total', '1566.0000');

        $sale = Sale::findOrFail($sale->id);
        $this->assertSame($yesterday, $sale->posted_at->toDateString());
        $this->assertSame('1350.0000', (string) $sale->subtotal);
        $this->assertSame('216.0000', (string) $sale->tax_total);
        $this->assertSame('1566.0000', $this->accountBalance('AR_CONTROL'));
        $this->assertSame('-1350.0000', $this->accountBalance('SALES_WHOLESALE'));
        $this->assertSame('-216.0000', $this->accountBalance('VAT_OUTPUT'));
        $this->assertSame('900.0000', $this->accountBalance('COGS'), 'Three boxes at 300');
        $this->assertSame('600.0000', $this->accountBalance('INVENTORY'));
        $this->assertSame('1566.0000', (string) CustomerCredit::where('customer_id', $this->customer->id)->value('current_balance'));
        $this->assertSame('-600.0000', (string) StockLedger::where('source_doc_id', $sale->id)->sum('qty_base'));
        $this->assertSame(1, AuditLog::where('action', 'SALE_CORRECTED')->where('entity_id', $sale->id)->count());
    }

    public function test_an_invoice_already_reported_to_kra_is_not_edited(): void
    {
        $this->grantPermissions(['sale.correct']);
        $this->receive('B-2', now()->addYears(2)->toDateString(), '5', '300', 'BOX');
        $sale = $this->checkout([$this->saleLine('BOX', '1', '500')], [['method' => 'CASH', 'amount' => '500']]);
        $sale->forceFill(['etims_status' => 'SUBMITTED', 'etims_control_code' => 'CU-1'])->save();

        $this->postJson("/api/sales/{$sale->id}/correct", [
            'reason' => 'Wrong price charged',
            'lines' => [['id' => $sale->lines[0]->id, 'qty' => '1', 'unit_price' => '450']],
        ])->assertStatus(409)->assertJsonPath('error.code', 'SALE_CORRECTION_BLOCKED');
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, mixed>
     */
    private function grnChange(GoodsReceiptLine $line, array $overrides): array
    {
        return array_merge([
            'id' => $line->id,
            'qty_accepted' => (string) $line->qty_accepted,
            'uom_id' => $line->uom_id,
            'unit_cost' => (string) $line->unit_cost,
            'batch_number' => $line->batch_number,
            'expiry_date' => $line->expiry_date->toDateString(),
        ], $overrides);
    }
}
