<?php

namespace Database\Seeders;

use App\Models\DosageForm;
use Illuminate\Database\Seeder;

/**
 * Global reference dosage forms (Part 5.2). These are the forms that appear
 * on the Medina catalogue; new ones are added as real products need them.
 */
class DosageFormSeeder extends Seeder
{
    /** @var list<array{string, string}> */
    public const FORMS = [
        ['TAB', 'Tablet'],
        ['CAP', 'Capsule'],
        ['SYR', 'Syrup'],
        ['SUS', 'Suspension'],
        ['SOL', 'Solution'],
        ['INJ', 'Injection'],
        ['INF', 'Infusion'],
        ['CRM', 'Cream'],
        ['OINT', 'Ointment'],
        ['GEL', 'Gel'],
        ['LOT', 'Lotion'],
        ['DROP', 'Drops'],
        ['SPRAY', 'Spray'],
        ['INH', 'Inhaler'],
        ['SUPP', 'Suppository'],
        ['PESS', 'Pessary'],
        ['PWD', 'Powder'],
        ['SACH', 'Sachet'],
        ['PATCH', 'Transdermal patch'],
        ['DEV', 'Device or consumable'],
    ];

    public function run(): void
    {
        foreach (self::FORMS as [$code, $name]) {
            DosageForm::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
