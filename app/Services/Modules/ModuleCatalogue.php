<?php

namespace App\Services\Modules;

use App\Models\User;
use Database\Seeders\PermissionSeeder;

/**
 * Which platform modules (Hospital, Laboratory, Pharmacy) a user may enter.
 * Access is permission-driven, never role-name-driven: a module is open to a
 * user when they hold at least one permission from its catalogue. The
 * catalogues live beside the permission definitions in PermissionSeeder so
 * a new permission is automatically counted for its module.
 *
 * The web app uses the resulting list for the post-login flow:
 * 0 modules → access denied, 1 module → straight to that module's
 * dashboard, 2+ → the module selection splash.
 */
class ModuleCatalogue
{
    public const HOSPITAL = 'HOSPITAL';

    public const LABORATORY = 'LABORATORY';

    public const PHARMACY = 'PHARMACY';

    public const MODULES = [self::HOSPITAL, self::LABORATORY, self::PHARMACY];

    /** @return list<string> */
    public static function permissionsFor(string $module): array
    {
        return match (strtoupper($module)) {
            self::HOSPITAL => PermissionSeeder::HOSPITAL_PERMISSIONS,
            self::LABORATORY => PermissionSeeder::LABORATORY_PERMISSIONS,
            self::PHARMACY => PermissionSeeder::PHARMACY_PERMISSIONS,
            default => throw new \InvalidArgumentException("Unknown module [{$module}]."),
        };
    }

    /**
     * The modules this user may enter, in display order.
     *
     * @return list<string>
     */
    public static function accessibleTo(User $user): array
    {
        $held = $user->getAllPermissions()->pluck('name')->flip();

        return array_values(array_filter(
            self::MODULES,
            fn (string $module) => collect(self::permissionsFor($module))->contains(fn ($p) => $held->has($p)),
        ));
    }

    public static function userHasModule(User $user, string $module): bool
    {
        return in_array(strtoupper($module), self::accessibleTo($user), true);
    }
}
