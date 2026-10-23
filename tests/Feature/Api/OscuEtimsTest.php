<?php

namespace Tests\Feature\Api;

use App\Models\CustomerReturn;
use App\Models\EtimsDevice;
use App\Models\EtimsTransmission;
use App\Models\Sale;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * KRA eTIMS through an OSCU device, against a faked KRA sandbox: the device
 * is initialised once, each product is registered before its first sale,
 * sales and credit notes are filed in KRA's own format with a running
 * trader invoice number, and KRA's signature comes back onto the document.
 */
class OscuEtimsTest extends TestCase
{
    use BuildsBlueprintWorld;

    private const BASE = 'https://etims-api-sbx.kra.go.ke/etims-api';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('K1', now()->addYears(2)->toDateString(), '2000', '2.0000');
        $this->grantPermissions(['sale.create', 'sale.view', 'return.create', 'return.post', 'tax.etims.manage', 'stock.view']);
        Sanctum::actingAs($this->user);
        config(['etims.enabled' => true, 'etims.driver' => 'oscu', 'etims.oscu.defaults.item_class' => '5100000000']);
    }

    public function test_setting_up_a_device_stores_the_key_kra_issues_encrypted_and_never_logs_it(): void
    {
        Http::fake([self::BASE.'/selectInitOsdcInfo' => Http::response($this->ok(['info' => ['cmcKey' => 'SECRET-KEY-1', 'bhfNm' => 'Head office']]))]);

        $this->artisan('etims:device', ['branch' => $this->branch->code, '--pin' => 'P051234567M', '--serial' => 'SBX-001', '--environment' => 'sandbox'])
            ->assertSuccessful();

        $device = EtimsDevice::where('branch_id', $this->branch->id)->firstOrFail();
        $this->assertSame('ACTIVE', $device->status);
        $this->assertSame('SECRET-KEY-1', $device->cmc_key);
        $this->assertStringNotContainsString('SECRET-KEY-1', (string) DB::table('etims_devices')->where('id', $device->id)->value('cmc_key'), 'stored encrypted');
        $this->assertStringNotContainsString('SECRET-KEY-1', json_encode(EtimsTransmission::where('document_type', 'init')->firstOrFail()->response_json));

        Http::assertSent(fn (Request $request) => $request->url() === self::BASE.'/selectInitOsdcInfo'
            && $request['dvcSrlNo'] === 'SBX-001' && $request['tin'] === 'P051234567M' && ! $request->hasHeader('cmcKey'));
    }

    public function test_a_sale_is_filed_in_kra_format_and_carries_kras_signature(): void
    {
        $this->vat16();
        $this->org->forceFill(['vat_registered' => true])->save();
        $this->activeDevice();
        Http::fake([
            self::BASE.'/saveItem' => Http::response($this->ok()),
            self::BASE.'/saveTrnsSalesOsdc' => Http::sequence()
                ->push($this->ok(['curRcptNo' => 41, 'totRcptNo' => 41, 'intrlData' => 'INTRL1', 'rcptSign' => 'SIGN0001', 'sdcDateTime' => '20261023120000']))
                ->push($this->ok(['curRcptNo' => 42, 'totRcptNo' => 42, 'intrlData' => 'INTRL2', 'rcptSign' => 'SIGN0002', 'sdcDateTime' => '20261023120100'])),
        ]);

        $sale = $this->postSale('oscu-1');
        $this->assertSame('SUBMITTED', $sale->etims_status, (string) $sale->etims_error);
        $this->assertSame('SIGN0001', $sale->etims_control_code);
        $this->assertSame('SBX-001/41', $sale->etims_invoice_number);

        $receipt = collect(Http::recorded())->map(fn ($pair) => $pair[0])->first(fn (Request $r) => str_ends_with($r->url(), '/saveTrnsSalesOsdc'));
        $this->assertSame('KEY-1', $receipt->header('cmcKey')[0]);
        $this->assertSame(1, $receipt['invcNo']);
        $this->assertSame('S', $receipt['rcptTyCd']);
        $this->assertSame('01', $receipt['pmtTyCd'], 'paid in cash');
        $this->assertSame('B', $receipt['itemList'][0]['taxTyCd'], '16% VAT');
        $this->assertEquals((float) $sale->grand_total, $receipt['totAmt'], 'KRA receives what the customer paid');
        $this->assertEquals((float) $sale->tax_total, $receipt['taxAmtB']);
        $this->assertEquals(16, $receipt['taxRtB']);
        $this->assertStringStartsWith('KE2NTU', $receipt['itemList'][0]['itemCd']);

        $transmission = EtimsTransmission::where('document_type', 'sale')->where('document_id', $sale->id)->firstOrFail();
        $this->assertStringEndsWith('P051234567M00SIGN0001', (string) $transmission->qr_url);

        $second = $this->postSale('oscu-2');
        $this->assertSame('SBX-001/42', $second->etims_invoice_number);
        Http::assertSentCount(3);  // one item registration, two receipts
        $this->assertSame(2, EtimsTransmission::where('document_type', 'sale')->where('document_id', $second->id)->value('invoice_no'));
    }

    public function test_a_refused_receipt_is_retried_under_the_same_invoice_number(): void
    {
        $this->activeDevice();
        Http::fake([
            self::BASE.'/saveItem' => Http::response($this->ok()),
            self::BASE.'/saveTrnsSalesOsdc' => Http::sequence()
                ->push(['resultCd' => '910', 'resultMsg' => 'Request parameter error', 'data' => null])
                ->push($this->ok(['curRcptNo' => 7, 'totRcptNo' => 7, 'intrlData' => 'X', 'rcptSign' => 'SIGNRETRY', 'sdcDateTime' => '20261023120000'])),
        ]);

        $sale = $this->postSale('oscu-3');
        $this->assertSame('FAILED', $sale->etims_status);
        $this->assertStringContainsString('910', (string) $sale->etims_error);

        $this->postJson("/api/etims/sales/{$sale->id}/retry")->assertOk()->assertJsonPath('etims_status', 'SUBMITTED');

        $numbers = collect(Http::recorded())->map(fn ($pair) => $pair[0])
            ->filter(fn (Request $r) => str_ends_with($r->url(), '/saveTrnsSalesOsdc'))->map(fn (Request $r) => $r['invcNo'])->values()->all();
        $this->assertSame([1, 1], $numbers, 'the retry files the same invoice, not a second one');
        $this->assertSame(1, EtimsDevice::where('branch_id', $this->branch->id)->value('last_invoice_no'));
    }

    public function test_a_credit_note_is_filed_as_a_refund_of_the_original_invoice(): void
    {
        $this->activeDevice();
        Http::fake([
            self::BASE.'/saveItem' => Http::response($this->ok()),
            self::BASE.'/saveTrnsSalesOsdc' => Http::sequence()
                ->push($this->ok(['curRcptNo' => 1, 'totRcptNo' => 1, 'intrlData' => 'A', 'rcptSign' => 'SALESIGN', 'sdcDateTime' => '20261023120000']))
                ->push($this->ok(['curRcptNo' => 2, 'totRcptNo' => 2, 'intrlData' => 'B', 'rcptSign' => 'REFUNDSIGN', 'sdcDateTime' => '20261023120500'])),
        ]);

        $sale = $this->postSale('oscu-4');
        $return = $this->postJson('/api/customer-returns', [
            'sale_id' => $sale->id, 'store_id' => $this->store->id, 'reason' => 'DAMAGED', 'refund_method' => 'CASH',
            'lines' => [['sale_line_id' => $sale->lines()->first()->id, 'batch_id' => $sale->lines()->first()->batchAllocations()->first()->batch_id, 'qty_base' => '200']],
        ])->assertCreated()->json();
        $this->postJson("/api/customer-returns/{$return['id']}/post")->assertOk();

        $note = CustomerReturn::findOrFail($return['id']);
        $this->assertSame('SUBMITTED', $note->etims_status, (string) $note->etims_error);
        $this->assertSame('REFUNDSIGN', $note->etims_control_code);

        $refund = collect(Http::recorded())->map(fn ($pair) => $pair[0])
            ->filter(fn (Request $r) => str_ends_with($r->url(), '/saveTrnsSalesOsdc'))->last();
        $this->assertSame('R', $refund['rcptTyCd']);
        $this->assertSame(1, $refund['orgInvcNo'], 'it refunds the original invoice');
        $this->assertSame(2, $refund['invcNo']);
        $this->assertSame('03', $refund['rfdRsnCd'], 'damaged');
    }

    public function test_a_branch_without_an_initialised_device_records_why_and_keeps_selling(): void
    {
        Http::fake();

        $sale = $this->postSale('oscu-5');
        $this->assertSame('POSTED', $sale->status);
        $this->assertSame('FAILED', $sale->etims_status);
        $this->assertStringContainsString('no eTIMS device', (string) $sale->etims_error);
        Http::assertNothingSent();
    }

    private function activeDevice(): EtimsDevice
    {
        return EtimsDevice::create([
            'organisation_id' => $this->org->id, 'branch_id' => $this->branch->id, 'environment' => 'sandbox',
            'tin' => 'P051234567M', 'bhf_id' => '00', 'device_serial' => 'SBX-001',
            'cmc_key' => 'KEY-1', 'status' => EtimsDevice::ACTIVE, 'initialised_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    private function ok(?array $data = null): array
    {
        return ['resultCd' => '000', 'resultMsg' => 'It is succeeded', 'resultDt' => '20261023120000', 'data' => $data];
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
