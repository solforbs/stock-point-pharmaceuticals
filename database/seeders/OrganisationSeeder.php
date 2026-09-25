<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Organisation;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        $settings = config('app.organisation');

        $org = Organisation::firstOrCreate(
            ['kra_pin' => $settings['kra_pin']],
            [
                'name' => $settings['name'],
                'legal_name' => $settings['name'],
                'vat_registered' => true,
                'base_currency' => 'KES',
                'fiscal_year_start' => 1,
            ]
        );
        // The platform owner's own institution is never billed.
        $org->forceFill(['is_complimentary' => true, 'subscription_status' => 'ACTIVE'])->save();

        $branch = Branch::firstOrCreate(
            ['code' => $settings['branch_code']],
            [
                'organisation_id' => $org->id,
                'name' => $settings['branch_name'],
                'county' => $settings['county'],
                'is_active' => true,
                // Stockpoint is a wholesaler with a retail counter — both on.
                'retail_enabled' => true,
                'wholesale_enabled' => true,
                'dispensing_enabled' => false,
            ]
        );

        // Store concepts preserved from the v5 prototype (Part 0.1).
        $stores = [
            ['code' => 'MAIN', 'name' => 'Main Warehouse', 'store_type' => 'MAIN', 'is_sellable' => false],
            ['code' => 'COLD', 'name' => 'Cold Room', 'store_type' => 'COLD', 'is_sellable' => false],
            ['code' => 'QTN', 'name' => 'Quarantine', 'store_type' => 'QUARANTINE', 'is_sellable' => false],
            ['code' => 'RETAIL', 'name' => 'Retail Counter', 'store_type' => 'RETAIL', 'is_sellable' => true],
        ];

        foreach ($stores as $store) {
            Store::firstOrCreate(
                ['branch_id' => $branch->id, 'code' => $store['code']],
                $store + ['branch_id' => $branch->id]
            );
        }

        // Give the seeded test user full access to this branch for local dev.
        // Director is the owner role and carries every institution permission
        // (the old System Administrator role is retired — see RoleSeeder).
        $testUser = User::where('email', 'test@example.com')->first();
        if ($testUser) {
            RoleSeeder::provision($org->id);
            $testUser->forceFill(['organisation_id' => $org->id])->save();
            app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
            $testUser->assignRole('Director');
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        }
    }
}
