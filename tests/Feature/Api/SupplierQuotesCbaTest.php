<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\ProductUom;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Client item 19: supplier quotes, the competitive bid analysis (CBA) with
 * its reasons, and the award that raises draft purchase orders.
 */
class SupplierQuotesCbaTest extends TestCase
{
    use BuildsBlueprintWorld;

    private Supplier $supplierA;

    private Supplier $supplierB;

    private Product $paracetamol;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['rfq.view', 'rfq.manage', 'rfq.award', 'po.create', 'po.approve', 'supplier.view']);

        $this->supplierA = $this->supplier;
        $this->supplierA->update(['name' => 'Alpha Pharma', 'licence_expiry' => now()->addYear()->toDateString()]);
        $this->supplierB = Supplier::create([
            'organisation_id' => $this->org->id, 'code' => 'BETA', 'name' => 'Beta Distributors',
            'status' => 'ACTIVE', 'licence_expiry' => now()->addYears(2)->toDateString(),
        ]);

        $this->paracetamol = Product::create([
            'organisation_id' => $this->org->id, 'code' => 'PCM500', 'name' => 'Paracetamol 500mg Tablets',
            'base_uom_id' => $this->uoms['TAB']->id, 'is_discrete' => true, 'requires_batch' => true, 'is_active' => true,
        ]);
        foreach (['TAB' => 1, 'BOX' => 100] as $code => $factor) {
            ProductUom::create([
                'product_id' => $this->paracetamol->id, 'uom_id' => $this->uoms[$code]->id, 'factor_to_base' => $factor,
                'is_base' => $factor === 1, 'is_purchase' => $code === 'BOX', 'is_sales' => true, 'is_default_sales' => $code === 'BOX',
            ]);
        }

        Sanctum::actingAs($this->user);
    }

    public function test_the_cheapest_quote_is_recommended_with_plain_reasons(): void
    {
        $rfq = $this->sentRfq();
        $this->quote($rfq, $this->supplierA, [$this->line($rfq, 0, '450', 7)]);
        $this->quote($rfq, $this->supplierB, [$this->line($rfq, 0, '485', 7)]);

        $analysis = $this->getJson("/api/rfqs/{$rfq['id']}/analysis")->assertOk();

        $analysis->assertJsonPath('lines.0.recommendation.supplier_id', $this->supplierA->id)
            ->assertJsonPath('lines.0.lowest_unit_price', '450.0000')
            ->assertJsonPath('overall.mode', 'SINGLE')
            ->assertJsonPath('overall.supplier_id', $this->supplierA->id);

        $summary = $analysis->json('lines.0.recommendation.summary');
        $this->assertStringContainsString('Cheapest by KES 35.00 a unit (7.8%)', $summary);
        $this->assertStringContainsString('licence valid to '.now()->addYear()->format('j M Y'), $summary);

        $quotes = collect($analysis->json('lines.0.quotes'))->keyBy('supplier_id');
        $this->assertTrue($quotes[$this->supplierA->id]['is_lowest']);
        $this->assertFalse($quotes[$this->supplierB->id]['is_lowest']);
        $this->assertGreaterThan($quotes[$this->supplierB->id]['scores']['total'], $quotes[$this->supplierA->id]['scores']['total']);
    }

    public function test_a_much_faster_supplier_beats_a_slightly_cheaper_one(): void
    {
        $rfq = $this->sentRfq();
        $this->quote($rfq, $this->supplierA, [$this->line($rfq, 0, '450', 21)]);
        $this->quote($rfq, $this->supplierB, [$this->line($rfq, 0, '460', 2)]);

        $analysis = $this->getJson("/api/rfqs/{$rfq['id']}/analysis")->assertOk()
            ->assertJsonPath('lines.0.recommendation.supplier_id', $this->supplierB->id);

        $summary = $analysis->json('lines.0.recommendation.summary');
        $this->assertStringContainsString('KES 10.00 a unit (2.2%) dearer than Alpha Pharma', $summary);
        $this->assertStringContainsString('but scores higher overall', $summary);
        $this->assertStringContainsString('delivers in 2 days against 21', $summary);
    }

    public function test_the_weights_decide_and_can_be_changed_per_request(): void
    {
        $rfq = $this->sentRfq();
        $this->quote($rfq, $this->supplierA, [$this->line($rfq, 0, '450', 21)]);
        $this->quote($rfq, $this->supplierB, [$this->line($rfq, 0, '460', 2)]);

        $this->patchJson("/api/rfqs/{$rfq['id']}", ['weights' => ['price' => 50, 'lead_time' => 20, 'payment_terms' => 10]])
            ->assertStatus(422);
        $this->patchJson("/api/rfqs/{$rfq['id']}", ['weights' => ['price' => 60, 'lead_time' => 20, 'payment_terms' => 10, 'supplier_record' => 20]])
            ->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');

        // Price alone: the cheapest wins whatever the delivery time.
        $this->patchJson("/api/rfqs/{$rfq['id']}", ['weights' => ['price' => 100, 'lead_time' => 0, 'payment_terms' => 0, 'supplier_record' => 0]])
            ->assertOk()->assertJsonPath('weight_price', '100.00');

        $this->getJson("/api/rfqs/{$rfq['id']}/analysis")->assertOk()
            ->assertJsonPath('weights.price', 100)
            ->assertJsonPath('lines.0.recommendation.supplier_id', $this->supplierA->id);
    }

    public function test_a_supplier_with_an_expired_licence_is_never_recommended_or_awarded(): void
    {
        $this->supplierA->update(['licence_expiry' => now()->subDay()->toDateString()]);
        $rfq = $this->sentRfq();
        $this->quote($rfq, $this->supplierA, [$this->line($rfq, 0, '400', 3)]);
        $this->quote($rfq, $this->supplierB, [$this->line($rfq, 0, '450', 7)]);

        $analysis = $this->getJson("/api/rfqs/{$rfq['id']}/analysis")->assertOk()
            ->assertJsonPath('lines.0.recommendation.supplier_id', $this->supplierB->id);

        $this->assertStringContainsString("Alpha Pharma's licence expired on", $analysis->json('lines.0.recommendation.summary'));
        $alpha = collect($analysis->json('lines.0.quotes'))->firstWhere('supplier_id', $this->supplierA->id);
        $this->assertFalse($alpha['eligible']);
        $this->assertContains('LICENCE_EXPIRED', array_column($alpha['risks'], 'code'));
        $this->assertTrue($alpha['is_lowest'], 'still shown as the lowest price');

        $this->postJson("/api/rfqs/{$rfq['id']}/award", [
            'lines' => [['rfq_line_id' => $rfq['lines'][0]['id'], 'supplier_id' => $this->supplierA->id]],
            'justification' => 'They are the cheapest and fastest by far.',
        ])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
        $this->assertSame(0, PurchaseOrder::where('rfq_id', $rfq['id'])->count());
    }

    public function test_the_supplier_record_counts_invoices_that_went_to_exception(): void
    {
        foreach (['EXCEPTION', 'EXCEPTION'] as $i => $status) {
            $this->invoice($this->supplierA, "A-{$i}", $status);
        }
        foreach (['MATCHED', 'MATCHED'] as $i => $status) {
            $this->invoice($this->supplierB, "B-{$i}", $status);
        }

        $rfq = $this->sentRfq();
        $this->quote($rfq, $this->supplierA, [$this->line($rfq, 0, '450', 7)]);
        $this->quote($rfq, $this->supplierB, [$this->line($rfq, 0, '450', 7)]);

        $analysis = $this->getJson("/api/rfqs/{$rfq['id']}/analysis")->assertOk()
            ->assertJsonPath('lines.0.recommendation.supplier_id', $this->supplierB->id);

        $suppliers = collect($analysis->json('suppliers'))->keyBy('supplier_id');
        $this->assertStringContainsString('2 of 2 past invoices went to exception', $suppliers[$this->supplierA->id]['record']['summary']);
        $this->assertStringContainsString('all 2 past invoices matched', $suppliers[$this->supplierB->id]['record']['summary']);
        $this->assertLessThan($suppliers[$this->supplierB->id]['record']['score'], $suppliers[$this->supplierA->id]['record']['score']);
        $this->assertStringContainsString('Same lowest price as Alpha Pharma', $analysis->json('lines.0.recommendation.summary'));
    }

    public function test_an_expired_quote_and_a_partial_quantity_are_flagged(): void
    {
        $rfq = $this->sentRfq();
        $this->quote($rfq, $this->supplierA, [$this->line($rfq, 0, '400', 3)], ['valid_until' => now()->subDay()->toDateString()]);
        $this->quote($rfq, $this->supplierB, [$this->line($rfq, 0, '450', 7, ['qty_available' => '4', 'shelf_life_months' => 8])]);

        $analysis = $this->getJson("/api/rfqs/{$rfq['id']}/analysis")->assertOk();
        $quotes = collect($analysis->json('lines.0.quotes'))->keyBy('supplier_id');

        $this->assertContains('QUOTE_EXPIRED', array_column($quotes[$this->supplierA->id]['risks'], 'code'));
        $this->assertFalse($quotes[$this->supplierA->id]['quote_valid']);
        $this->assertContains('PARTIAL_QUANTITY', array_column($quotes[$this->supplierB->id]['risks'], 'code'));
        $this->assertContains('SHORT_SHELF_LIFE', array_column($quotes[$this->supplierB->id]['risks'], 'code'));
        $this->assertSame('4.0000', $quotes[$this->supplierB->id]['qty_to_order']);
        $this->assertSame('1800.0000', $quotes[$this->supplierB->id]['line_total']);

        // A valid quote beats an expired one, even for part of the quantity.
        $analysis->assertJsonPath('lines.0.recommendation.supplier_id', $this->supplierB->id);
        $this->assertStringContainsString("Alpha Pharma's quote expired on", $analysis->json('lines.0.recommendation.summary'));
    }

    public function test_a_split_award_is_recommended_when_it_saves_enough(): void
    {
        $rfq = $this->sentRfq(twoLines: true);
        $this->quote($rfq, $this->supplierA, [$this->line($rfq, 0, '400', 5), $this->line($rfq, 1, '300', 5)], ['delivery_charge' => '500']);
        $this->quote($rfq, $this->supplierB, [$this->line($rfq, 0, '480', 5), $this->line($rfq, 1, '200', 5)], ['delivery_charge' => '500']);

        $analysis = $this->getJson("/api/rfqs/{$rfq['id']}/analysis")->assertOk()
            ->assertJsonPath('overall.mode', 'SPLIT')
            ->assertJsonPath('overall.total', '9000.0000')
            ->assertJsonPath("overall.plan.{$rfq['lines'][0]['id']}", $this->supplierA->id)
            ->assertJsonPath("overall.plan.{$rfq['lines'][1]['id']}", $this->supplierB->id);

        // Split 4,000 + 4,000 + two deliveries = 9,000; Beta alone 4,800 + 4,000 + 500 = 9,300.
        $this->assertStringContainsString('saves KES 300.00 (3.2%) against the best single supplier, Beta Distributors', $analysis->json('overall.summary'));
    }

    public function test_one_supplier_is_recommended_when_splitting_saves_too_little(): void
    {
        $rfq = $this->sentRfq(twoLines: true);
        $this->quote($rfq, $this->supplierA, [$this->line($rfq, 0, '400', 5), $this->line($rfq, 1, '300', 5)], ['delivery_charge' => '500']);
        $this->quote($rfq, $this->supplierB, [$this->line($rfq, 0, '410', 5), $this->line($rfq, 1, '200', 5)], ['delivery_charge' => '500']);

        $analysis = $this->getJson("/api/rfqs/{$rfq['id']}/analysis")->assertOk()
            ->assertJsonPath('lines.0.recommendation.supplier_id', $this->supplierA->id)
            ->assertJsonPath('overall.mode', 'SINGLE')
            ->assertJsonPath('overall.supplier_id', $this->supplierB->id)
            ->assertJsonPath('overall.total', '8600.0000')
            ->assertJsonPath("overall.plan.{$rfq['lines'][0]['id']}", $this->supplierB->id);

        $this->assertStringContainsString('costs KES 400.00 less than splitting', $analysis->json('overall.summary'));
    }

    public function test_accepting_the_recommendation_raises_one_draft_purchase_order_per_supplier(): void
    {
        $rfq = $this->sentRfq(twoLines: true);
        $this->quote($rfq, $this->supplierA, [$this->line($rfq, 0, '400', 5), $this->line($rfq, 1, '300', 5)], ['delivery_charge' => '500']);
        $this->quote($rfq, $this->supplierB, [$this->line($rfq, 0, '480', 5), $this->line($rfq, 1, '200', 9)], ['delivery_charge' => '500']);
        $this->postJson("/api/rfqs/{$rfq['id']}/close")->assertOk()->assertJsonPath('status', 'CLOSED');

        $awarded = $this->postJson("/api/rfqs/{$rfq['id']}/award")->assertOk()
            ->assertJsonPath('status', 'AWARDED')
            ->assertJsonPath('award_mode', 'SPLIT')
            ->assertJsonPath('award_followed_recommendation', true)
            ->assertJsonPath('awarded_by', $this->user->id)
            ->assertJsonCount(2, 'created_purchase_orders');

        $orders = PurchaseOrder::where('rfq_id', $rfq['id'])->with('lines')->get()->keyBy('supplier_id');
        $this->assertCount(2, $orders);
        $alpha = $orders[$this->supplierA->id];
        $beta = $orders[$this->supplierB->id];
        $this->assertSame('DRAFT', $alpha->status);
        $this->assertSame(1, $alpha->lines->count());
        $this->assertSame($this->amox->id, $alpha->lines[0]->product_id);
        $this->assertSame('400.0000', (string) $alpha->lines[0]->unit_price);
        $this->assertSame('10.0000', (string) $alpha->lines[0]->qty_ordered);
        $this->assertSame($this->paracetamol->id, $beta->lines[0]->product_id);
        $this->assertSame('200.0000', (string) $beta->lines[0]->unit_price);
        $this->assertSame(now()->addDays(9)->toDateString(), $beta->expected_date->toDateString());
        $this->assertSame($this->supplierA->id, $awarded->json('lines.0.awarded_supplier_id'));

        // The raised orders go through the ordinary approval.
        $this->postJson("/api/purchase-orders/{$alpha->id}/approve")->assertOk()->assertJsonPath('status', 'APPROVED');

        // Awarded once only.
        $this->postJson("/api/rfqs/{$rfq['id']}/award")->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');
    }

    public function test_overriding_the_recommendation_needs_a_justification(): void
    {
        $rfq = $this->sentRfq();
        $this->quote($rfq, $this->supplierA, [$this->line($rfq, 0, '450', 7)]);
        $this->quote($rfq, $this->supplierB, [$this->line($rfq, 0, '485', 7)]);
        $override = ['lines' => [['rfq_line_id' => $rfq['lines'][0]['id'], 'supplier_id' => $this->supplierB->id]]];

        $this->postJson("/api/rfqs/{$rfq['id']}/award", $override)->assertStatus(422)->assertJsonPath('error.code', 'JUSTIFICATION_REQUIRED');
        $this->postJson("/api/rfqs/{$rfq['id']}/award", $override + ['justification' => 'short'])->assertStatus(422)->assertJsonPath('error.code', 'JUSTIFICATION_REQUIRED');

        // Choosing exactly the recommendation needs no reason.
        $this->assertSame('SENT', Rfq::find($rfq['id'])->status);

        $this->postJson("/api/rfqs/{$rfq['id']}/award", $override + ['justification' => 'Alpha delivered expired stock last month; Beta is reliable.'])
            ->assertOk()
            ->assertJsonPath('award_followed_recommendation', false)
            ->assertJsonPath('award_justification', 'Alpha delivered expired stock last month; Beta is reliable.')
            ->assertJsonPath('award_mode', 'SINGLE');

        $this->assertSame(1, PurchaseOrder::where('rfq_id', $rfq['id'])->where('supplier_id', $this->supplierB->id)->count());

        $pdf = $this->get("/api/rfqs/{$rfq['id']}/summary-pdf")->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());

        // After the award the analysis is the one the approver saw.
        $this->getJson("/api/rfqs/{$rfq['id']}/analysis")->assertOk()->assertJsonPath('lines.0.recommendation.supplier_id', $this->supplierA->id);
    }

    public function test_the_request_moves_through_its_statuses_and_prints_per_supplier(): void
    {
        $rfq = $this->postJson('/api/rfqs', $this->rfqPayload())->assertCreated()
            ->assertJsonPath('status', 'DRAFT')->assertJsonCount(2, 'suppliers')->json();
        $this->assertStringStartsWith('RFQ-', $rfq['doc_number']);

        $this->putJson("/api/rfqs/{$rfq['id']}/quotes/{$this->supplierA->id}", ['lines' => [$this->line($rfq, 0, '450', 7)]])
            ->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');
        $this->postJson("/api/rfqs/{$rfq['id']}/send")->assertOk()->assertJsonPath('status', 'SENT');
        $this->postJson("/api/rfqs/{$rfq['id']}/close")->assertStatus(422);

        $pdf = $this->get("/api/rfqs/{$rfq['id']}/suppliers/{$this->supplierA->id}/pdf")->assertOk();
        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());

        $stranger = Supplier::create(['organisation_id' => $this->org->id, 'code' => 'GAM', 'name' => 'Gamma', 'status' => 'ACTIVE']);
        $this->get("/api/rfqs/{$rfq['id']}/suppliers/{$stranger->id}/pdf")->assertNotFound();
        $this->putJson("/api/rfqs/{$rfq['id']}/quotes/{$stranger->id}", ['lines' => [$this->line($rfq, 0, '450', 7)]])->assertStatus(422);

        $declined = $this->putJson("/api/rfqs/{$rfq['id']}/quotes/{$this->supplierB->id}", ['declined' => true])->assertOk();
        $this->assertSame('DECLINED', collect($declined->json('suppliers'))->firstWhere('supplier_id', $this->supplierB->id)['quote_status']);

        $this->getJson('/api/rfqs')->assertOk()->assertJsonPath('data.0.id', $rfq['id'])->assertJsonPath('data.0.suppliers_count', 2);

        $this->postJson("/api/rfqs/{$rfq['id']}/cancel", ['reason' => 'Bought from stock transfer instead'])->assertOk()->assertJsonPath('status', 'CANCELLED');
        $this->postJson("/api/rfqs/{$rfq['id']}/award")->assertStatus(409);
    }

    public function test_rfq_endpoints_need_their_permissions(): void
    {
        $rfq = $this->sentRfq();
        $this->quote($rfq, $this->supplierA, [$this->line($rfq, 0, '450', 7)]);

        $this->revokeAllRoles();
        $this->grantPermissions(['rfq.view'], 'Viewer');

        $this->getJson("/api/rfqs/{$rfq['id']}/analysis")->assertOk();
        $this->postJson('/api/rfqs', $this->rfqPayload())->assertForbidden();
        $this->putJson("/api/rfqs/{$rfq['id']}/quotes/{$this->supplierB->id}", ['lines' => [$this->line($rfq, 0, '450', 7)]])->assertForbidden();
        $this->postJson("/api/rfqs/{$rfq['id']}/award")->assertForbidden();

        $this->revokeAllRoles();
        $this->grantPermissions(['stock.view'], 'Nobody');
        $this->getJson('/api/rfqs')->assertForbidden();
        $this->getJson("/api/rfqs/{$rfq['id']}/analysis")->assertForbidden();
        $this->get("/api/rfqs/{$rfq['id']}/summary-pdf")->assertForbidden();
    }

    public function test_another_institutions_requests_and_suppliers_are_out_of_reach(): void
    {
        $orgB = Organisation::create(['name' => 'Nairobi Chemists Ltd', 'legal_name' => 'Nairobi Chemists Ltd', 'base_currency' => 'KES', 'fiscal_year_start' => 1]);
        [$supplierB, $rfqB] = app(TenantContext::class)->run($orgB->id, function () use ($orgB) {
            $branchB = Branch::create(['organisation_id' => $orgB->id, 'code' => 'NBO', 'name' => 'Nairobi CBD', 'retail_enabled' => true, 'wholesale_enabled' => true]);

            return [
                Supplier::create(['organisation_id' => $orgB->id, 'code' => 'NBS', 'name' => 'Nairobi Supplier', 'status' => 'ACTIVE']),
                Rfq::create(['doc_number' => 'RFQ-B-1', 'branch_id' => $branchB->id, 'title' => 'Theirs', 'status' => 'SENT']),
            ];
        });

        $payload = $this->rfqPayload();
        $payload['supplier_ids'][] = $supplierB->id;
        $this->postJson('/api/rfqs', $payload)->assertStatus(422)->assertJsonValidationErrors('supplier_ids.2');

        $this->assertNotContains($rfqB->id, collect($this->getJson('/api/rfqs')->assertOk()->json('data'))->pluck('id')->all());
        $this->getJson("/api/rfqs/{$rfqB->id}")->assertNotFound();
        $this->getJson("/api/rfqs/{$rfqB->id}/analysis")->assertNotFound();
        $this->putJson("/api/rfqs/{$rfqB->id}/quotes/{$supplierB->id}", ['declined' => true])->assertNotFound();
        $this->postJson("/api/rfqs/{$rfqB->id}/award")->assertNotFound();
        $this->get("/api/rfqs/{$rfqB->id}/summary-pdf")->assertNotFound();

        $mine = $this->sentRfq();
        $this->quote($mine, $this->supplierA, [$this->line($mine, 0, '450', 7)]);
        $this->postJson("/api/rfqs/{$mine['id']}/award", [
            'lines' => [['rfq_line_id' => $mine['lines'][0]['id'], 'supplier_id' => $supplierB->id]],
            'justification' => 'Trying a supplier from elsewhere.',
        ])->assertStatus(422)->assertJsonValidationErrors('lines.0.supplier_id');

        $this->assertSame('SENT', Rfq::withoutGlobalScopes()->find($rfqB->id)->status);
    }

    /**
     * @return array<string, mixed>
     */
    private function rfqPayload(bool $twoLines = false): array
    {
        $lines = [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'qty' => '10']];
        if ($twoLines) {
            $lines[] = ['product_id' => $this->paracetamol->id, 'uom_id' => $this->uoms['BOX']->id, 'qty' => '20'];
        }

        return [
            'title' => 'Antibiotics restock',
            'needed_by' => now()->addWeeks(2)->toDateString(),
            'lines' => $lines,
            'supplier_ids' => [$this->supplierA->id, $this->supplierB->id],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sentRfq(bool $twoLines = false): array
    {
        $rfq = $this->postJson('/api/rfqs', $this->rfqPayload($twoLines))->assertCreated()->json();
        $this->postJson("/api/rfqs/{$rfq['id']}/send")->assertOk();

        return $rfq;
    }

    /**
     * @param  array<string, mixed>  $rfq
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function line(array $rfq, int $index, string $price, int $leadDays, array $extra = []): array
    {
        return ['rfq_line_id' => $rfq['lines'][$index]['id'], 'unit_price' => $price, 'lead_time_days' => $leadDays] + $extra;
    }

    /**
     * @param  array<string, mixed>  $rfq
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $header
     */
    private function quote(array $rfq, Supplier $supplier, array $lines, array $header = []): void
    {
        $this->putJson("/api/rfqs/{$rfq['id']}/quotes/{$supplier->id}", $header + [
            'payment_terms_days' => 30,
            'valid_until' => now()->addMonth()->toDateString(),
            'lines' => $lines,
        ])->assertOk();
    }

    private function invoice(Supplier $supplier, string $number, string $status): void
    {
        SupplierInvoice::create([
            'doc_number' => 'SINV-'.Str::random(6), 'supplier_id' => $supplier->id, 'branch_id' => $this->branch->id,
            'invoice_number' => $number, 'invoice_date' => now()->subMonth()->toDateString(), 'match_status' => $status,
        ]);
    }
}
