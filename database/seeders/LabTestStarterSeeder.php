<?php

namespace Database\Seeders;

use App\Models\LabTest;
use App\Models\LabTestCategory;
use App\Models\Organisation;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * A starter orderable test catalogue for demo/local work, so the lab is
 * usable out of the box. Real institutions maintain their own catalogue
 * (Laboratory → Test Catalogue); prices here are demo figures. Not run in
 * production — load deliberately with `db:seed --class=LabTestStarterSeeder`.
 */
class LabTestStarterSeeder extends Seeder
{
    /** @var list<array{string, string, string, string, string, ?string, ?string}> [category, code, name, price, sample, normal range, unit] */
    public const TESTS = [
        ['Hematology', 'CBC', 'Complete Blood Count', '800', 'Blood', null, null],
        ['Hematology', 'HB', 'Hemoglobin', '300', 'Blood', '12 - 17.5', 'g/dL'],
        ['Parasitology', 'MPS', 'Malaria Parasite Slide', '300', 'Blood', 'Negative', null],
        ['Parasitology', 'MRDT', 'Malaria Rapid Test', '350', 'Blood', 'Negative', null],
        ['Biochemistry', 'RBS', 'Random Blood Sugar', '250', 'Blood', '3.9 - 7.8', 'mmol/L'],
        ['Biochemistry', 'FBS', 'Fasting Blood Sugar', '250', 'Blood', '3.9 - 5.6', 'mmol/L'],
        ['Biochemistry', 'LFT', 'Liver Function Tests', '1500', 'Blood', null, null],
        ['Biochemistry', 'UECS', 'Urea, Electrolytes & Creatinine', '1500', 'Blood', null, null],
        ['Biochemistry', 'LIPID', 'Lipid Profile', '1800', 'Blood', null, null],
        ['Serology', 'HIV', 'HIV Screening', '0', 'Blood', 'Non-reactive', null],
        ['Serology', 'HPYL', 'H. Pylori Antigen', '900', 'Stool', 'Negative', null],
        ['Serology', 'VDRL', 'VDRL (Syphilis)', '500', 'Blood', 'Non-reactive', null],
        ['Microbiology', 'URINE-CS', 'Urine Culture & Sensitivity', '1200', 'Urine', 'No growth', null],
        ['Microbiology', 'STOOL-ME', 'Stool Microscopy', '400', 'Stool', 'No ova or cysts seen', null],
        ['Urinalysis', 'URINALYSIS', 'Urinalysis', '300', 'Urine', null, null],
        ['Hematology', 'PDT', 'Pregnancy Test (hCG)', '300', 'Urine', 'Negative', null],
    ];

    public function run(): void
    {
        // Shared categories first, OUTSIDE any tenant context — inside one,
        // the tenant trait stamps a "shared" insert with the organisation id
        // and the second test in that category collides on the unique key.
        (new LabTestCategorySeeder)->run();

        foreach (Organisation::pluck('id') as $organisationId) {
            app(TenantContext::class)->run((string) $organisationId, function () {
                foreach (self::TESTS as [$category, $code, $name, $price, $sample, $range, $unit]) {
                    // Prefer the shared row; tolerate an organisation-owned
                    // copy left behind by an earlier partial run.
                    $categoryId = LabTestCategory::where('name', $category)
                        ->orderByRaw('organisation_id is not null')->value('id');

                    LabTest::firstOrCreate(['code' => $code], [
                        'category_id' => $categoryId,
                        'name' => $name,
                        'price' => $price,
                        'sample_type' => $sample,
                        'normal_range' => $range,
                        'unit' => $unit,
                        'is_active' => true,
                    ]);
                }
            });
        }
    }
}
