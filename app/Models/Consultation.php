<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A clinician's record of one consultation within an encounter. */
class Consultation extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'encounter_id', 'clinician_id', 'chief_complaint', 'history', 'symptoms',
        'vital_signs', 'examination', 'clinical_notes', 'treatment_plan',
        'follow_up', 'follow_up_date', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'vital_signs' => 'array',
            'follow_up_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clinician_id');
    }
}
