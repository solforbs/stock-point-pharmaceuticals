<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A clinician's request for tests, worked by the laboratory through a
 * guarded status chain. Transitions live in LabOrderService — nothing
 * writes `status` directly.
 */
class LabOrder extends Model
{
    use BelongsToOrganisation, HasUuids;

    public const STATUSES = ['PENDING', 'ACCEPTED', 'SAMPLE_COLLECTED', 'PROCESSING', 'COMPLETED', 'CANCELLED'];

    protected $fillable = [
        'organisation_id', 'order_no', 'encounter_id', 'patient_id', 'facility_id', 'ordered_by',
        'clinical_notes', 'status', 'accepted_by', 'accepted_at',
        'completed_by', 'completed_at', 'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Orders this user may work with: performed by one of their laboratories,
     * or ordered from a visit at one of their facilities (so a clinician still
     * reads results from an outside laboratory).
     */
    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereIn($q->qualifyColumn('facility_id'), Facility::query()->accessibleTo($user)->select('facilities.id'))
            ->orWhereHas('encounter', fn (Builder $encounter) => $encounter->accessibleTo($user)));
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(LabOrderTest::class);
    }

    public function samples(): HasMany
    {
        return $this->hasMany(LabSample::class);
    }
}
