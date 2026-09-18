<?php

namespace Tests\Feature\Api;

use App\Models\CustomerReturn;
use App\Models\Sale;
use App\Services\Tax\Etims\EtimsDriver;
use App\Services\Tax\Etims\EtimsResult;
use App\Services\Tax\Etims\EtimsTransmissionException;
use App\Services\Tax\Etims\LogDriver;
use App\Services\Tax\EtimsService;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 13.6 — asynchronous, retried, never blocking. The sale posts whether
 * or not KRA answers; the queue screen shows what is outstanding.
 */
class EtimsHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('E1', now()->addYears(2)->toDateString(), '2000', '2.0000');
        $this->grantPermissions(['sale.create', 'sale.view', 'return.create', 'return.post', 'tax.etims.manage', 'stock.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_a_sale_is_fiscalised_after_posting_and_its_credit_note_references_the_control_code(): void
    {
        config(['etims.enabled' => true, 'etims.driver' => 'log']);

        $sale = $this->postSale('et-1');
        $this->assertSame('SUBMITTED', $sale->etims_status);
        $this->assertStringStartsWith('LOG-', (string) $sale->etims_control_code, 'the log driver never pretends to be KRA');
        $this->assertNotNull($sale->etims_submitted_at);

        $this->getJson('/api/etims/queue?status=SUBMITTED')->assertOk()
            ->assertJsonPath('summary.submitted', 1)
            ->assertJsonPath('summary.failed', 0)
            ->assertJsonPath('data.0.doc_number', $sale->doc_number);

        $return = $this->postJson('/api/customer-returns', [
            'sale_id' => $sale->id, 'store_id' => $this->store->id, 'reason' => 'Wrong item picked', 'refund_method' => 'CASH',
            'lines' => [['sale_line_id' => $sale->lines()->first()->id, 'batch_id' => $sale->lines()->first()->batchAllocations()->first()->batch_id, 'qty_base' => '200']],
        ])->assertCreated()->json();
        $this->postJson("/api/customer-returns/{$return['id']}/post")->assertOk();

        $note = CustomerReturn::findOrFail($return['id']);
        $this->assertSame('SUBMITTED', $note->etims_status);
        $this->assertStringStartsWith('LOG-CN-', (string) $note->etims_invoice_number);
        $this->assertSame($sale->etims_control_code, app(EtimsService::class)->creditNotePayload($note)['original_control_code']);
    }

    public function test_a_kra_outage_never_blocks_the_sale_and_can_be_retried_from_the_queue(): void
    {
        config(['etims.enabled' => true, 'etims.driver' => 'log']);
        $this->app->bind(EtimsDriver::class, fn () => new class implements EtimsDriver
        {
            public function submitInvoice(array $payload): EtimsResult
            {
                throw new EtimsTransmissionException('KRA gateway unreachable: connection timed out');
            }

            public function submitCreditNote(array $payload): EtimsResult
            {
                throw new EtimsTransmissionException('KRA gateway unreachable');
            }
        });

        $sale = $this->postSale('et-2');
        $this->assertSame('POSTED', $sale->status, 'the business keeps selling');
        $this->assertSame('FAILED', $sale->etims_status);
        $this->assertStringContainsString('unreachable', (string) $sale->etims_error);

        $this->getJson('/api/etims/queue')->assertOk()
            ->assertJsonPath('summary.failed', 1)
            ->assertJsonPath('data.0.type', 'sale')
            ->assertJsonPath('data.0.etims_status', 'FAILED');

        $this->postJson("/api/etims/sales/{$sale->id}/retry")->assertStatus(502)->assertJsonPath('error.code', 'ETIMS_TRANSMISSION_FAILED');

        // The gateway is back.
        $this->app->bind(EtimsDriver::class, fn () => new LogDriver);
        $this->postJson("/api/etims/sales/{$sale->id}/retry")->assertOk()->assertJsonPath('etims_status', 'SUBMITTED');
        $this->getJson('/api/etims/queue')->assertJsonPath('summary.failed', 0)->assertJsonPath('summary.submitted', 1);
    }

    public function test_an_unconfigured_server_tracks_every_sale_as_not_configured(): void
    {
        config(['etims.enabled' => false]);

        $sale = $this->postSale('et-3');
        $this->assertSame('NOT_CONFIGURED', $sale->etims_status);
        $this->postJson("/api/etims/sales/{$sale->id}/retry")->assertStatus(422)->assertJsonPath('error.code', 'ETIMS_NOT_CONFIGURED');

        $this->grantPermissions(['sale.view']);
        $this->getJson('/api/etims/queue')->assertStatus(403);
    }

    private function postSale(string $key): Sale
    {
        $quote = $this->postJson('/api/pricing/quote', [
            'sale_mode' => 'RETAIL', 'store_id' => $this->store->id,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => '2']],
        ])->assertOk()->json();
        $id = $this->postJson('/api/sales/checkout', [
            'quote_id' => $quote['quote_id'], 'store_id' => $this->store->id,
            'payments' => [['method' => 'CASH', 'amount' => $quote['totals']['grand_total']]],
        ], ['Idempotency-Key' => $key])->assertCreated()->json('id');

        return Sale::findOrFail($id);
    }
}
