<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleDiscountAuthority extends Model
{
    use HasUuids;

    protected $fillable = ['role_id', 'max_line_discount_pct', 'max_header_discount_pct', 'may_override_floor'];

    protected function casts(): array
    {
        return [
            'max_line_discount_pct' => 'decimal:3',
            'max_header_discount_pct' => 'decimal:3',
            'may_override_floor' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
