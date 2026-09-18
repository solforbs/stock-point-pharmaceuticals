<?php

namespace App\Services\Pricing;

/**
 * Part 4.8 rounding rules, fixed and documented: half-up, applied last,
 * never floating point. All arithmetic is bcmath on decimal strings.
 */
final class Money
{
    /**
     * Round half-up to $scale decimal places. The result is always returned
     * as a 4dp string, because every money column is DECIMAL(18,4) and the
     * rest of the system compares amounts at that scale.
     */
    public static function round(string $amount, int $scale = 2): string
    {
        $negative = bccomp($amount, '0', 10) < 0;
        $abs = $negative ? bcmul($amount, '-1', 10) : $amount;
        $rounded = bcadd($abs, '0.'.str_repeat('0', $scale).'5', $scale);
        $rounded = $negative ? bcmul($rounded, '-1', $scale) : $rounded;

        return bcadd($rounded, '0', 4);
    }

    /** Round half-up to the nearest $step (e.g. 0.50), as a 4dp string. */
    public static function roundToStep(string $amount, string $step): string
    {
        if (bccomp($step, '0', 4) <= 0) {
            return self::round($amount, 4);
        }

        $units = bcdiv($amount, $step, 10);
        $roundedUnits = self::round($units, 0);

        return bcmul($roundedUnits, $step, 4);
    }

    /** The smallest multiple of $step that is >= $amount. */
    public static function ceilToStep(string $amount, string $step): string
    {
        if (bccomp($step, '0', 4) <= 0) {
            return self::round($amount, 4);
        }

        $units = bcdiv($amount, $step, 10);
        $floorUnits = bcdiv($units, '1', 0);
        if (bccomp(bcmul($floorUnits, $step, 10), $amount, 10) < 0) {
            $floorUnits = bcadd($floorUnits, '1', 0);
        }

        return bcmul($floorUnits, $step, 4);
    }

    public static function pct(string $numerator, string $denominator): string
    {
        if (bccomp($denominator, '0', 4) === 0) {
            return '0.0000';
        }

        return self::round(bcmul(bcdiv($numerator, $denominator, 10), '100', 8), 4);
    }

    public static function max(string $a, string $b): string
    {
        return bccomp($a, $b, 4) >= 0 ? $a : $b;
    }

    public static function min(string $a, string $b): string
    {
        return bccomp($a, $b, 4) <= 0 ? $a : $b;
    }
}
