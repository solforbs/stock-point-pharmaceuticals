<?php

namespace App\Models;

use App\Models\Concerns\SharedAcrossOrganisations;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Hematology, Biochemistry, … — platform-seeded, tenant-extensible. */
class LabTestCategory extends Model
{
    use HasUuids, SharedAcrossOrganisations;

    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tests(): HasMany
    {
        return $this->hasMany(LabTest::class, 'category_id');
    }
}
