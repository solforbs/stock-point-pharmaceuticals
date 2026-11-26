<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Quotation;
use App\Models\TaxCode;
use App\Models\TaxRate;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Editing a quotation the customer has not yet had turned into an order:
 * quoted prices stand, totals follow the lines, and the PDF and the sales
 * order are built from the edited quotation.
 */
class QuotationEditHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('W1', now()->addYears(2)->toDateString(), '5000', '2.0000');
        $this->grantPermissions(['sale.create', 'sale.view', 'stock.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_reducing_a_quantity_keeps_the_quoted_price_and_the_order_follows_the_edit(): void
    {
        $quotation = $this->createQuotation([$this->line('BOX', '5', '640'), $this->line('STR', '8', '35')]);
        $this->assertSame('3480.0000', $quotation['grand_total']);

        // The price list moving afterwards must not reprice what was offered.
        $this->amox->update(['default_price' => '9.0000']);

        $this->edit($quotation, [
            $this->keep($quotation, 0, '3'),
            $this->keep($quotation, 1, '5'),
        ])
            ->assertOk()
            ->assertJsonPath('doc_number', $quotation['doc_number'])
            ->assertJsonPath('lines.0.qty', '3.0000')
            ->assertJsonPath('lines.0.qty_base', '600.0000')
            ->assertJsonPath('lines.0.unit_price', '640.0000')
            ->assertJsonPath('lines.0.line_total', '1920.0000')
            ->assertJsonPath('lines.1.line_total', '175.0000')
            ->assertJsonPath('subtotal', '2095.0000')
            ->assertJsonPath('grand_total', '2095.0000');

        $this->get("/api/quotations/{$quotation['id']}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->postJson("/api/quotations/{$quotation['id']}/accept", [], ['Idempotency-Key' => 'edit-1'])
            ->assertCreated()
            ->assertJsonPath('grand_total', '2095.0000')
            ->assertJsonPath('lines.0.qty', '3.0000')
            ->assertJsonPath('lines.1.qty', '5.0000');

        $audit = AuditLog::where('action', 'QUOTATION_UPDATED')->where('entity_id', $quotation['id'])->sole();
        $this->assertSame('3480.0000', $audit->before_json['grand_total']);
        $this->assertSame('2095.0000', $audit->after_json['grand_total']);
    }

    public function test_the_editor_sending_back_the_quoted_price_keeps_a_discounted_line_in_proportion(): void
    {
        $this->grantAuthority('10', '10');
        $quotation = $this->createQuotation([$this->line('BOX', '10', '600')], ['header_discount' => '100']);
        $this->assertSame('100.0000', $quotation['discount_total']);

        $this->edit($quotation, [$this->keep($quotation, 0, '5') + ['selling_price' => '590']])
            ->assertOk()
            ->assertJsonPath('subtotal', '3000.0000')
            ->assertJsonPath('discount_total', '50.0000')
            ->assertJsonPath('grand_total', '2950.0000');
    }

    public function test_a_vat_inclusive_price_stays_exact_at_a_new_quantity(): void
    {
        $vat = TaxCode::create(['code' => 'VAT16', 'name' => 'VAT 16%', 'tax_type' => 'VAT']);
        TaxRate::create(['tax_code_id' => $vat->id, 'rate_pct' => '16.0000', 'effective_from' => now()->subYear()->toDateString()]);
        $this->amox->update(['tax_code_id' => $vat->id]);

        $quotation = $this->createQuotation([$this->line('BOX', '7', '97')]);

        $this->edit($quotation, [$this->keep($quotation, 0, '3')])
            ->assertOk()
            ->assertJsonPath('lines.0.line_total', '291.0000')
            ->assertJsonPath('grand_total', '291.0000');

        $updated = Quotation::findOrFail($quotation['id']);
        $this->assertSame('291.0000', bcadd(bcsub((string) $updated->subtotal, (string) $updated->discount_total, 4), (string) $updated->tax_total, 4));
        $this->assertSame(1, bccomp((string) $updated->tax_total, '0', 4));
    }

    public function test_lines_can_be_removed_added_and_repriced(): void
    {
        $quotation = $this->createQuotation([$this->line('BOX', '5', '640'), $this->line('STR', '8', '35')]);

        $this->edit($quotation, [
            $this->keep($quotation, 1, '8') + ['selling_price' => '30'],
            $this->line('TAB', '100', '4'),
        ])
            ->assertOk()
            ->assertJsonCount(2, 'lines')
            ->assertJsonPath('lines.0.line_number', 1)
            ->assertJsonPath('lines.0.unit_price', '30.0000')
            ->assertJsonPath('lines.1.unit_price', '4.0000')
            ->assertJsonPath('grand_total', '640.0000');
    }

    public function test_an_expired_quotation_can_be_accepted_once_edited_with_a_new_date(): void
    {
        $quotation = $this->createQuotation([$this->line('BOX', '5', '640')]);

        $this->travel(10)->days();
        $this->postJson("/api/quotations/{$quotation['id']}/accept", [], ['Idempotency-Key' => 'edit-expired'])
            ->assertStatus(409);

        $this->edit($quotation, [$this->keep($quotation, 0, '3')], now()->toDateString())
            ->assertStatus(422)->assertSee('date after today');

        $this->edit($quotation, [$this->keep($quotation, 0, '3')])->assertOk();

        $this->postJson("/api/quotations/{$quotation['id']}/accept", [], ['Idempotency-Key' => 'edit-renewed'])
            ->assertCreated()
            ->assertJsonPath('grand_total', '1920.0000');
    }

    public function test_an_accepted_quotation_cannot_be_edited(): void
    {
        $quotation = $this->createQuotation([$this->line('BOX', '5', '640')]);
        $this->postJson("/api/quotations/{$quotation['id']}/accept", [], ['Idempotency-Key' => 'edit-done'])->assertCreated();

        $this->edit($quotation, [$this->keep($quotation, 0, '3')])
            ->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');

        $this->assertSame('3200.0000', (string) Quotation::findOrFail($quotation['id'])->grand_total);
    }

    public function test_a_refused_edit_leaves_the_quotation_as_it_was(): void
    {
        $quotation = $this->createQuotation([$this->line('BOX', '5', '640')]);
        $other = $this->createQuotation([$this->line('STR', '8', '35')]);

        $this->edit($quotation, [$this->keep($other, 0, '3')])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
        $this->edit($quotation, [$this->keep($quotation, 0, '2.5')])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
        $this->edit($quotation, [])->assertStatus(422);

        $this->getJson("/api/quotations/{$quotation['id']}")
            ->assertJsonCount(1, 'lines')
            ->assertJsonPath('lines.0.qty', '5.0000')
            ->assertJsonPath('grand_total', '3200.0000')
            ->assertJsonPath('lines.0.product.uoms.0.uom.code', fn ($code) => $code !== null);
    }

    public function test_editing_needs_the_sale_create_permission(): void
    {
        $quotation = $this->createQuotation([$this->line('BOX', '5', '640')]);

        $this->grantPermissions(['sale.view']);

        $this->edit($quotation, [$this->keep($quotation, 0, '3')])->assertStatus(403);
    }

    /**
     * @return array<string, string>
     */
    private function line(string $uom, string $quantity, string $price): array
    {
        return ['product_id' => $this->amox->id, 'uom_id' => $this->uoms[$uom]->id, 'quantity' => $quantity, 'selling_price' => $price];
    }

    /**
     * @param  array<string, mixed>  $quotation
     * @return array<string, string>
     */
    private function keep(array $quotation, int $index, string $quantity): array
    {
        $line = $quotation['lines'][$index];

        return ['id' => $line['id'], 'product_id' => $line['product_id'], 'uom_id' => $line['uom_id'], 'quantity' => $quantity];
    }

    /**
     * @param  list<array<string, string>>  $lines
     * @param  array<string, string>  $extra
     * @return array<string, mixed>
     */
    private function createQuotation(array $lines, array $extra = []): array
    {
        return $this->postJson('/api/quotations', $extra + [
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'valid_until' => now()->addDays(7)->toDateString(),
            'lines' => $lines,
        ])->assertCreated()->json();
    }

    /**
     * @param  array<string, mixed>  $quotation
     * @param  list<array<string, string>>  $lines
     */
    private function edit(array $quotation, array $lines, ?string $validUntil = null): TestResponse
    {
        return $this->putJson("/api/quotations/{$quotation['id']}", [
            'valid_until' => $validUntil ?? now()->addDays(7)->toDateString(),
            'lines' => $lines,
        ]);
    }
}
