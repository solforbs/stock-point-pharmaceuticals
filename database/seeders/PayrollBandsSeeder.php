<?php

namespace Database\Seeders;

use App\Models\PayrollBand;
use Illuminate\Database\Seeder;

/**
 * Part 21.16 — statutory deductions as effective-dated bands, never code.
 *
 * [ASSUMPTION] These are the Kenyan monthly rates in force from 2025
 * (Finance Act 2023 PAYE bands, NSSF Act 2013 year-3 limits from
 * February 2025, SHIF 2.75% with a KES 300 floor, Affordable Housing Levy
 * 1.5% + 1.5%). Confirm against the current KRA/NSSF/SHA notices before
 * the first live payroll; a change is a new row with a new effective_from,
 * never an edit.
 */
class PayrollBandsSeeder extends Seeder
{
    public const BANDS = [
        // band_type, sequence, effective_from, lower, upper, rate_pct, fixed_amount, meta, source
        ['PAYE', 1, '2023-07-01', '0', '24000', '10', null, null, 'Finance Act 2023 s.5 (monthly)'],
        ['PAYE', 2, '2023-07-01', '24000', '32333', '25', null, null, 'Finance Act 2023 s.5 (monthly)'],
        ['PAYE', 3, '2023-07-01', '32333', '500000', '30', null, null, 'Finance Act 2023 s.5 (monthly)'],
        ['PAYE', 4, '2023-07-01', '500000', '800000', '32.5', null, null, 'Finance Act 2023 s.5 (monthly)'],
        ['PAYE', 5, '2023-07-01', '800000', null, '35', null, null, 'Finance Act 2023 s.5 (monthly)'],
        ['PAYE_RELIEF', 1, '2018-01-01', '0', null, '0', '2400', null, 'Income Tax Act Third Schedule — personal relief KES 2,400/month'],
        ['NSSF', 1, '2025-02-01', '0', '8000', '6', null, ['tier' => 'I'], 'NSSF Act 2013 — Tier I, LEL 8,000 (Feb 2025)'],
        ['NSSF', 2, '2025-02-01', '8000', '72000', '6', null, ['tier' => 'II'], 'NSSF Act 2013 — Tier II, UEL 72,000 (Feb 2025)'],
        ['SHIF', 1, '2024-10-01', '0', null, '2.75', null, ['minimum' => '300'], 'Social Health Insurance Act 2023 — 2.75% of gross, min KES 300'],
        ['HOUSING_LEVY', 1, '2024-03-19', '0', null, '1.5', null, ['employer_rate_pct' => '1.5'], 'Affordable Housing Act 2024 — 1.5% employee + 1.5% employer'],
        ['PENSION_RELIEF_CAP', 1, '2025-01-01', '0', null, '0', '30000', null, 'Tax Laws (Amendment) Act 2024 — pension deduction cap KES 30,000/month'],
    ];

    public function run(): void
    {
        foreach (self::BANDS as [$type, $seq, $from, $lower, $upper, $rate, $fixed, $meta, $source]) {
            PayrollBand::firstOrCreate(
                ['organisation_id' => null, 'band_type' => $type, 'sequence' => $seq, 'effective_from' => $from],
                ['lower' => $lower, 'upper' => $upper, 'rate_pct' => $rate, 'fixed_amount' => $fixed, 'meta_json' => $meta, 'source' => $source],
            );
        }
    }
}
