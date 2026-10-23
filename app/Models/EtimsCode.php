<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An entry in one of KRA's national code lists (tax types, packaging and
 * quantity units, payment types, refund reasons…), as downloaded from eTIMS.
 *
 * @property-read string $code_class
 * @property-read string $code
 * @property-read string $name
 */
class EtimsCode extends Model
{
    protected $fillable = ['code_class', 'code_class_name', 'code', 'name', 'description', 'sort_order', 'in_use'];

    protected function casts(): array
    {
        return ['in_use' => 'boolean', 'sort_order' => 'integer'];
    }
}
