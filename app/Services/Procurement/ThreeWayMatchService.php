<?php

namespace App\Services\Procurement;

use App\Models\AccountsPayable;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\SupplierInvoice;
use App\Services\Finance\JournalPoster;
use Illuminate\Support\Facades\DB;

final class MatchResult
{
    /**
     * @param  list<string>  $failures
     */
    public function __construct(
        public readonly bool $matched,
        public readonly array $failures,
    ) {}
}

/**
 * Part 9.4 — compares PO <-> GRN <-> Supplier Invoice on quantity and price,
 * per line. Only a matched invoice creates an accounts_payable entry and
 * posts the Part 12.3 "Supplier invoice matched" journal (Dr GRN accrual /
 * Dr VAT input / Cr Accounts payable); everything else sits in an
 * exception queue. Tolerances follow the Part 9.3 defaults.
 */
class ThreeWayMatchService
{
    private const DEFAULT_PRICE_VARIANCE_PCT = '2.0';

    private const DEFAULT_PRICE_VARIANCE_ABS = '500.0000';

    public function __construct(private readonly JournalPoster $journalPoster) {}

    public function match(SupplierInvoice $invoice, ?int $matchedBy = null): MatchResult
    {
        if ($invoice->match_status === 'MATCHED') {
            return new MatchResult(true, []);
        }

        $failures = [];

        foreach ($invoice->lines as $line) {
            $poLine = $line->purchaseOrderLine;

            if (! $poLine) {
                $failures[] = "Line for product {$line->product_id}: no linked PO line.";

                continue;
            }

            if ($poLine->purchaseOrder->supplier_id !== $invoice->supplier_id) {
                $failures[] = "Line {$line->id}: invoice supplier does not match the PO's supplier.";
            }

            $acceptedQty = (string) $poLine->goodsReceiptLines()->sum('qty_accepted');
            if (bccomp((string) $line->qty, $acceptedQty, 4) > 0) {
                $failures[] = "Line {$line->id}: invoiced qty ({$line->qty}) exceeds accepted qty ({$acceptedQty}) — over-invoicing.";
            }

            // Part 9.3 — the percentage tolerance is what blocks (Trace 10:
            // 432 vs 420 = 2.86% → exception). The absolute KES floor exists
            // so a cheap item does not block on a few shillings' worth of
            // percentage: a line only fails when it exceeds BOTH.
            $priceDiff = bcsub((string) $line->unit_price, (string) $poLine->unit_price, 4);
            $priceDiffAbs = bccomp($priceDiff, '0', 4) < 0 ? bcmul($priceDiff, '-1', 4) : $priceDiff;
            $percentTolerance = bcdiv(bcmul((string) $poLine->unit_price, self::DEFAULT_PRICE_VARIANCE_PCT, 6), '100', 4);
            $lineVariance = bcmul($priceDiffAbs, (string) $line->qty, 4);

            $beyondPercent = bccomp($priceDiffAbs, $percentTolerance, 4) > 0;
            $beyondAbsolute = bccomp($lineVariance, self::DEFAULT_PRICE_VARIANCE_ABS, 4) > 0;

            if ($beyondPercent && $beyondAbsolute) {
                $variancePct = bccomp((string) $poLine->unit_price, '0', 4) > 0
                    ? bcmul(bcdiv($priceDiffAbs, (string) $poLine->unit_price, 6), '100', 2)
                    : '0.00';
                $failures[] = "Line {$line->id}: price variance {$priceDiffAbs}/unit ({$variancePct}%, KES {$lineVariance} on the line) exceeds tolerance "
                    .self::DEFAULT_PRICE_VARIANCE_PCT.'% / KES '.self::DEFAULT_PRICE_VARIANCE_ABS.'.';
            }
        }

        $matched = $failures === [];

        DB::transaction(function () use ($invoice, $matched, $matchedBy) {
            $invoice->update([
                'match_status' => $matched ? 'MATCHED' : 'EXCEPTION',
                'matched_at' => $matched ? now() : null,
            ]);

            if ($matched) {
                $this->postPayable($invoice, $matchedBy);
            }
        });

        return new MatchResult($matched, $failures);
    }

    private function postPayable(SupplierInvoice $invoice, ?int $matchedBy): void
    {
        $organisationId = Branch::whereKey($invoice->branch_id)->value('organisation_id');
        $lineTotal = (string) $invoice->lines()->sum('line_total');
        $subtotal = bccomp((string) $invoice->subtotal, '0', 4) > 0 ? (string) $invoice->subtotal : $lineTotal;
        $tax = (string) $invoice->tax_total;
        $grand = bccomp((string) $invoice->grand_total, '0', 4) > 0 ? (string) $invoice->grand_total : bcadd($subtotal, $tax, 4);

        $balance = AccountsPayable::balanceFor($invoice->supplier_id);
        AccountsPayable::create([
            'organisation_id' => $organisationId,
            'supplier_id' => $invoice->supplier_id,
            'txn_type' => 'INVOICE',
            'supplier_invoice_id' => $invoice->id,
            'amount' => $grand,
            'balance_after' => bcadd($balance, $grand, 4),
            'due_date' => $invoice->due_date,
            'branch_id' => $invoice->branch_id,
            'created_at' => now(),
        ]);

        $lines = [
            ['account_role' => 'GRN_ACCRUAL', 'debit' => $subtotal, 'partner_type' => 'supplier', 'partner_id' => $invoice->supplier_id, 'narration' => "Invoice {$invoice->invoice_number} matched to receipt"],
        ];
        if (bccomp($tax, '0', 4) > 0) {
            $lines[] = ['account_role' => 'VAT_INPUT', 'debit' => $tax, 'narration' => "Invoice {$invoice->invoice_number} — input VAT"];
        }
        $lines[] = ['account_role' => 'AP_CONTROL', 'credit' => $grand, 'partner_type' => 'supplier', 'partner_id' => $invoice->supplier_id, 'narration' => "Invoice {$invoice->invoice_number} payable"];

        $this->journalPoster->post([
            'organisation_id' => $organisationId,
            'branch_id' => $invoice->branch_id,
            'entry_date' => $invoice->invoice_date ?? now(),
            'source_doc_type' => 'supplier_invoice',
            'source_doc_id' => $invoice->id,
            'narration' => "Supplier invoice {$invoice->invoice_number} matched",
            'posted_by' => $matchedBy ?? (int) (auth()->id() ?? 0) ?: $this->fallbackUserId(),
        ], $lines);

        AuditLog::record('INVOICE_MATCHED', 'supplier_invoice', $invoice->id, [
            'reference' => $invoice->invoice_number,
            'after_json' => ['grand_total' => $grand],
        ]);
    }

    private function fallbackUserId(): int
    {
        return (int) DB::table('users')->orderBy('id')->value('id');
    }
}
