<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organisation extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'legal_name', 'kra_pin', 'vat_registered',
        'base_currency', 'fiscal_year_start', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'vat_registered' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
