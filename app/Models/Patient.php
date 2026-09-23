<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One person, one record. Repeat visits create encounters, never another
 * patient row — the patient_no (PAT-YYYY-000001) is the lifetime identifier.
 */
class Patient extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'patient_no', 'first_name', 'last_name', 'sex', 'date_of_birth', 'phone', 'email',
        'national_id', 'address', 'next_of_kin_name', 'next_of_kin_phone', 'notes',
        'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'is_active' => 'boolean'];
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class)->orderByDesc('started_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /** Reception's search box: number, name, phone or national id. */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($term)).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('patient_no', 'like', $like)
            ->orWhere('first_name', 'like', $like)
            ->orWhere('last_name', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhere('national_id', 'like', $like));
    }
}
