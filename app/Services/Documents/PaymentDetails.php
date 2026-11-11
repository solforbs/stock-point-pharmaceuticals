<?php

namespace App\Services\Documents;

use App\Models\BankAccount;
use App\Models\Organisation;

/**
 * How customers pay, for the foot of a price list: M-PESA from the company
 * profile, then the active bank accounts.
 */
class PaymentDetails
{
    /**
     * @return array{mpesa_paybill: ?string, mpesa_account: ?string, mpesa_till: ?string, note: ?string, account_name: string, banks: list<array{bank_name: ?string, account_name: ?string, account_number: ?string, branch_name: ?string}>}
     */
    public function for(string $organisationId): array
    {
        $organisation = Organisation::find($organisationId);

        return [
            'mpesa_paybill' => $organisation?->mpesa_paybill,
            'mpesa_account' => $organisation?->mpesa_account,
            'mpesa_till' => $organisation?->mpesa_till,
            'note' => $organisation?->payment_note,
            'account_name' => $organisation?->legal_name ?: ($organisation?->name ?? ''),
            'banks' => BankAccount::where('organisation_id', $organisationId)->where('is_active', true)->orderBy('name')
                ->get(['bank_name', 'account_name', 'account_number', 'branch_name'])->toArray(),
        ];
    }
}
