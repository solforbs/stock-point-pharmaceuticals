<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\StorageCondition;
use App\Models\Store;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Part 8.5 — the storage conditions cold-chain monitoring is measured
 * against. Without these no store can be told what temperature it must
 * hold, so no excursion can ever be detected.
 */
class StorageConditionSeeder extends Seeder
{
    /** @var list<array{code: string, name: string, min: ?string, max: ?string, excursion: ?int, cold: bool}> */
    public const CONDITIONS = [
        ['code' => 'AMBIENT', 'name' => 'Ambient (15 °C – 30 °C)', 'min' => '15.00', 'max' => '30.00', 'excursion' => null, 'cold' => false],
        ['code' => 'ROOM', 'name' => 'Controlled Room Temperature (20 °C – 25 °C)', 'min' => '20.00', 'max' => '25.00', 'excursion' => null, 'cold' => false],
        ['code' => 'COOL', 'name' => 'Cool (8–15 °C)', 'min' => '8.00', 'max' => '15.00', 'excursion' => 120, 'cold' => false],
        ['code' => 'COLD', 'name' => 'Cold chain (2–8 °C)', 'min' => '2.00', 'max' => '8.00', 'excursion' => 30, 'cold' => true],
        ['code' => 'FROZEN', 'name' => 'Frozen (−25 to −15 °C)', 'min' => '-25.00', 'max' => '-15.00', 'excursion' => 15, 'cold' => true],
    ];

    public function run(): void
    {
        $org = Organisation::first();
        if (! $org) {
            throw new RuntimeException('Organisation not found. Run OrganisationSeeder first.');
        }

        $conditions = [];
        foreach (self::CONDITIONS as $condition) {
            $conditions[$condition['code']] = StorageCondition::firstOrCreate(
                ['code' => $condition['code']],
                [
                    'organisation_id' => $org->id,
                    'name' => $condition['name'],
                    'min_temp_c' => $condition['min'],
                    'max_temp_c' => $condition['max'],
                    'max_excursion_minutes' => $condition['excursion'],
                    'requires_cold_chain' => $condition['cold'],
                ]
            );
        }

        // A cold room that is not told its range cannot raise an excursion.
        // Only stores that have never been configured are touched.
        foreach (['COLD' => 'COLD', 'MAIN' => 'AMBIENT', 'RETAIL' => 'AMBIENT', 'QTN' => 'AMBIENT'] as $storeCode => $conditionCode) {
            Store::where('code', $storeCode)->whereNull('storage_condition_id')
                ->update(['storage_condition_id' => $conditions[$conditionCode]->id]);
        }
    }
}
