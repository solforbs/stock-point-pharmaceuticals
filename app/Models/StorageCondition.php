<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StorageCondition extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'code', 'name', 'min_temp_c', 'max_temp_c',
        'max_excursion_minutes', 'requires_cold_chain',
    ];

    protected function casts(): array
    {
        return [
            'min_temp_c' => 'decimal:2',
            'max_temp_c' => 'decimal:2',
            'requires_cold_chain' => 'boolean',
        ];
    }

    public function isWithinRange(float $temperatureC): bool
    {
        if ($this->min_temp_c !== null && $temperatureC < $this->min_temp_c) {
            return false;
        }

        if ($this->max_temp_c !== null && $temperatureC > $this->max_temp_c) {
            return false;
        }

        return true;
    }
}
