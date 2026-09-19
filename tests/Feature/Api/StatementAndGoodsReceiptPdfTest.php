<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 16.6 — customer statements and goods received notes as real PDFs,
 * rendered from the same figures the screens show.
 */
class StatementAndGoodsReceiptPdfTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('B-GRN-PDF', now()->addYear()->toDateString(), '500', '2.0000', 'TAB', true, '480');
        Sanctum::actingAs($this->user);
    }

    public function test_a_customer_statement_downloads_as_a_pdf_and_the_print_is_audited(): void
    {
        $this->grantPermissions(['sale.view', 'sale.create', 'payment.record', 'finance.ar.view']);
        $this->checkout([$this->saleLine('BOX', '2', '500.0000')], [], ['sale_mode' => 'WHOLESALE', 'customer_id' => $this->customer->id]);
        $this->postJson('/api/payments', ['customer_id' => $this->customer->id, 'method' => 'MPESA', 'reference' => 'QPDF1', 'amount' => '300'])->assertCreated();

        $to = now()->toDateString();
        $response = $this->get("/api/customers/{$this->customer->id}/statement/pdf?from=".now()->startOfMonth()->toDateString()."&to={$to}")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString("STMT-{$this->customer->code}-{$to}", (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());

        $this->assertSame(1, AuditLog::where('action', 'STATEMENT_PRINTED')->where('entity_id', $this->customer->id)->count());
    }

    public function test_a_statement_pdf_needs_both_sales_and_receivables_access(): void
    {
        $this->grantPermissions(['sale.view']);

        $this->get("/api/customers/{$this->customer->id}/statement/pdf")->assertStatus(403);
        $this->assertSame(0, AuditLog::where('action', 'STATEMENT_PRINTED')->count());
    }

    public function test_a_goods_received_note_downloads_as_a_pdf_and_the_print_is_audited(): void
    {
        $this->grantPermissions(['grn.create']);
        $grn = GoodsReceipt::where('branch_id', $this->branch->id)->firstOrFail();

        $response = $this->get("/api/goods-receipts/{$grn->id}/pdf")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString($grn->doc_number, (string) $response->headers->get('Content-Disposition'));
        $pdf = (string) $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));

        $this->assertSame(1, AuditLog::where('action', 'GRN_PRINTED')->where('entity_id', $grn->id)->count());
    }

    public function test_a_goods_received_note_pdf_needs_receiving_permission(): void
    {
        $this->revokeAllRoles();
        $this->grantPermissions(['sale.view'], 'Cashier');
        $grn = GoodsReceipt::where('branch_id', $this->branch->id)->firstOrFail();

        $this->get("/api/goods-receipts/{$grn->id}/pdf")->assertStatus(403);
    }
}
