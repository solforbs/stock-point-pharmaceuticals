<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $licence_expiry
 */
class Supplier extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'code', 'name', 'contact_name', 'email', 'phone', 'address',
        'licence_number', 'licence_expiry', 'payment_terms_days', 'lead_time_days', 'currency',
        'bank_name', 'bank_account', 'status', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'licence_expiry' => 'date',
            'is_active' => 'boolean',
            // Bank details are a classic fraud vector (Part 19.2) — encrypted
            // at rest, decrypted transparently for authorised reads.
            'bank_name' => 'encrypted',
            'bank_account' => 'encrypted',
        ];
    }

    public function isLicenceExpired(): bool
    {
        return $this->licence_expiry !== null && $this->licence_expiry->isPast();
    }
}
