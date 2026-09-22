<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A role belongs to one institution: each tenant gets its own copy of the
 * standard roles and may change them without touching anyone else's.
 * Spatie resolves roles by name through static::query(), so the tenant
 * scope makes assignRole('Cashier') find this institution's Cashier.
 */
class Role extends SpatieRole
{
    use BelongsToOrganisation;
}
