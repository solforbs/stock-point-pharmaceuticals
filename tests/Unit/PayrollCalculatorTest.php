<?php

namespace Tests\Unit;

use App\Services\Payroll\PayrollCalculator;
use Database\Seeders\PayrollBandsSeeder;
use PHPUnit\Framework\TestCase;

/**
 * Part 21.16 — the worked example. Every rate comes from the band set, so
 * the calculator is exercised here with the seeded 2025 bands turned into
 * plain arrays; no database needed.
 */
class PayrollCalculatorTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function bands(): array
    {
        return array_map(fn ($b) => [
            'band_type' => $b[0], 'sequence' => $b[1], 'effective_from' => $b[2], 'effective_to' => null,
            'lower' => $b[3], 'upper' => $b[4], 'rate_pct' => $b[5], 'fixed_amount' => $b[6], 'meta' => $b[7] ?? [], 'source' => $b[8],
        ], PayrollBandsSeeder::BANDS);
    }

    public function test_a_50000_basic_salary_in_2025(): void
    {
        $f = (new PayrollCalculator)->compute(['basic' => '50000'], $this->bands());

        $this->assertSame('50000.0000', $f['gross']);
        $this->assertSame('3000.0000', $f['nssf_employee'], 'Tier I 6% × 8,000 = 480 + Tier II 6% × 42,000 = 2,520');
        $this->assertSame('3000.0000', $f['nssf_employer']);
        $this->assertSame('1375.0000', $f['shif'], '2.75% of gross');
        $this->assertSame('750.0000', $f['housing_levy_employee'], '1.5% of gross');
        $this->assertSame('750.0000', $f['housing_levy_employer']);
        $this->assertSame('44875.0000', $f['taxable'], 'gross less NSSF, SHIF and housing levy');
        $this->assertSame('8245.8500', $f['breakdown']['gross_tax_before_relief'], '2,400 + 2,083.25 + 3,762.60');
        $this->assertSame('5845.8500', $f['paye'], 'less personal relief 2,400');
        $this->assertSame('39029.1500', $f['net'], '50,000 − 5,845.85 − 3,000 − 1,375 − 750');
        $this->assertSame('53750.0000', $f['employer_cost']);
    }

    public function test_a_low_salary_pays_the_shif_floor_and_no_paye(): void
    {
        $f = (new PayrollCalculator)->compute(['basic' => '10000'], $this->bands());

        $this->assertSame('300.0000', $f['shif'], 'the KES 300 minimum applies below 10,909');
        $this->assertSame('600.0000', $f['nssf_employee'], '6% × 8,000 + 6% × 2,000');
        $this->assertSame('0.0000', $f['paye'], 'the relief exceeds the tax due; PAYE never goes negative');
    }

    public function test_nssf_is_capped_at_the_upper_earnings_limit_and_pension_relief_is_capped(): void
    {
        $f = (new PayrollCalculator)->compute(['basic' => '400000', 'allowances' => '100000', 'pension_contribution' => '50000', 'other_deductions' => '2500'], $this->bands());

        $this->assertSame('500000.0000', $f['gross']);
        $this->assertSame('4320.0000', $f['nssf_employee'], '6% of the 72,000 upper limit');
        $this->assertSame('30000.0000', $f['pension_relief'], 'capped at 30,000 even though 50,000 was contributed');
        $this->assertSame('2500.0000', $f['other_deductions']);
        $this->assertSame(bcsub(bcsub(bcsub(bcsub(bcsub('500000.0000', $f['paye'], 4), '4320.0000', 4), $f['shif'], 4), '7500.0000', 4), '2500.0000', 4), $f['net']);
    }

    public function test_a_rate_change_is_a_new_band_row_not_an_edit(): void
    {
        $bands = $this->bands();
        $bands[] = ['band_type' => 'SHIF', 'sequence' => 1, 'effective_from' => '2027-01-01', 'effective_to' => null, 'lower' => '0', 'upper' => null, 'rate_pct' => '3', 'fixed_amount' => null, 'meta' => ['minimum' => '300'], 'source' => 'hypothetical'];
        // The calculator only sees what PayrollBand::inForce() hands it: the
        // old rate for an old run, the new rate for a new one. Feed it each.
        $old = array_values(array_filter($bands, fn ($b) => $b['effective_from'] < '2027-01-01'));
        $new = array_values(array_filter($bands, fn ($b) => ! ($b['band_type'] === 'SHIF' && $b['effective_from'] < '2027-01-01')));

        $this->assertSame('1375.0000', (new PayrollCalculator)->compute(['basic' => '50000'], $old)['shif']);
        $this->assertSame('1500.0000', (new PayrollCalculator)->compute(['basic' => '50000'], $new)['shif']);
    }
}
