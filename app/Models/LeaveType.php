<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Part 21.16 — a kind of leave and its yearly entitlement.
 *
 * @property-read string $days_per_year
 * @property-read string $carry_forward_max
 * @property-read bool $is_paid
 */
class LeaveType extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'code', 'name', 'days_per_year', 'is_paid', 'requires_document', 'carry_forward_max', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'days_per_year' => 'decimal:2',
            'carry_forward_max' => 'decimal:2',
            'is_paid' => 'boolean',
            'requires_document' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
