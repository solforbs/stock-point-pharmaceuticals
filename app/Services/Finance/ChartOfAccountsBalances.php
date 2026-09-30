<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Point-in-time balances read straight from posted journal lines — what the
 * ledger holds, not what a screen remembers.
 */
class ChartOfAccountsBalances
{
    /** Cash, bank, M-PESA and petty cash together, organisation-wide, up to and including a date. */
    public static function cashOnHand(string $organisationId, string $asOf): string
    {
        return self::net($organisationId, ['CASH', 'BANK', 'MPESA_CLEARING', 'PETTY_CASH'], $asOf);
    }

    /** The petty cash float at one branch up to and including a date. */
    public static function pettyCash(string $organisationId, string $branchId, string $asOf): string
    {
        return self::net($organisationId, ['PETTY_CASH'], $asOf, $branchId);
    }

    /**
     * @param  list<string>  $roles
     */
    private static function net(string $organisationId, array $roles, string $asOf, ?string $branchId = null): string
    {
        $net = DB::table('journal_entry_lines as l')
            ->join('journal_entries as j', 'j.id', '=', 'l.journal_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('a.organisation_id', $organisationId)->whereIn('a.system_role', $roles)
            ->whereDate('j.entry_date', '<=', $asOf)
            ->when($branchId, fn ($q, $v) => $q->where('j.branch_id', $v))
            ->selectRaw('COALESCE(SUM(l.debit_amount - l.credit_amount), 0) as net')->value('net');

        return number_format((float) $net, 4, '.', '');
    }
}
