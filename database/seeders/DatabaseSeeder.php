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
        // Roles belong to an institution, so they are seeded after it (RoleSeeder below).
        $this->call([
            PermissionSeeder::class,
            PlanSeeder::class,
        ]);

        // A known-password account must never exist in production; there the
        // first administrator comes from `php artisan user:create-admin`.
        if (! app()->isProduction()) {
            User::firstOrCreate(
                ['email' => 'test@example.com'],
                ['name' => 'Test User', 'username' => 'testuser', 'password' => Hash::make('password')]
            );
        }

        $this->call([
            OrganisationSeeder::class,
            RoleSeeder::class,
            ChartOfAccountsSeeder::class,
            DiscountAuthoritySeeder::class,
            PayrollBandsSeeder::class,
            LeaveTypesSeeder::class,
            TaxCodeSeeder::class,
            SupplierSeeder::class,
            UnitOfMeasureSeeder::class,
            DosageFormSeeder::class,
            StorageConditionSeeder::class,
            MrlPricelistSeeder::class,
            MrlPricelistRemainderSeeder::class,
            ProductTaxDefaultSeeder::class,
        ]);

        // Demo customers, stock, sales, receipts and approvals for training and
        // local work. They post real ledger and journal rows, so production —
        // where the books must contain only what the business actually did —
        // never runs them; load them there deliberately with
        // `php artisan db:seed --class=KenyanPharmaMasterSeeder` if a demo
        // environment is wanted.
        if (! app()->isProduction()) {
            $this->call([
                KenyanPharmaMasterSeeder::class,
                KenyanPharmaStockSeeder::class,
                KenyanPharmaSalesSeeder::class,
                KenyanPharmaFinanceSeeder::class,
                KenyanPharmaApprovalsSeeder::class,
            ]);
        }
    }
}
