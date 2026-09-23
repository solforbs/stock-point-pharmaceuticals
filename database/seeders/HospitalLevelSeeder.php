<?php

namespace Database\Seeders;

use App\Models\HospitalLevel;
use Illuminate\Database\Seeder;

/**
 * Shared hospital classifications (Kenyan KEPH levels plus Referral). These
 * are configurable reference data, not code: a tenant can add its own rows
 * and a platform administrator can revise this list as classifications change.
 */
class HospitalLevelSeeder extends Seeder
{
    /** @var list<array{string, int}> */
    public const LEVELS = [
        ['Level 1', 1],
        ['Level 2', 2],
        ['Level 3', 3],
        ['Level 4', 4],
        ['Level 5', 5],
        ['Level 6', 6],
        ['Referral Hospital', 7],
    ];

    public function run(): void
    {
        foreach (self::LEVELS as [$name, $rank]) {
            HospitalLevel::firstOrCreate(['name' => $name, 'organisation_id' => null], ['rank' => $rank]);
        }
    }
}
