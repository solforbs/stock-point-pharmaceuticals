<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'username' => 'testuser', 'password' => Hash::make('password')]
        );

        $this->call([
            OrganisationSeeder::class,
            ChartOfAccountsSeeder::class,
            DiscountAuthoritySeeder::class,
            PayrollBandsSeeder::class,
            TaxCodeSeeder::class,
            SupplierSeeder::class,
            UnitOfMeasureSeeder::class,
            MrlPricelistSeeder::class,
            MrlPricelistRemainderSeeder::class,
        ]);
    }
}
