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
        $org = Organisation::firstOrCreate(
            ['kra_pin' => 'P000000000X'],
            [
                'name' => 'Stockpoint Pharma Wholesalers Ltd',
                'legal_name' => 'Stockpoint Pharma Wholesalers Ltd',
                'vat_registered' => true,
                'base_currency' => 'KES',
                'fiscal_year_start' => 1,
            ]
        );

        $branch = Branch::firstOrCreate(
            ['code' => 'LDW'],
            [
                'organisation_id' => $org->id,
                'name' => 'Lodwar Main Branch',
                'county' => 'Turkana',
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
        $testUser = User::where('email', 'test@example.com')->first();
        if ($testUser) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
            $testUser->assignRole('Director', 'System Administrator');
        }
    }
}
