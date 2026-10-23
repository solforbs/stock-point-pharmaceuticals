<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * KRA's item classification, as downloaded from eTIMS. Every product sent
 * to KRA carries one of these codes.
 *
 * @property-read string $code
 * @property-read string $name
 * @property-read ?string $tax_type
 */
class EtimsItemClass extends Model
{
    protected $fillable = ['code', 'name', 'level', 'tax_type', 'in_use'];

    protected function casts(): array
    {
        return ['in_use' => 'boolean', 'level' => 'integer'];
    }
}
