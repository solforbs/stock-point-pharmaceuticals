<?php

namespace App\Services\Payroll;

/**
 * Part 21.16 — the arithmetic, with every rate read from the band set it is
 * handed (never from code):
 *
 *   gross_pay   = basic + allowances + overtime
 *   taxable_pay = gross_pay − pension_contribution (capped) − allowable deductions (NSSF, SHIF, housing levy)
 *   paye        = Σ over bands (band_amount × band_rate) − personal_relief   (never below zero)
 *   net_pay     = gross_pay − paye − nssf − shif − housing_levy − other_deductions
 *
 * Money is held at 4dp and each statutory figure is rounded to the cent.
 */
class PayrollCalculator
{
    /**
     * @param  array{basic: string, allowances?: string, overtime?: string, pension_contribution?: string, other_deductions?: string}  $inputs
     * @param  list<array<string, mixed>>  $bands  as returned by PayrollBand::inForce()
     * @return array<string, mixed>
     */
    public function compute(array $inputs, array $bands): array
    {
        $basic = $this->m($inputs['basic']);
        $allowances = $this->m($inputs['allowances'] ?? '0');
        $overtime = $this->m($inputs['overtime'] ?? '0');
        $pension = $this->m($inputs['pension_contribution'] ?? '0');
        $other = $this->m($inputs['other_deductions'] ?? '0');
        $gross = bcadd(bcadd($basic, $allowances, 4), $overtime, 4);

        $byType = [];
        foreach ($bands as $b) {
            $byType[$b['band_type']][] = $b;
        }

        // NSSF: tiered, employee and employer pay the same.
        $nssf = '0.0000';
        $nssfDetail = [];
        foreach ($byType['NSSF'] ?? [] as $band) {
            $portion = $this->portionInBand($gross, $band);
            $contribution = $this->cents(bcdiv(bcmul($portion, $band['rate_pct'], 6), '100', 6));
            $nssf = bcadd($nssf, $contribution, 4);
            $nssfDetail[] = ['tier' => $band['sequence'], 'pensionable' => $portion, 'rate_pct' => $band['rate_pct'], 'contribution' => $contribution];
        }
        $nssfEmployer = $nssf;

        // SHIF: a flat rate of gross with a statutory minimum.
        $shif = '0.0000';
        foreach ($byType['SHIF'] ?? [] as $band) {
            $shif = $this->cents(bcdiv(bcmul($gross, $band['rate_pct'], 6), '100', 6));
            $min = (string) ($band['meta']['minimum'] ?? '0');
            if (bccomp($shif, $min, 4) < 0) {
                $shif = $this->m($min);
            }
        }

        // Housing levy: employee rate on gross; the employer matches at the meta rate.
        $housing = '0.0000';
        $housingEmployer = '0.0000';
        foreach ($byType['HOUSING_LEVY'] ?? [] as $band) {
            $housing = $this->cents(bcdiv(bcmul($gross, $band['rate_pct'], 6), '100', 6));
            $housingEmployer = $this->cents(bcdiv(bcmul($gross, (string) ($band['meta']['employer_rate_pct'] ?? $band['rate_pct']), 6), '100', 6));
        }

        // Pension relief cap, then the taxable figure.
        $pensionCap = null;
        foreach ($byType['PENSION_RELIEF_CAP'] ?? [] as $band) {
            $pensionCap = (string) $band['fixed_amount'];
        }
        $pensionRelief = $pensionCap !== null && bccomp($pension, $pensionCap, 4) > 0 ? $this->m($pensionCap) : $pension;
        $taxable = bcsub(bcsub(bcsub(bcsub($gross, $pensionRelief, 4), $nssf, 4), $shif, 4), $housing, 4);
        if (bccomp($taxable, '0', 4) < 0) {
            $taxable = '0.0000';
        }

        // PAYE across the bands, less personal relief.
        $grossTax = '0.0000';
        $payeDetail = [];
        foreach ($byType['PAYE'] ?? [] as $band) {
            $portion = $this->portionInBand($taxable, $band);
            if (bccomp($portion, '0', 4) <= 0) {
                continue;
            }
            $tax = bcdiv(bcmul($portion, $band['rate_pct'], 6), '100', 6);
            $grossTax = bcadd($grossTax, $tax, 6);
            $payeDetail[] = ['band' => $band['sequence'], 'lower' => $band['lower'], 'upper' => $band['upper'], 'rate_pct' => $band['rate_pct'], 'amount_in_band' => $portion, 'tax' => $this->cents($tax)];
        }
        $relief = '0.0000';
        foreach ($byType['PAYE_RELIEF'] ?? [] as $band) {
            $relief = bcadd($relief, (string) $band['fixed_amount'], 4);
        }
        $paye = bcsub($this->cents($grossTax), $relief, 4);
        if (bccomp($paye, '0', 4) < 0) {
            $paye = '0.0000';
        }

        $net = bcsub(bcsub(bcsub(bcsub(bcsub($gross, $paye, 4), $nssf, 4), $shif, 4), $housing, 4), $other, 4);

        return [
            'basic' => $basic, 'allowances' => $allowances, 'overtime' => $overtime, 'gross' => $gross,
            'pension_contribution' => $pension, 'pension_relief' => $pensionRelief, 'taxable' => $taxable,
            'paye' => $paye, 'nssf_employee' => $nssf, 'nssf_employer' => $nssfEmployer, 'shif' => $shif,
            'housing_levy_employee' => $housing, 'housing_levy_employer' => $housingEmployer, 'other_deductions' => $other, 'net' => $net,
            'employer_cost' => bcadd(bcadd($gross, $nssfEmployer, 4), $housingEmployer, 4),
            'breakdown' => ['gross_tax_before_relief' => $this->cents($grossTax), 'personal_relief' => $relief, 'paye_bands' => $payeDetail, 'nssf_tiers' => $nssfDetail],
        ];
    }

    /**
     * @param  array<string, mixed>  $band
     */
    private function portionInBand(string $amount, array $band): string
    {
        $lower = (string) $band['lower'];
        $upper = $band['upper'] !== null ? (string) $band['upper'] : null;
        if (bccomp($amount, $lower, 4) <= 0) {
            return '0.0000';
        }
        $top = $upper !== null && bccomp($amount, $upper, 4) > 0 ? $upper : $amount;

        return bcsub($top, $lower, 4);
    }

    private function m(string|int|float $v): string
    {
        return bcadd((string) $v, '0', 4);
    }

    /** Statutory figures are settled to the cent, half up. */
    private function cents(string $v): string
    {
        return number_format(round((float) $v, 2), 4, '.', '');
    }
}
