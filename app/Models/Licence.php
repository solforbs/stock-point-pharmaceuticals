<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Part 16.3 (V6) — a licence or certificate held by the organisation, a
 * branch, an employee or a supplier. Status is derived from the expiry date.
 *
 * @property-read Carbon $expiry_date
 * @property-read Carbon|null $issue_date
 */
class Licence extends Model
{
    use BelongsToOrganisation, HasUuids;

    /** Days before expiry at which a licence counts as EXPIRING. */
    public const EXPIRING_WITHIN_DAYS = 60;

    protected $fillable = [
        'organisation_id', 'holder_type', 'holder_id', 'licence_type', 'licence_number', 'issued_by',
        'issue_date', 'expiry_date', 'document_path', 'document_name', 'notes', 'is_active', 'created_by', 'updated_by',
    ];

    protected $hidden = ['document_path'];

    protected $appends = ['status', 'days_to_expiry', 'has_document'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date:Y-m-d',
            'expiry_date' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public static function statusFor(?Carbon $expiry): string
    {
        if ($expiry === null) {
            return 'VALID';
        }
        $days = (int) now()->startOfDay()->diffInDays($expiry->copy()->startOfDay(), false);

        return match (true) {
            $days < 0 => 'EXPIRED',
            $days <= self::EXPIRING_WITHIN_DAYS => 'EXPIRING',
            default => 'VALID',
        };
    }

    public function getStatusAttribute(): string
    {
        return self::statusFor($this->expiry_date);
    }

    public function getDaysToExpiryAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->expiry_date->copy()->startOfDay(), false);
    }

    public function getHasDocumentAttribute(): bool
    {
        return $this->document_path !== null;
    }
}
