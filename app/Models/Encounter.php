<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The clinical spine: one patient visit at one facility. Consultations,
 * diagnoses, referrals, lab orders, prescriptions and charges all hang off
 * the encounter. Statuses: REGISTERED → WAITING → IN_CONSULTATION →
 * AWAITING_RESULTS → COMPLETED, with CANCELLED from any open state.
 */
class Encounter extends Model
{
    use BelongsToOrganisation, HasUuids;

    public const OPEN_STATUSES = ['REGISTERED', 'WAITING', 'IN_CONSULTATION', 'AWAITING_RESULTS'];

    protected $fillable = [
        'organisation_id', 'encounter_no', 'patient_id', 'facility_id', 'department_id', 'encounter_type',
        'status', 'attending_clinician_id', 'consultation_fee', 'fee_status',
        'presenting_notes', 'created_by', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function attendingClinician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attending_clinician_id');
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(EncounterCharge::class);
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
