<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * Part 9.6 — the supplier whose catalogue the product master was imported
 * from (MrlPricelistSeeder), so a purchase order can be raised against it
 * on day one. Contact, licence and bank details are not on the pricelist
 * and are left for the procurement officer to complete.
 */
class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Organisation::all() as $organisation) {
            Supplier::firstOrCreate(
                ['organisation_id' => $organisation->id, 'code' => 'MRL'],
                ['name' => 'Medina Remedies Limited', 'currency' => 'KES', 'status' => 'ACTIVE', 'is_active' => true],
            );
        }
    }
}
