<?php

namespace Database\Seeders;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

/**
 * Global reference units of measure (Part 5.3/5.4). Kept deliberately small —
 * new codes are added as real products need them, not speculatively.
 */
class UnitOfMeasureSeeder extends Seeder
{
    public const UOMS = [
        ['EA', 'Each', true],
        ['TAB', 'Tablet', true],
        ['CAP', 'Capsule', true],
        ['ML', 'Millilitre', true],
        ['GM', 'Gram', true],
    ];

    public function run(): void
    {
        foreach (self::UOMS as [$code, $name, $isBaseCandidate]) {
            UnitOfMeasure::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_base_candidate' => $isBaseCandidate]
            );
        }
    }
}
