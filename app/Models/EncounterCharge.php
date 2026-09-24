<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A visit-level billing line (consultation fee, lab test, procedure).
 * Medicine charges are pharmacy Sales, never encounter charges.
 */
class EncounterCharge extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'encounter_id', 'charge_type', 'description', 'amount', 'status',
        'source_type', 'source_id', 'paid_at', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
