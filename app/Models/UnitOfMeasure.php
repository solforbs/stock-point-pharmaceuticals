<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $code
 * @property-read string $name
 */
class UnitOfMeasure extends Model
{
    use HasUuids;

    protected $table = 'units_of_measure';

    protected $fillable = ['code', 'name', 'is_base_candidate'];

    protected function casts(): array
    {
        return ['is_base_candidate' => 'boolean'];
    }
}
