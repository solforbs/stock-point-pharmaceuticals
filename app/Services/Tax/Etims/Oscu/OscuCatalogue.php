<?php

namespace App\Services\Tax\Etims\Oscu;

use App\Models\EtimsCode;
use App\Models\EtimsDevice;
use App\Models\EtimsItemClass;
use App\Models\EtimsTransmission;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\TaxCode;
use App\Services\Tax\Etims\EtimsNotConfiguredException;
use App\Services\Tax\Etims\EtimsTransmissionException;
use Illuminate\Support\Facades\DB;

/**
 * Everything KRA must know before a receipt can be filed: the device is
 * initialised (which issues its communication key), KRA's code lists and
 * item classes are downloaded, and each product is registered under its
 * KRA item code.
 */
class OscuCatalogue
{
    public function __construct(private readonly OscuClient $client) {}

    /** The device for a branch, ready to file — or why it is not. */
    public function deviceForBranch(string $branchId): EtimsDevice
    {
        $device = EtimsDevice::withoutGlobalScopes()->where('branch_id', $branchId)->first();
        if (! $device) {
            throw new EtimsNotConfiguredException('This branch has no eTIMS device set up.');
        }
        if (! $device->isActive()) {
            throw new EtimsNotConfiguredException("The eTIMS device {$device->device_serial} is not initialised yet.");
        }

        return $device;
    }

    /**
     * Initialises the device with KRA, which issues the communication key
     * every later call carries. KRA issues the key once per device.
     */
    public function initialise(EtimsDevice $device): EtimsDevice
    {
        $body = ['tin' => $device->tin, 'bhfId' => $device->bhf_id, 'dvcSrlNo' => $device->device_serial];
        $transmission = $this->open($device, 'init', null, 'selectInitOsdcInfo', $body);

        try {
            $result = $this->client->call($device, 'selectInitOsdcInfo', $body, withKey: false);
        } catch (EtimsTransmissionException $e) {
            $this->fail($transmission, $e);
            $device->forceFill(['status' => EtimsDevice::FAILED, 'last_error' => mb_substr($e->getMessage(), 0, 500)])->save();

            throw $e;
        }

        $cmcKey = $result['data']['info']['cmcKey'] ?? null;
        if (! is_string($cmcKey) || $cmcKey === '') {
            $e = new EtimsTransmissionException('KRA initialised the device but returned no communication key.');
            $this->fail($transmission, $e, $result['raw']);
            $device->forceFill(['status' => EtimsDevice::FAILED, 'last_error' => $e->getMessage()])->save();

            throw $e;
        }

        $this->succeed($transmission, $result, ['response_json' => $this->withoutKey($result['raw'])]);
        $device->forceFill(['cmc_key' => $cmcKey, 'status' => EtimsDevice::ACTIVE, 'last_error' => null, 'initialised_at' => now()])->save();

        return $device->fresh();
    }

    /**
     * Downloads KRA's code lists (tax types, units, payment types, refund
     * reasons…). Returns how many codes were stored.
     */
    public function syncCodes(EtimsDevice $device): int
    {
        $result = $this->client->call($device, 'selectCodeList', $this->base($device) + ['lastReqDt' => '20180101000000']);

        $count = 0;
        foreach ((array) ($result['data']['clsList'] ?? []) as $class) {
            foreach ((array) ($class['dtlList'] ?? []) as $detail) {
                EtimsCode::updateOrCreate(
                    ['code_class' => (string) $class['cdCls'], 'code' => (string) $detail['cd']],
                    [
                        'code_class_name' => $class['cdClsNm'] ?? null,
                        'name' => (string) ($detail['cdNm'] ?? $detail['cd']),
                        'description' => isset($detail['cdDesc']) ? mb_substr((string) $detail['cdDesc'], 0, 500) : null,
                        'sort_order' => (int) ($detail['srtOrd'] ?? 0),
                        'in_use' => ($detail['useYn'] ?? 'Y') === 'Y',
                    ],
                );
                $count++;
            }
        }

        return $count;
    }

    /** Downloads KRA's item classification. Returns how many classes were stored. */
    public function syncItemClasses(EtimsDevice $device): int
    {
        $result = $this->client->call($device, 'selectItemClsList', $this->base($device) + ['lastReqDt' => '20180101000000']);

        $count = 0;
        foreach ((array) ($result['data']['itemClsList'] ?? []) as $class) {
            EtimsItemClass::updateOrCreate(
                ['code' => (string) $class['itemClsCd']],
                [
                    'name' => mb_substr((string) ($class['itemClsNm'] ?? $class['itemClsCd']), 0, 300),
                    'level' => isset($class['itemClsLvl']) ? (int) $class['itemClsLvl'] : null,
                    'tax_type' => $class['taxTyCd'] ?? null,
                    'in_use' => ($class['useYn'] ?? 'Y') === 'Y',
                ],
            );
            $count++;
        }

        return $count;
    }

    /**
     * Registers the product with KRA for this device, once. The item code
     * is built the way KRA builds it — origin country, item type, packaging
     * unit, quantity unit and a seven-digit running number — and kept on
     * the product so it never changes.
     */
    public function ensureRegistered(EtimsDevice $device, Product $product): string
    {
        $itemCode = $this->itemCodeFor($product);

        $registered = EtimsTransmission::withoutGlobalScopes()
            ->where('etims_device_id', $device->id)->where('document_type', 'item')
            ->where('document_id', $product->id)->where('status', EtimsTransmission::SUCCESS)->exists();
        if ($registered) {
            return $itemCode;
        }

        $body = $this->base($device) + [
            'itemCd' => $itemCode,
            'itemClsCd' => $this->itemClassFor($product),
            'itemTyCd' => (string) config('etims.oscu.defaults.item_type', '2'),
            'itemNm' => mb_substr($product->name, 0, 200),
            'itemStdNm' => $product->brand_name ? mb_substr($product->brand_name, 0, 200) : null,
            'orgnNatCd' => $this->originFor($product),
            'pkgUnitCd' => $this->packagingUnitFor($product),
            'qtyUnitCd' => $this->quantityUnitFor($product),
            'taxTyCd' => $this->taxTypeFor($product->organisation_id, $product->tax_code_id),
            'btchNo' => null,
            'bcd' => $product->gtin ?: null,
            'dftPrc' => round((float) $product->default_price, 2),
            'grpPrcL1' => null, 'grpPrcL2' => null, 'grpPrcL3' => null, 'grpPrcL4' => null, 'grpPrcL5' => null,
            'addInfo' => $product->strength ? mb_substr((string) $product->strength, 0, 7) : null,
            'sftyQty' => null,
            'isrcAplcbYn' => 'N',
            'useYn' => $product->is_active ? 'Y' : 'N',
            'regrId' => 'stockpoint', 'regrNm' => 'Stockpoint',
            'modrId' => 'stockpoint', 'modrNm' => 'Stockpoint',
        ];

        $transmission = $this->open($device, 'item', $product->id, 'saveItem', $body);
        try {
            $result = $this->client->call($device, 'saveItem', $body);
        } catch (EtimsTransmissionException $e) {
            $this->fail($transmission, $e);

            throw new EtimsTransmissionException("{$product->name} could not be registered with KRA: {$e->getMessage()}", 0, $e);
        }
        $this->succeed($transmission, $result);

        return $itemCode;
    }

    public function itemCodeFor(Product $product): string
    {
        if ($product->etims_item_code) {
            return $product->etims_item_code;
        }

        return DB::transaction(function () use ($product) {
            // Serialise numbering within the institution.
            Organisation::whereKey($product->organisation_id)->lockForUpdate()->first();
            $fresh = Product::withoutGlobalScopes()->whereKey($product->id)->first();
            if ($fresh?->etims_item_code) {
                return $product->etims_item_code = $fresh->etims_item_code;
            }

            $prefix = $this->originFor($product).config('etims.oscu.defaults.item_type', '2')
                .$this->packagingUnitFor($product).$this->quantityUnitFor($product);
            $numbered = Product::withoutGlobalScopes()->where('organisation_id', $product->organisation_id)->whereNotNull('etims_item_code')->count();
            $code = $prefix.str_pad((string) ($numbered + 1), 7, '0', STR_PAD_LEFT);

            Product::withoutGlobalScopes()->whereKey($product->id)->update(['etims_item_code' => $code]);

            return $product->etims_item_code = $code;
        });
    }

    /**
     * KRA's tax type letter: A exempt, B 16%, C zero-rated, D non-VAT, E 8%.
     * A tax code can name its letter outright; otherwise it is read from the
     * code and its rate. An institution that is not VAT registered files D.
     */
    public function taxTypeFor(string $organisationId, ?string $taxCodeId, ?string $rate = null): string
    {
        $vatRegistered = (bool) (Organisation::whereKey($organisationId)->value('vat_registered') ?? true);
        if (! $vatRegistered) {
            return 'D';
        }

        $taxCode = $taxCodeId ? TaxCode::withoutGlobalScopes()->whereKey($taxCodeId)->first() : null;
        if ($taxCode?->etims_tax_type) {
            return $taxCode->etims_tax_type;
        }
        if ($taxCode === null) {
            return 'D';
        }

        $code = strtoupper((string) $taxCode->code);
        if (str_contains($code, 'EXEMPT')) {
            return 'A';
        }
        if (strtoupper((string) $taxCode->tax_type) !== 'VAT') {
            return 'D';
        }

        $rate ??= (string) (DB::table('tax_rates')->where('tax_code_id', $taxCode->id)
            ->where('effective_from', '<=', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()->toDateString()))
            ->orderByDesc('effective_from')->value('rate_pct') ?? '0');

        return match (true) {
            bccomp($rate, '16', 3) === 0 => 'B',
            bccomp($rate, '8', 3) === 0 => 'E',
            bccomp($rate, '0', 3) === 0 => 'C',
            default => 'B',
        };
    }

    /** KRA's VAT rate for each tax type letter. */
    public static function rateForType(string $type): string
    {
        return match ($type) {
            'B' => '16',
            'E' => '8',
            default => '0',
        };
    }

    private function itemClassFor(Product $product): string
    {
        $class = $product->etims_item_class ?: (string) config('etims.oscu.defaults.item_class');
        if ($class === '') {
            throw new EtimsNotConfiguredException("{$product->name} has no KRA item class, and no default item class is set (ETIMS_DEFAULT_ITEM_CLASS).");
        }

        return $class;
    }

    private function originFor(Product $product): string
    {
        return strtoupper($product->etims_origin_country ?: (string) config('etims.oscu.defaults.origin_country', 'KE'));
    }

    private function packagingUnitFor(Product $product): string
    {
        return strtoupper($product->etims_packaging_unit ?: (string) config('etims.oscu.defaults.packaging_unit', 'NT'));
    }

    private function quantityUnitFor(Product $product): string
    {
        return strtoupper($product->etims_quantity_unit ?: (string) config('etims.oscu.defaults.quantity_unit', 'U'));
    }

    /**
     * @return array{tin: string, bhfId: string}
     */
    public function base(EtimsDevice $device): array
    {
        return ['tin' => $device->tin, 'bhfId' => $device->bhf_id];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function open(EtimsDevice $device, string $type, ?string $documentId, string $endpoint, array $body, ?int $invoiceNo = null): EtimsTransmission
    {
        return EtimsTransmission::create([
            'organisation_id' => $device->organisation_id,
            'etims_device_id' => $device->id,
            'document_type' => $type,
            'document_id' => $documentId,
            'endpoint' => $endpoint,
            'invoice_no' => $invoiceNo,
            'status' => EtimsTransmission::PENDING,
            'request_json' => $body,
        ]);
    }

    /**
     * @param  array{result_code: string, result_message: string, raw: array<string, mixed>}  $result
     * @param  array<string, mixed>  $extra
     */
    public function succeed(EtimsTransmission $transmission, array $result, array $extra = []): void
    {
        $transmission->forceFill($extra + [
            'status' => EtimsTransmission::SUCCESS,
            'result_code' => $result['result_code'],
            'result_message' => mb_substr($result['result_message'], 0, 500),
            'response_json' => $result['raw'],
        ])->save();
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    public function fail(EtimsTransmission $transmission, \Throwable $e, ?array $response = null): void
    {
        $transmission->forceFill([
            'status' => EtimsTransmission::FAILED,
            'result_code' => $e instanceof EtimsRejectedException ? $e->resultCode : null,
            'result_message' => mb_substr($e->getMessage(), 0, 500),
            'response_json' => $response ?? ($e instanceof EtimsRejectedException ? $e->response : null),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function withoutKey(array $raw): array
    {
        if (isset($raw['data']['info']['cmcKey'])) {
            $raw['data']['info']['cmcKey'] = '[stored encrypted]';
        }

        return $raw;
    }
}
