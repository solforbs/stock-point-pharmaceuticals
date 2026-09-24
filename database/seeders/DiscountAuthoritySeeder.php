<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\RoleDiscountAuthority;
use Illuminate\Database\Seeder;

class DiscountAuthoritySeeder extends Seeder
{
    /**
     * Part 4.5 — the discount authority matrix. A business policy decision
     * for the owner (Part 32 step 3); these are the blueprint's starting
     * values. Roles not listed may not discount at all.
     */
    public const AUTHORITY = [
        // Governance 2026-09-24: the standard role set is small, so only
        // the two selling roles carry discount authority. The Director (the
        // institution owner) may discount freely; the Pharmacist within a
        // modest band. Custom roles can be granted authority in the app.
        //  role            line %     header %   may override floor
        'Pharmacist' => ['5.000', '2.000', false],
        'Director' => ['100.000', '100.000', true],
    ];

    public function run(): void
    {
        foreach (Organisation::pluck('id') as $organisationId) {
            self::provision((string) $organisationId);
        }
    }

    /** One institution's starting matrix, on its own roles. */
    public static function provision(string $organisationId): void
    {
        foreach (self::AUTHORITY as $roleName => [$line, $header, $override]) {
            $role = Role::withoutGlobalScopes()->firstOrCreate(['organisation_id' => $organisationId, 'name' => $roleName, 'guard_name' => 'web', 'branch_id' => null]);

            RoleDiscountAuthority::updateOrCreate(['role_id' => $role->id], [
                'max_line_discount_pct' => $line,
                'max_header_discount_pct' => $header,
                'may_override_floor' => $override,
            ]);
        }
    }
}
