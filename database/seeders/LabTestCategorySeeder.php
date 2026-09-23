<?php

namespace Database\Seeders;

use App\Models\LabTestCategory;
use Illuminate\Database\Seeder;

/**
 * Shared starting laboratory disciplines. Tenants add their own beside
 * these; none of this is hard-coded in the workflow.
 */
class LabTestCategorySeeder extends Seeder
{
    /** @var list<string> */
    public const CATEGORIES = [
        'Hematology',
        'Biochemistry',
        'Microbiology',
        'Parasitology',
        'Serology',
        'Immunology',
        'Urinalysis',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            LabTestCategory::firstOrCreate(['name' => $name, 'organisation_id' => null]);
        }
    }
}
