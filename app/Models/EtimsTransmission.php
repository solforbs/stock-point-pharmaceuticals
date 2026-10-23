<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One call to KRA: what was sent, what came back and, for a sale or credit
 * note, the receipt number and signature the printed receipt carries.
 *
 * @property-read string $id
 * @property-read string $etims_device_id
 * @property-read string $document_type
 * @property-read ?string $document_id
 * @property-read ?int $invoice_no
 * @property-read string $status
 * @property-read ?int $receipt_no
 * @property-read ?int $total_receipt_no
 * @property-read ?string $receipt_signature
 * @property-read ?string $internal_data
 * @property-read ?string $sdc_datetime
 * @property-read ?string $qr_url
 */
class EtimsTransmission extends Model
{
    use BelongsToOrganisation, HasUuids;

    public const PENDING = 'PENDING';

    public const SUCCESS = 'SUCCESS';

    public const FAILED = 'FAILED';

    protected $fillable = [
        'organisation_id', 'etims_device_id', 'document_type', 'document_id', 'endpoint', 'invoice_no',
        'status', 'result_code', 'result_message', 'request_json', 'response_json',
        'receipt_no', 'total_receipt_no', 'receipt_signature', 'internal_data', 'sdc_datetime', 'qr_url',
    ];

    protected function casts(): array
    {
        return [
            'request_json' => 'array',
            'response_json' => 'array',
            'invoice_no' => 'integer',
            'receipt_no' => 'integer',
            'total_receipt_no' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<EtimsDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(EtimsDevice::class, 'etims_device_id');
    }
}
