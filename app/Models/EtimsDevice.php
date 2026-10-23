<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One branch's KRA eTIMS OSCU device: the PIN and branch id KRA approved,
 * the device serial, and the communication key issued when it was
 * initialised. The key never leaves the server.
 *
 * @property-read string $id
 * @property-read string $organisation_id
 * @property-read string $branch_id
 * @property-read string $environment
 * @property-read string $tin
 * @property-read string $bhf_id
 * @property-read string $device_serial
 * @property-read ?string $cmc_key
 * @property-read string $status
 * @property-read int $last_invoice_no
 * @property-read ?Carbon $initialised_at
 */
class EtimsDevice extends Model
{
    use BelongsToOrganisation, HasUuids;

    public const PENDING = 'PENDING';

    public const ACTIVE = 'ACTIVE';

    public const FAILED = 'FAILED';

    protected $fillable = [
        'organisation_id', 'branch_id', 'environment', 'tin', 'bhf_id', 'device_serial',
        'cmc_key', 'status', 'last_error', 'last_invoice_no', 'initialised_at',
    ];

    protected $hidden = ['cmc_key'];

    protected function casts(): array
    {
        return [
            'cmc_key' => 'encrypted',
            'last_invoice_no' => 'integer',
            'initialised_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE && $this->cmc_key !== null;
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<EtimsTransmission, $this>
     */
    public function transmissions(): HasMany
    {
        return $this->hasMany(EtimsTransmission::class);
    }
}
