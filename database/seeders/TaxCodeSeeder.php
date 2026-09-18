<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\TaxCode;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;

/**
 * Part 13 — the three Kenyan VAT treatments every product must eventually
 * carry: standard-rated, zero-rated and exempt. Products are seeded without
 * a tax code (see MrlPricelistSeeder) because which treatment applies is a
 * compliance decision per product; this seeder only makes the codes exist so
 * that decision can be recorded through the product form.
 *
 * [ASSUMPTION] 16% is the standard rate under the VAT Act 2013, in force
 * from its commencement on 2 September 2013 apart from the temporary 14%
 * relief of April–December 2020, which is not modelled here.
 */
class TaxCodeSeeder extends Seeder
{
    /**
     * @var list<array{0: string, 1: string, 2: string, 3: bool}> [code, name, rate_pct, is_recoverable]
     */
    public const CODES = [
        ['VAT_STD', 'VAT standard rate (16%)', '16.000', true],
        ['VAT_ZERO', 'VAT zero-rated', '0.000', true],
        ['VAT_EXEMPT', 'VAT exempt', '0.000', false],
    ];

    public const RATES_EFFECTIVE_FROM = '2013-09-02';

    public function run(): void
    {
        foreach (Organisation::all() as $organisation) {
            foreach (self::CODES as [$code, $name, $rate, $recoverable]) {
                $taxCode = TaxCode::firstOrCreate(
                    ['organisation_id' => $organisation->id, 'code' => $code],
                    ['name' => $name, 'tax_type' => 'VAT', 'is_recoverable' => $recoverable, 'is_active' => true],
                );

                TaxRate::firstOrCreate(
                    ['tax_code_id' => $taxCode->id, 'effective_from' => self::RATES_EFFECTIVE_FROM],
                    ['rate_pct' => $rate, 'effective_to' => null],
                );
            }
        }
    }
}
