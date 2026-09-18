<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use App\Models\Organisation;
use Illuminate\Database\Seeder;

/**
 * Part 21.16 — the statutory leave types every organisation starts with
 * (Employment Act 2007, Cap. 226).
 *
 * [ASSUMPTION] Annual 21 working days (s.28), maternity 90 days (s.29),
 * paternity 14 days (s.29(8)), compassionate 5 days (common practice; not
 * statutory). Sick leave under s.30 is 7 days full pay then 7 days half pay
 * after two months' service; it is modelled here as 14 paid days and the
 * half-pay split is left to the payroll officer — confirm with HR before
 * the first live leave year. Unpaid leave has no entitlement and never
 * blocks on balance.
 */
class LeaveTypesSeeder extends Seeder
{
    public const TYPES = [
        // code, name, days_per_year, is_paid, requires_document, carry_forward_max
        ['ANNUAL', 'Annual leave', '21', true, false, '10'],
        ['SICK', 'Sick leave', '14', true, true, '0'],
        ['MATERNITY', 'Maternity leave', '90', true, true, '0'],
        ['PATERNITY', 'Paternity leave', '14', true, false, '0'],
        ['COMPASSIONATE', 'Compassionate leave', '5', true, false, '0'],
        ['UNPAID', 'Unpaid leave', '0', false, false, '0'],
    ];

    public function run(): void
    {
        foreach (Organisation::pluck('id') as $organisationId) {
            self::seedFor((string) $organisationId);
        }
    }

    /**
     * Ensures one organisation has the default leave types; existing rows
     * (and any edits made to them) are left alone.
     */
    public static function seedFor(string $organisationId): void
    {
        foreach (self::TYPES as [$code, $name, $days, $paid, $document, $carry]) {
            LeaveType::firstOrCreate(
                ['organisation_id' => $organisationId, 'code' => $code],
                ['name' => $name, 'days_per_year' => $days, 'is_paid' => $paid, 'requires_document' => $document, 'carry_forward_max' => $carry, 'is_active' => true],
            );
        }
    }
}
