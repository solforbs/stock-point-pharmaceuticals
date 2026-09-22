<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permissions are one platform-wide catalogue. Spatie caches every
 * permission with the roles that hold it and checks a user's roles against
 * that list, so the list must span every institution: were it loaded under
 * one tenant's scope, every other tenant would be denied everything.
 */
class Permission extends SpatiePermission
{
    public function roles(): BelongsToMany
    {
        return parent::roles()->withoutGlobalScope('tenant');
    }
}
