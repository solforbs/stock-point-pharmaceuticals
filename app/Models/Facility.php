<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The umbrella for the healthcare service modules. A facility offers any
 * combination of Hospital, Laboratory and Pharmacy — an independent chemist
 * is a facility with only offers_pharmacy, a diagnostic centre only
 * offers_laboratory. Its pharmacy service points at an existing Branch so
 * prescriptions route into the running pharmacy, never a parallel one.
 */
class Facility extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'code', 'name', 'hospital_level_id', 'offers_hospital', 'offers_laboratory',
        'offers_pharmacy', 'pharmacy_branch_id', 'phone', 'email', 'address', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'offers_hospital' => 'boolean',
            'offers_laboratory' => 'boolean',
            'offers_pharmacy' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function hospitalLevel(): BelongsTo
    {
        return $this->belongsTo(HospitalLevel::class);
    }

    public function pharmacyBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'pharmacy_branch_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'facility_user')
            ->withPivot(['department_id', 'job_title'])->withTimestamps();
    }

    /**
     * Facility-level access: users see only facilities they are assigned to,
     * unless they hold the module's manage permission (facility admin).
     */
    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('hospital.manage') || $user->can('laboratory.manage')) {
            return $query;
        }

        return $query->whereHas('staff', fn (Builder $q) => $q->where('users.id', $user->id));
    }
}
