<?php

namespace App\Services\Tax\Etims\Oscu;

use App\Models\CustomerReturn;
use App\Models\EtimsDevice;
use App\Models\EtimsTransmission;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Services\Tax\Etims\EtimsDriver;
use App\Services\Tax\Etims\EtimsResult;
use App\Services\Tax\Etims\EtimsTransmissionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Files sales and credit notes with KRA through the branch's OSCU device
 * (saveTrnsSalesOsdc). Prices go to KRA VAT-inclusive, as the customer
 * paid them, with each line's tax worked out under its KRA tax type and
 * the receipt totals summed per type A–E.
 *
 * The trader invoice number is a plain counter per device. A document keeps
 * the number it was first given, so a retry after a lost answer files the
 * same invoice again rather than a second one.
 */
class OscuDriver implements EtimsDriver
{
    private const TYPES = ['A', 'B', 'C', 'D', 'E'];

    public function __construct(private readonly OscuClient $client, private readonly OscuCatalogue $catalogue) {}

    public function submitInvoice(array $payload, ?Sale $sale = null): EtimsResult
    {
        if (! $sale) {
            throw new EtimsTransmissionException('The OSCU driver files a sale, not a bare payload.');
        }
        $sale->load(['lines.product', 'customer']); // the canonical payload loaded slimmer products
        $device = $this->catalogue->deviceForBranch($sale->branch_id);

        $items = [];
        foreach ($sale->lines as $line) {
            $itemCode = $this->catalogue->ensureRegistered($device, $line->product);
            $items[] = $this->saleItem($sale, $line, $itemCode, count($items) + 1);
        }

        $body = $this->receiptBody($device, $sale->organisation_id, [
            'rcptTyCd' => 'S',
            'orgInvcNo' => 0,
            'custTin' => $this->pinOrNull($sale->customer?->kra_pin),
            'custNm' => $sale->customer?->name,
            'pmtTyCd' => $this->paymentType($sale),
            'postedAt' => $sale->posted_at ?? now(),
            'rfdRsnCd' => null,
            'remark' => null,
            'userId' => (string) $sale->user_id,
            'customerPhone' => $sale->customer?->phone,
        ], $items);

        return $this->file($device, 'sale', $sale->id, $body);
    }

    public function submitCreditNote(array $payload, ?CustomerReturn $return = null): EtimsResult
    {
        if (! $return) {
            throw new EtimsTransmissionException('The OSCU driver files a credit note, not a bare payload.');
        }
        $return->load(['lines.product', 'lines.saleLine', 'sale.customer']);
        $device = $this->catalogue->deviceForBranch($return->branch_id);

        $original = EtimsTransmission::withoutGlobalScopes()
            ->where('etims_device_id', $device->id)->where('document_type', 'sale')
            ->where('document_id', $return->sale_id)->where('status', EtimsTransmission::SUCCESS)->first();
        if (! $original) {
            throw new EtimsTransmissionException('The original sale has not been filed with KRA yet; its credit note waits for it.');
        }

        $items = [];
        foreach ($return->lines as $line) {
            $itemCode = $this->catalogue->ensureRegistered($device, $line->product);
            $rate = (string) ($line->saleLine?->tax_rate ?? '0');
            $type = $this->catalogue->taxTypeFor($return->organisation_id, $line->saleLine?->tax_code_id, $rate);
            $qty = (string) $line->qty_base;
            $taxable = $this->money((string) $line->line_total);
            $items[] = $this->item(count($items) + 1, $line->product, $itemCode, $qty, $taxable, '0.00', $type, $this->money((string) $line->tax_amount));
        }

        $body = $this->receiptBody($device, $return->organisation_id, [
            'rcptTyCd' => 'R',
            'orgInvcNo' => (int) $original->invoice_no,
            'custTin' => $this->pinOrNull($return->sale?->customer?->kra_pin),
            'custNm' => $return->sale?->customer?->name,
            'pmtTyCd' => '01',
            'postedAt' => $return->posted_at ?? now(),
            'rfdRsnCd' => $this->refundReason((string) $return->reason),
            'remark' => mb_substr((string) $return->reason, 0, 400) ?: null,
            'userId' => (string) ($return->posted_by ?? $return->created_by ?? 'stockpoint'),
            'customerPhone' => $return->sale?->customer?->phone,
        ], $items);

        return $this->file($device, 'credit_note', $return->id, $body);
    }

    /**
     * One sale line in KRA's terms. The line total the customer paid is the
     * taxable amount; a discount is sent VAT-inclusive alongside it.
     *
     * @return array<string, mixed>
     */
    private function saleItem(Sale $sale, SaleLine $line, string $itemCode, int $sequence): array
    {
        $rate = (string) $line->tax_rate;
        $type = $this->catalogue->taxTypeFor($sale->organisation_id, $line->tax_code_id, $rate);
        $grossFactor = bcadd('1', bcdiv($rate, '100', 6), 6);
        $discount = $this->money(bcmul((string) $line->discount_amount, $grossFactor, 6));

        return $this->item($sequence, $line->product, $itemCode, (string) $line->qty, $this->money((string) $line->line_total), $discount, $type, $this->money((string) $line->tax_amount));
    }

    /**
     * @return array<string, mixed>
     */
    private function item(int $sequence, Product $product, string $itemCode, string $qty, string $taxable, string $discount, string $type, string $tax): array
    {
        $supply = $this->money(bcadd($taxable, $discount, 4));
        $price = bccomp($qty, '0', 4) > 0 ? $this->money(bcdiv($supply, $qty, 6)) : '0.00';

        return [
            'itemSeq' => $sequence,
            'itemCd' => $itemCode,
            'itemClsCd' => $product->etims_item_class ?: (string) config('etims.oscu.defaults.item_class'),
            'itemNm' => mb_substr($product->name, 0, 200),
            'bcd' => $product->gtin ?: null,
            'pkgUnitCd' => strtoupper($product->etims_packaging_unit ?: (string) config('etims.oscu.defaults.packaging_unit', 'NT')),
            'pkg' => (float) $qty,
            'qtyUnitCd' => strtoupper($product->etims_quantity_unit ?: (string) config('etims.oscu.defaults.quantity_unit', 'U')),
            'qty' => (float) $qty,
            'prc' => (float) $price,
            'splyAmt' => (float) $supply,
            'dcRt' => bccomp($supply, '0', 2) > 0 ? round((float) bcdiv(bcmul($discount, '100', 6), $supply, 6), 2) : 0.0,
            'dcAmt' => (float) $discount,
            'isrccCd' => null, 'isrccNm' => null, 'isrcRt' => null, 'isrcAmt' => null,
            'taxTyCd' => $type,
            'taxblAmt' => (float) $taxable,
            'taxAmt' => (float) $tax,
            'totAmt' => (float) $taxable,
        ];
    }

    /**
     * The receipt KRA files: header, per-type totals and the items.
     *
     * @param  array{rcptTyCd: string, orgInvcNo: int, custTin: ?string, custNm: ?string, pmtTyCd: string, postedAt: Carbon, rfdRsnCd: ?string, remark: ?string, userId: string, customerPhone: ?string}  $header
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function receiptBody(EtimsDevice $device, string $organisationId, array $header, array $items): array
    {
        $taxable = array_fill_keys(self::TYPES, '0');
        $tax = array_fill_keys(self::TYPES, '0');
        foreach ($items as $item) {
            $taxable[$item['taxTyCd']] = bcadd($taxable[$item['taxTyCd']], (string) $item['taxblAmt'], 2);
            $tax[$item['taxTyCd']] = bcadd($tax[$item['taxTyCd']], (string) $item['taxAmt'], 2);
        }

        $organisation = Organisation::find($organisationId);
        $posted = $header['postedAt'];
        $body = $this->catalogue->base($device) + [
            'invcNo' => 0, // set when the number is reserved
            'orgInvcNo' => $header['orgInvcNo'],
            'custTin' => $header['custTin'],
            'custNm' => $header['custNm'] ? mb_substr($header['custNm'], 0, 60) : null,
            'salesTyCd' => 'N',
            'rcptTyCd' => $header['rcptTyCd'],
            'pmtTyCd' => $header['pmtTyCd'],
            'salesSttsCd' => '02',
            'cfmDt' => $posted->format('YmdHis'),
            'salesDt' => $posted->format('Ymd'),
            'stockRlsDt' => $posted->format('YmdHis'),
            'cnclReqDt' => null,
            'cnclDt' => null,
            'rfdDt' => $header['rcptTyCd'] === 'R' ? $posted->format('YmdHis') : null,
            'rfdRsnCd' => $header['rfdRsnCd'],
            'totItemCnt' => count($items),
        ];
        foreach (self::TYPES as $type) {
            $body["taxblAmt{$type}"] = (float) $taxable[$type];
        }
        foreach (self::TYPES as $type) {
            $body["taxRt{$type}"] = (float) OscuCatalogue::rateForType($type);
        }
        foreach (self::TYPES as $type) {
            $body["taxAmt{$type}"] = (float) $tax[$type];
        }

        return $body + [
            'totTaxblAmt' => (float) array_reduce($taxable, fn ($sum, $amount) => bcadd($sum, $amount, 2), '0'),
            'totTaxAmt' => (float) array_reduce($tax, fn ($sum, $amount) => bcadd($sum, $amount, 2), '0'),
            'totAmt' => (float) array_reduce($taxable, fn ($sum, $amount) => bcadd($sum, $amount, 2), '0'),
            'prchrAcptcYn' => 'N',
            'remark' => $header['remark'],
            'regrId' => $header['userId'], 'regrNm' => $header['userId'],
            'modrId' => $header['userId'], 'modrNm' => $header['userId'],
            'receipt' => [
                'custTin' => $header['custTin'],
                'custMblNo' => $header['customerPhone'] ? mb_substr($header['customerPhone'], 0, 20) : null,
                'rptNo' => null,
                'trdeNm' => $organisation?->name ? mb_substr($organisation->name, 0, 20) : null,
                'adrs' => $organisation?->physical_address ? mb_substr($organisation->physical_address, 0, 200) : null,
                'topMsg' => null,
                'btmMsg' => 'Thank you',
                'prchrAcptcYn' => 'N',
            ],
            'itemList' => $items,
        ];
    }

    /**
     * Reserves the trader invoice number (reusing the one this document was
     * already given), sends the receipt and records KRA's signature.
     *
     * @param  array<string, mixed>  $body
     */
    private function file(EtimsDevice $device, string $type, string $documentId, array $body): EtimsResult
    {
        $transmission = DB::transaction(function () use ($device, $type, $documentId, $body) {
            $locked = EtimsDevice::withoutGlobalScopes()->whereKey($device->id)->lockForUpdate()->first();
            $previous = EtimsTransmission::withoutGlobalScopes()
                ->where('etims_device_id', $device->id)->whereIn('document_type', ['sale', 'credit_note'])
                ->where('document_type', $type)->where('document_id', $documentId)
                ->whereNotNull('invoice_no')->orderByDesc('created_at')->first();

            $invoiceNo = $previous?->invoice_no;
            if ($invoiceNo === null) {
                $invoiceNo = $locked->last_invoice_no + 1;
                $locked->forceFill(['last_invoice_no' => $invoiceNo])->save();
            }
            $body['invcNo'] = $invoiceNo;

            if ($previous) {
                $previous->forceFill(['request_json' => $body, 'status' => EtimsTransmission::PENDING])->save();

                return $previous;
            }

            return $this->catalogue->open($device, $type, $documentId, 'saveTrnsSalesOsdc', $body, $invoiceNo);
        });

        try {
            $result = $this->client->call($device, 'saveTrnsSalesOsdc', $transmission->request_json);
        } catch (EtimsTransmissionException $e) {
            $this->catalogue->fail($transmission, $e);

            throw $e;
        }

        $data = $result['data'];
        $signature = (string) ($data['rcptSign'] ?? '');
        $receiptNo = $data['curRcptNo'] ?? null;
        if ($signature === '' || $receiptNo === null) {
            $e = new EtimsTransmissionException('KRA accepted the receipt but returned no signature.');
            $this->catalogue->fail($transmission, $e, $result['raw']);

            throw $e;
        }

        $qr = (string) config("etims.oscu.receipt_urls.{$device->environment}").$device->tin.$device->bhf_id.$signature;
        $this->catalogue->succeed($transmission, $result, [
            'receipt_no' => (int) $receiptNo,
            'total_receipt_no' => isset($data['totRcptNo']) ? (int) $data['totRcptNo'] : null,
            'receipt_signature' => $signature,
            'internal_data' => isset($data['intrlData']) ? (string) $data['intrlData'] : null,
            'sdc_datetime' => isset($data['sdcDateTime']) ? (string) $data['sdcDateTime'] : null,
            'qr_url' => $qr,
        ]);

        return new EtimsResult($signature, $device->device_serial.'/'.$receiptNo, $qr);
    }

    /**
     * KRA payment types: 01 cash, 02 credit, 03 cash/credit, 04 bank cheque,
     * 05 card, 06 mobile money, 07 other.
     */
    private function paymentType(Sale $sale): string
    {
        $methods = DB::table('payments')
            ->join('payment_allocations', 'payment_allocations.payment_id', '=', 'payments.id')
            ->where('payment_allocations.allocated_to_type', 'sale')
            ->where('payment_allocations.allocated_to_id', $sale->id)
            ->distinct()->pluck('payments.method')->all();

        if ($methods === []) {
            return '02';
        }
        if (count($methods) > 1) {
            return $sale->isCreditSale() ? '03' : '07';
        }

        return match ($methods[0]) {
            'CASH' => $sale->isCreditSale() ? '03' : '01',
            'MPESA' => '06',
            'CARD' => '05',
            'CHEQUE' => '04',
            default => '07',
        };
    }

    /**
     * KRA refund reasons — 03 damaged, 06 refund, 11 wrong item… — read from
     * the return's reason; anything else is the configured default.
     */
    private function refundReason(string $reason): string
    {
        return match (strtoupper($reason)) {
            'DAMAGED' => '03',
            'WRONG_ITEM' => '11',
            'WRONG_QUANTITY' => '10',
            'WRONG_PRICE' => '09',
            default => (string) config('etims.oscu.defaults.refund_reason', '06'),
        };
    }

    private function pinOrNull(?string $pin): ?string
    {
        $pin = strtoupper(trim((string) $pin));

        return preg_match('/^[AP]\d{9}[A-Z]$/', $pin) === 1 ? $pin : null;
    }

    private function money(string $amount): string
    {
        return bcadd($amount, bccomp($amount, '0', 6) >= 0 ? '0.005' : '-0.005', 2);
    }
}
