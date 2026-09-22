<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Organisation;
use App\Services\Documents\PdfRenderer;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 16.6 — invoices and delivery notes as real PDFs, rendered from the
 * posted record so a reprint can never disagree with the books.
 */
class DocumentPdfTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('B-PDF', now()->addYear()->toDateString(), '500', '2.0000');
        $this->grantPermissions(['sale.view', 'sale.create', 'warehouse.dispatch']);
        Sanctum::actingAs($this->user);
    }

    public function test_an_invoice_downloads_as_a_pdf_and_the_print_is_audited(): void
    {
        $sale = $this->checkout([$this->saleLine('TAB', '10', '2.7500')], [['method' => 'CASH', 'amount' => '27.5000']]);

        $response = $this->get("/api/sales/{$sale->id}/pdf")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString($sale->doc_number, (string) $response->headers->get('Content-Disposition'));

        $pdf = (string) $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf, 'a real PDF, not HTML');
        $this->assertGreaterThan(1000, strlen((string) $pdf));

        $this->assertSame(1, AuditLog::where('action', 'INVOICE_PRINTED')->where('entity_id', $sale->id)->count());
    }

    public function test_a_sale_paid_in_full_prints_as_a_cash_sale_and_one_on_account_as_a_tax_invoice(): void
    {
        $sale = $this->checkout([$this->saleLine('TAB', '10', '2.7500')], [['method' => 'MPESA', 'amount' => '27.5000', 'reference' => 'QWE123RTY']]);

        $this->assertSame('Cash sale invoice', $sale->documentTitle('27.5000'));
        $this->assertSame('Tax invoice', $sale->documentTitle('10.0000'), 'part-paid leaves a balance on account');
        $this->get("/api/sales/{$sale->id}/pdf")->assertOk();

        $sale->update(['status' => 'VOIDED']);
        $this->assertSame('Voided invoice', $sale->documentTitle('27.5000'));
    }

    public function test_a_placeholder_kra_pin_is_never_printed_as_if_it_were_real(): void
    {
        $renderer = app(PdfRenderer::class);

        Organisation::where('id', $this->org->id)->update(['kra_pin' => 'P000000000X']);
        $this->assertNull($renderer->letterhead($this->branch->id)['kra_pin'], 'a placeholder PIN is reported missing, not printed');

        Organisation::where('id', $this->org->id)->update(['kra_pin' => 'P051234567M']);
        $this->assertSame('P051234567M', $renderer->letterhead($this->branch->id)['kra_pin']);
    }

    public function test_the_letterhead_names_who_issued_the_document(): void
    {
        $letterhead = app(PdfRenderer::class)->letterhead($this->branch->id);

        $this->assertSame($this->org->name, $letterhead['organisation']);
        $this->assertSame($this->branch->name, $letterhead['branch']);
        $this->assertNotEmpty($letterhead['printed_at']);
    }

    public function test_printing_an_invoice_needs_permission_to_see_sales(): void
    {
        $sale = $this->checkout([$this->saleLine('TAB', '1', '2.7500')], [['method' => 'CASH', 'amount' => '2.7500']]);

        $this->revokeAllRoles();
        $this->grantPermissions(['stock.view'], 'Storekeeper');

        $this->get("/api/sales/{$sale->id}/pdf")->assertStatus(403);
    }
}
