<?php

namespace App\Models;

use App\Models\Concerns\SharedAcrossOrganisations;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DosageForm extends Model
{
    use HasUuids, SharedAcrossOrganisations;

    protected $fillable = ['code', 'name'];
}
