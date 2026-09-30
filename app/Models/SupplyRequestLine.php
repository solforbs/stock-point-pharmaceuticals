<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupplyRequestLine extends Model
{
    use HasUuids;

    protected $fillable = ['supply_request_id', 'item', 'category', 'qty', 'unit', 'est_unit_cost', 'actual_unit_cost'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'est_unit_cost' => 'decimal:4',
            'actual_unit_cost' => 'decimal:4',
        ];
    }
}
