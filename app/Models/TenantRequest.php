<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An institution asking for a quote from the public page. The platform
 * reviews it and, once approved, emails a one-time registration link.
 */
class TenantRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'institution_name', 'contact_name', 'email', 'phone', 'town', 'branches_count', 'users_count',
        'plan_id', 'message', 'ip',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
