<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\FinancialPeriod;
use App\Models\Organisation;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    // Part 12.2 — code, name, type, system_role (the posting engine finds
    // accounts by role, never by hardcoded number).
    public const ACCOUNTS = [
        ['1010', 'Cash in till', 'ASSET', 'CASH'],
        ['1020', 'Bank', 'ASSET', 'BANK'],
        ['1030', 'M-PESA clearing', 'ASSET', 'MPESA_CLEARING'],
        ['1100', 'Accounts receivable', 'ASSET', 'AR_CONTROL'],
        ['1200', 'Inventory', 'ASSET', 'INVENTORY'],
        ['1210', 'Inventory in transit', 'ASSET', 'INVENTORY_IN_TRANSIT'],
        ['1220', 'Inventory quarantined', 'ASSET', 'INVENTORY_QUARANTINED'],
        ['2010', 'Accounts payable', 'LIABILITY', 'AP_CONTROL'],
        ['2100', 'VAT payable', 'LIABILITY', 'VAT_OUTPUT'],
        ['2110', 'VAT input', 'ASSET', 'VAT_INPUT'],
        ['2200', 'PAYE payable', 'LIABILITY', 'PAYE_PAYABLE'],
        ['2210', 'NSSF payable', 'LIABILITY', 'NSSF_PAYABLE'],
        ['2220', 'SHIF payable', 'LIABILITY', 'SHIF_PAYABLE'],
        ['2230', 'Housing levy payable', 'LIABILITY', 'HOUSING_LEVY_PAYABLE'],
        ['2240', 'Net salaries payable', 'LIABILITY', 'NET_PAY_PAYABLE'],
        ['2250', 'Other payroll deductions payable', 'LIABILITY', 'OTHER_DEDUCTIONS_PAYABLE'],
        ['2310', 'GRN accrual', 'LIABILITY', 'GRN_ACCRUAL'],
        ['3010', 'Share capital', 'EQUITY', null],
        ['3100', 'Retained earnings', 'EQUITY', null],
        ['3200', 'Opening balance equity', 'EQUITY', 'OPENING_BALANCE_EQUITY'],
        ['4010', 'Sales - retail', 'REVENUE', 'SALES_RETAIL'],
        ['4020', 'Sales - wholesale', 'REVENUE', 'SALES_WHOLESALE'],
        ['4030', 'Sales - dispensing', 'REVENUE', 'SALES_DISPENSING'],
        ['4100', 'Sales discounts', 'REVENUE', 'SALES_DISCOUNTS'],
        ['4110', 'Sales returns', 'REVENUE', 'SALES_RETURNS'],
        ['5010', 'Cost of goods sold', 'EXPENSE', 'COGS'],
        ['5100', 'Stock write-off - expiry', 'EXPENSE', 'STOCK_WRITEOFF_EXPIRY'],
        ['5110', 'Stock write-off - damage', 'EXPENSE', 'STOCK_WRITEOFF_DAMAGE'],
        ['5120', 'Stock variance', 'EXPENSE', 'STOCK_VARIANCE'],
        ['5130', 'Bonus goods cost', 'EXPENSE', 'BONUS_GOODS_COST'],
        ['6010', 'Salaries and wages', 'EXPENSE', 'SALARIES_EXPENSE'],
    ];

    public function run(): void
    {
        $org = Organisation::firstOrFail();

        foreach (self::ACCOUNTS as [$code, $name, $type, $role]) {
            ChartOfAccount::firstOrCreate(
                ['organisation_id' => $org->id, 'code' => $code],
                ['name' => $name, 'account_type' => $type, 'system_role' => $role, 'is_postable' => true]
            );
        }

        $year = (int) now()->format('Y');
        FinancialPeriod::firstOrCreate(
            ['organisation_id' => $org->id, 'fiscal_year' => $year, 'period_no' => now()->month],
            [
                'start_date' => now()->startOfMonth(),
                'end_date' => now()->endOfMonth(),
                'status' => 'OPEN',
            ]
        );
    }
}
