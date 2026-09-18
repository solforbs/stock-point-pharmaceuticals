<?php

namespace App\Services\Finance;

/**
 * The payment-method -> GL account role mapping, shared by SalesJournalMapper
 * (money in at checkout time) and ReceiptService (money in against an
 * existing AR balance) — one place, so the two never drift apart.
 */
class PaymentMethodAccounts
{
    private const ROLE = [
        'CASH' => 'CASH',
        'BANK' => 'BANK',
        'MPESA' => 'MPESA_CLEARING',
        'CARD' => 'BANK',
        'CHEQUE' => 'BANK',
    ];

    public static function role(string $method): string
    {
        return self::ROLE[$method] ?? 'CASH';
    }
}
