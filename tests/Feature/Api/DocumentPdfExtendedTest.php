<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\NumberSequence;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\Sales\QuotationService;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

class DocumentPdfExtendedTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('B-PDF-EXT', now()->addYear()->toDateString(), '500', '2.0000');
        $this->discountPolicy();
        $this->grantAuthority('8.000', '5.000');
        Sanctum::actingAs($this->user);
    }

    public function test_a_quotation_downloads_as_a_pdf_and_the_print_is_audited(): void
    {
        $this->grantPermissions(['sale.view', 'sale.create']);

        $quotation = app(QuotationService::class)->create([
            'organisation_id' => $this->org->id,
            'branch_id' => $this->branch->id,
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'valid_until' => now()->addDays(7)->toDateString(),
            'notes' => 'Tender delivery note',
            'lines' => [
                [
                    'product_id' => $this->amox->id,
                    'uom_id' => $this->uoms['BOX']->id,
                    'qty' => '10',
                    'requested_discount_pct' => '0',
                ],
            ],
        ]);

        $response = $this->get("/api/quotations/{$quotation->id}/pdf")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString($quotation->doc_number, (string) $response->headers->get('Content-Disposition'));

        $pdf = (string) $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf, 'must be a valid binary PDF');
        $this->assertGreaterThan(1000, strlen($pdf));

        $this->assertSame(
            1,
            AuditLog::where('action', 'QUOTATION_PRINTED')->where('entity_id', $quotation->id)->count(),
            'Quotation PDF print was audited'
        );
    }

    public function test_a_purchase_order_downloads_as_a_pdf_and_the_print_is_audited(): void
    {
        $this->grantPermissions(['po.create', 'po.approve']);

        $po = PurchaseOrder::create([
            'doc_number' => NumberSequence::next($this->org->id, 'PO', $this->branch->id, 'PO'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'status' => 'APPROVED',
            'expected_date' => now()->addDays(5)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->amox->id,
            'uom_id' => $this->uoms['BOX']->id,
            'qty_ordered' => '25.0000',
            'unit_price' => '380.0000',
        ]);

        $response = $this->get("/api/purchase-orders/{$po->id}/pdf")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString($po->doc_number, (string) $response->headers->get('Content-Disposition'));

        $pdf = (string) $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf, 'must be a valid binary PDF');
        $this->assertGreaterThan(1000, strlen($pdf));

        $this->assertSame(
            1,
            AuditLog::where('action', 'PURCHASE_ORDER_PRINTED')->where('entity_id', $po->id)->count(),
            'Purchase Order PDF print was audited'
        );
    }

    public function test_quotation_and_purchase_order_pdf_endpoints_enforce_permissions(): void
    {
        $this->revokeAllRoles();
        $this->grantPermissions(['stock.view'], 'Storekeeper');

        $quotation = app(QuotationService::class)->create([
            'organisation_id' => $this->org->id,
            'branch_id' => $this->branch->id,
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'valid_until' => now()->addDays(7)->toDateString(),
            'lines' => [
                [
                    'product_id' => $this->amox->id,
                    'uom_id' => $this->uoms['BOX']->id,
                    'qty' => '5',
                ],
            ],
        ]);

        $po = PurchaseOrder::create([
            'doc_number' => NumberSequence::next($this->org->id, 'PO', $this->branch->id, 'PO'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'status' => 'DRAFT',
            'created_by' => $this->user->id,
        ]);

        $this->get("/api/quotations/{$quotation->id}/pdf")->assertStatus(403);
        $this->get("/api/purchase-orders/{$po->id}/pdf")->assertStatus(403);
    }
}
