<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    use HasUuids;

    protected $fillable = ['store_id', 'code', 'aisle', 'rack', 'bin', 'capacity'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
