<?php

namespace Database\Seeders;

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
        //  role                   line %   header %  may override floor
        'Cashier' => ['2.000', '0.000', false],
        'Senior Cashier' => ['5.000', '2.000', false],
        'Wholesale Rep' => ['8.000', '5.000', false],
        'Operations Manager' => ['15.000', '10.000', true],
        'Director' => ['100.000', '100.000', true],
    ];

    public function run(): void
    {
        foreach (self::AUTHORITY as $roleName => [$line, $header, $override]) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'branch_id' => null]);

            RoleDiscountAuthority::updateOrCreate(['role_id' => $role->id], [
                'max_line_discount_pct' => $line,
                'max_header_discount_pct' => $header,
                'may_override_floor' => $override,
            ]);
        }
    }
}
