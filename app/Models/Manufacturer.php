<?php

namespace App\Models;

use App\Models\Concerns\SharedAcrossOrganisations;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Manufacturer extends Model
{
    use HasUuids, SharedAcrossOrganisations;

    protected $fillable = ['code', 'name', 'country', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
