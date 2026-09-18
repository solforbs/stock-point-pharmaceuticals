<?php

namespace App\Services\Finance;

use App\Models\Sale;

/**
 * Part 12.3 — the posting map, for a single posted sale. One journal entry
 * covers both the revenue side and the cost-of-goods side, since a journal
 * only needs to balance overall, not line-pair by line-pair.
 *
 * Revenue side:
 *   Dr  Cash/Bank/M-PESA clearing / Accounts Receivable   (grand_total, split across
 *       however the sale was paid — see $paidByMethod / $creditAmount)
 *   Dr  Sales discounts (contra-revenue)                  (discount_total)
 *   Cr  Sales - {mode}                                     (subtotal, i.e. gross of
 *       discount, net of tax)
 *   Cr  VAT payable                                        (tax_total)
 *
 * Cost side:
 *   Dr  Cost of goods sold                                 (cost_total, less bonus cost)
 *   Dr  Bonus goods cost                                   (bonus line cost only)
 *   Cr  Inventory                                           (cost_total)
 */
class SalesJournalMapper
{
    private const MODE_REVENUE_ROLE = [
        'RETAIL' => 'SALES_RETAIL',
        'WHOLESALE' => 'SALES_WHOLESALE',
        'DISPENSING' => 'SALES_DISPENSING',
    ];

    /**
     * @param  array<string, string>  $paidByMethod  method => amount paid at checkout
     * @return list<array{account_role: string, debit?: string, credit?: string, partner_type?: ?string, partner_id?: ?string, narration?: string}>
     */
    public function buildLines(Sale $sale, array $paidByMethod, string $creditAmount): array
    {
        $lines = [];

        foreach ($paidByMethod as $method => $amount) {
            if (bccomp($amount, '0', 4) <= 0) {
                continue;
            }
            $lines[] = [
                'account_role' => PaymentMethodAccounts::role($method),
                'debit' => $amount,
                'narration' => "Sale {$sale->doc_number} — {$method}",
            ];
        }

        if (bccomp($creditAmount, '0', 4) > 0) {
            $lines[] = [
                'account_role' => 'AR_CONTROL',
                'debit' => $creditAmount,
                'partner_type' => 'customer',
                'partner_id' => $sale->customer_id,
                'narration' => "Sale {$sale->doc_number} — on credit",
            ];
        }

        if (bccomp((string) $sale->discount_total, '0', 4) > 0) {
            $lines[] = [
                'account_role' => 'SALES_DISCOUNTS',
                'debit' => (string) $sale->discount_total,
                'narration' => "Sale {$sale->doc_number} — discount given",
            ];
        }

        if (bccomp((string) $sale->subtotal, '0', 4) > 0) {
            $lines[] = [
                'account_role' => self::MODE_REVENUE_ROLE[$sale->sale_mode] ?? 'SALES_RETAIL',
                'credit' => (string) $sale->subtotal,
                'narration' => "Sale {$sale->doc_number} — revenue",
            ];
        }

        if (bccomp((string) $sale->tax_total, '0', 4) > 0) {
            $lines[] = [
                'account_role' => 'VAT_OUTPUT',
                'credit' => (string) $sale->tax_total,
                'narration' => "Sale {$sale->doc_number} — VAT",
            ];
        }

        $bonusCost = (string) $sale->lines()->where('is_bonus', true)->sum('line_cost');
        $regularCost = bcsub((string) $sale->cost_total, $bonusCost, 4);

        if (bccomp($regularCost, '0', 4) > 0) {
            $lines[] = ['account_role' => 'COGS', 'debit' => $regularCost, 'narration' => "Sale {$sale->doc_number} — COGS"];
        }
        if (bccomp($bonusCost, '0', 4) > 0) {
            $lines[] = ['account_role' => 'BONUS_GOODS_COST', 'debit' => $bonusCost, 'narration' => "Sale {$sale->doc_number} — bonus cost"];
        }
        if (bccomp((string) $sale->cost_total, '0', 4) > 0) {
            $lines[] = ['account_role' => 'INVENTORY', 'credit' => (string) $sale->cost_total, 'narration' => "Sale {$sale->doc_number} — inventory out"];
        }

        return $lines;
    }
}
