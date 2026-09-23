<?php

namespace App\Models;

use App\Models\Concerns\SharedAcrossOrganisations;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable hospital classification (Level 1–6, Referral Hospital, …).
 * Platform-seeded rows are shared; a tenant may add its own.
 */
class HospitalLevel extends Model
{
    use HasUuids, SharedAcrossOrganisations;

    protected $fillable = ['name', 'rank', 'is_active'];

    protected function casts(): array
    {
        return ['rank' => 'integer', 'is_active' => 'boolean'];
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }
}
