<?php

namespace App\Services\Procurement;

use App\Models\AccountsPayable;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PurchaseOrderLine;
use App\Models\SupplierInvoice;
use App\Services\Finance\JournalPoster;
use App\Services\Pricing\Money;
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
        foreach ($this->compare($invoice) as $row) {
            array_push($failures, ...$row['failures']);
        }
        array_push($failures, ...$this->totals($invoice)['failures']);

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

    /**
     * The side-by-side view behind a match: for every invoice line, what was
     * ordered on the PO, what the GRNs accepted and what the supplier billed,
     * with the check each figure passed or failed. match() uses exactly these
     * checks, so the screen and the posting can never disagree.
     *
     * @return list<array{
     *     line_id: string, product: array{id: string, code: string|null, name: string|null},
     *     po_number: string|null, grn_numbers: list<string>,
     *     qty_ordered: string|null, qty_accepted: string|null, qty_invoiced: string,
     *     po_unit_price: string|null, invoice_unit_price: string,
     *     price_variance_per_unit: string|null, price_variance_pct: string|null, line_variance: string|null,
     *     checks: array{po_linked: bool, supplier: bool|null, quantity: bool|null, price: bool|null},
     *     failures: list<string>
     * }>
     */
    public function compare(SupplierInvoice $invoice): array
    {
        $invoice->loadMissing(['lines.product:id,code,name', 'lines.purchaseOrderLine.purchaseOrder:id,doc_number,supplier_id', 'lines.purchaseOrderLine.goodsReceiptLines.goodsReceipt:id,doc_number']);

        $rows = [];
        foreach ($invoice->lines as $line) {
            $poLine = $line->purchaseOrderLine;
            $row = [
                'line_id' => (string) $line->id,
                'product' => ['id' => (string) $line->product_id, 'code' => $line->product?->code, 'name' => $line->product?->name],
                'po_number' => null,
                'grn_numbers' => [],
                'qty_ordered' => null,
                'qty_accepted' => null,
                'qty_invoiced' => (string) $line->qty,
                'po_unit_price' => null,
                'invoice_unit_price' => (string) $line->unit_price,
                'price_variance_per_unit' => null,
                'price_variance_pct' => null,
                'line_variance' => null,
                'checks' => ['po_linked' => $poLine !== null, 'supplier' => null, 'quantity' => null, 'price' => null],
                'failures' => [],
            ];

            if (! $poLine) {
                $row['failures'][] = "Line for product {$line->product_id}: no linked PO line.";
                $rows[] = $row;

                continue;
            }

            $row['po_number'] = $poLine->purchaseOrder->doc_number;
            $row['grn_numbers'] = $poLine->goodsReceiptLines->map(fn ($grnLine) => $grnLine->goodsReceipt?->doc_number)->filter()->unique()->values()->all();
            $row['qty_ordered'] = (string) $poLine->qty_ordered;
            $row['po_unit_price'] = (string) $poLine->unit_price;

            $row['checks']['supplier'] = $poLine->purchaseOrder->supplier_id === $invoice->supplier_id;
            if (! $row['checks']['supplier']) {
                $row['failures'][] = "Line {$line->id}: invoice supplier does not match the PO's supplier.";
            }

            $acceptedQty = $poLine->goodsReceiptLines->reduce(fn (string $total, $grnLine) => bcadd($total, (string) $grnLine->qty_accepted, 4), '0.0000');
            $row['qty_accepted'] = $acceptedQty;
            $row['checks']['quantity'] = bccomp((string) $line->qty, $acceptedQty, 4) <= 0;
            if (! $row['checks']['quantity']) {
                $row['failures'][] = "Line {$line->id}: invoiced qty ({$line->qty}) exceeds accepted qty ({$acceptedQty}) — over-invoicing.";
            }

            // Part 9.3 — the percentage tolerance is what blocks (Trace 10:
            // 432 vs 420 = 2.86% → exception). The absolute KES floor exists
            // so a cheap item does not block on a few shillings' worth of
            // percentage: a line only fails when it exceeds BOTH.
            $priceDiff = bcsub((string) $line->unit_price, (string) $poLine->unit_price, 4);
            $priceDiffAbs = bccomp($priceDiff, '0', 4) < 0 ? bcmul($priceDiff, '-1', 4) : $priceDiff;
            $percentTolerance = bcdiv(bcmul((string) $poLine->unit_price, self::DEFAULT_PRICE_VARIANCE_PCT, 6), '100', 4);
            $lineVariance = bcmul($priceDiffAbs, (string) $line->qty, 4);
            $variancePct = bccomp((string) $poLine->unit_price, '0', 4) > 0
                ? bcmul(bcdiv($priceDiffAbs, (string) $poLine->unit_price, 6), '100', 2)
                : '0.00';

            $row['price_variance_per_unit'] = $priceDiff;
            $row['price_variance_pct'] = $variancePct;
            $row['line_variance'] = $lineVariance;

            $beyondPercent = bccomp($priceDiffAbs, $percentTolerance, 4) > 0;
            $beyondAbsolute = bccomp($lineVariance, self::DEFAULT_PRICE_VARIANCE_ABS, 4) > 0;
            $row['checks']['price'] = ! ($beyondPercent && $beyondAbsolute);

            if (! $row['checks']['price']) {
                $row['failures'][] = "Line {$line->id}: price variance {$priceDiffAbs}/unit ({$variancePct}%, KES {$lineVariance} on the line) exceeds tolerance "
                    .self::DEFAULT_PRICE_VARIANCE_PCT.'% / KES '.self::DEFAULT_PRICE_VARIANCE_ABS.'.';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * What the invoice should come to, from the purchase order alone: the
     * ordered price of each invoiced quantity plus the order's transport
     * shared over those quantities. Nothing else is added — no VAT, no other
     * charge — and an invoice that comes to more than that does not match.
     *
     * @return array{
     *     goods_expected: string, transport: string, expected_total: string,
     *     goods_invoiced: string, tax_invoiced: string, invoiced_total: string,
     *     checks: array{no_tax: bool, total: bool}, failures: list<string>
     * }
     */
    public function totals(SupplierInvoice $invoice): array
    {
        $invoice->loadMissing('lines.purchaseOrderLine');

        $goodsExpected = '0.0000';
        foreach ($invoice->lines as $line) {
            if ($line->purchaseOrderLine) {
                $goodsExpected = bcadd($goodsExpected, bcmul((string) $line->qty, (string) ($line->purchaseOrderLine->unit_price ?? '0'), 4), 4);
            }
        }

        $transport = bcadd((string) $invoice->transport_total, '0', 4);
        $tax = bcadd((string) $invoice->tax_total, '0', 4);
        $goodsInvoiced = (string) $invoice->lines->reduce(fn (string $sum, $line) => bcadd($sum, (string) $line->line_total, 4), '0.0000');
        $expected = bcadd($goodsExpected, $transport, 4);
        $invoiced = bcadd(bcadd($goodsInvoiced, $transport, 4), $tax, 4);

        $failures = [];
        $noTax = bccomp($tax, '0', 4) === 0;
        if (! $noTax) {
            $failures[] = "KES {$tax} of tax is added on the invoice; the match expects only the goods and the order's transport.";
        }
        // A shilling of rounding is the supplier's arithmetic, not an extra charge.
        $totalOk = bccomp($invoiced, bcadd($expected, '1.00', 4), 4) <= 0;
        if (! $totalOk) {
            $failures[] = "The invoice comes to KES {$invoiced}, more than the KES {$expected} the order holds for these goods and their transport.";
        }

        return [
            'goods_expected' => $goodsExpected,
            'transport' => $transport,
            'expected_total' => $expected,
            'goods_invoiced' => $goodsInvoiced,
            'tax_invoiced' => $tax,
            'invoiced_total' => $invoiced,
            'checks' => ['no_tax' => $noTax, 'total' => $totalOk],
            'failures' => $failures,
        ];
    }

    /**
     * The order's transport carried by these invoice lines: each PO line's
     * share per unit times the quantity invoiced.
     *
     * @param  iterable<array{purchase_order_line_id: string, qty: string|int|float}>  $lines
     */
    public function transportFor(iterable $lines): string
    {
        $shares = [];
        $total = '0.0000';
        foreach ($lines as $line) {
            $poLine = PurchaseOrderLine::with('purchaseOrder')->find($line['purchase_order_line_id']);
            if (! $poLine) {
                continue;
            }
            $orderId = (string) $poLine->purchase_order_id;
            $shares[$orderId] ??= $poLine->purchaseOrder->transportShares();
            $total = bcadd($total, bcmul((string) $line['qty'], $shares[$orderId][(string) $poLine->id] ?? '0', 4), 4);
        }

        return Money::round($total, 2);
    }

    /**
     * Takes a matched invoice back off the books so it can be corrected or
     * removed: the payable it raised is cancelled by an opposite entry and
     * its journal is reversed, then it waits to be matched again. Refused
     * when the supplier has already been paid so much that cancelling it
     * would leave them owing us.
     */
    public function unmatch(SupplierInvoice $invoice, string $reason, int $userId): void
    {
        if ($invoice->match_status !== 'MATCHED') {
            return;
        }

        DB::transaction(function () use ($invoice, $reason, $userId) {
            $raised = (string) AccountsPayable::where('supplier_invoice_id', $invoice->id)->sum('amount');
            $balance = AccountsPayable::balanceFor($invoice->supplier_id);
            if (bccomp($raised, $balance, 4) > 0) {
                throw new \DomainException("Invoice {$invoice->invoice_number} has already been paid: the supplier is owed KES {$balance}, less than the KES {$raised} this invoice raised. Record a supplier return or credit instead.");
            }

            if (bccomp($raised, '0', 4) !== 0) {
                AccountsPayable::create([
                    'organisation_id' => Branch::whereKey($invoice->branch_id)->value('organisation_id'),
                    'supplier_id' => $invoice->supplier_id,
                    'txn_type' => 'ADJUSTMENT',
                    'supplier_invoice_id' => $invoice->id,
                    'amount' => bcmul($raised, '-1', 4),
                    'balance_after' => bcsub($balance, $raised, 4),
                    'branch_id' => $invoice->branch_id,
                    'created_at' => now(),
                ]);
            }

            $journal = JournalEntry::with('lines')->where('source_doc_type', 'supplier_invoice')->where('source_doc_id', $invoice->id)
                ->whereNull('reverses_journal_id')->latest('posted_at')->first();
            if ($journal) {
                $this->journalPoster->post([
                    'organisation_id' => $journal->organisation_id,
                    'branch_id' => $journal->branch_id,
                    'entry_date' => now(),
                    'source_doc_type' => 'supplier_invoice_reversal',
                    'source_doc_id' => $invoice->id,
                    'narration' => "Supplier invoice {$invoice->invoice_number} taken back: {$reason}",
                    'posted_by' => $userId,
                    'reverses_journal_id' => $journal->id,
                ], $journal->lines->map(fn (JournalEntryLine $l) => [
                    'account_id' => $l->account_id,
                    'debit' => (string) $l->credit_amount,
                    'credit' => (string) $l->debit_amount,
                    'partner_type' => $l->partner_type,
                    'partner_id' => $l->partner_id,
                ])->all());
            }

            $invoice->update(['match_status' => 'UNMATCHED', 'matched_at' => null]);
            AuditLog::record('INVOICE_UNMATCHED', 'supplier_invoice', $invoice->id, ['reference' => $invoice->invoice_number, 'reason' => $reason]);
        });
    }

    /**
     * @return array{price_variance_pct: string, price_variance_abs: string}
     */
    public function tolerances(): array
    {
        return ['price_variance_pct' => self::DEFAULT_PRICE_VARIANCE_PCT, 'price_variance_abs' => self::DEFAULT_PRICE_VARIANCE_ABS];
    }

    private function postPayable(SupplierInvoice $invoice, ?int $matchedBy): void
    {
        $organisationId = Branch::whereKey($invoice->branch_id)->value('organisation_id');
        $lineTotal = (string) $invoice->lines()->sum('line_total');
        $subtotal = bccomp((string) $invoice->subtotal, '0', 4) > 0 ? (string) $invoice->subtotal : $lineTotal;
        // The payable is the goods plus the order's transport: nothing is added on top.
        $transport = (string) $invoice->transport_total;
        $grand = bcadd($subtotal, $transport, 4);

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
        // The order's transport, accrued at receipt, is now owed to this supplier.
        if (bccomp($transport, '0', 4) > 0) {
            $lines[] = ['account_role' => 'FREIGHT_ACCRUAL', 'debit' => $transport, 'narration' => "Invoice {$invoice->invoice_number} — transport on the order"];
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
