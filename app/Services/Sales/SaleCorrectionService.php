<?php

namespace App\Services\Sales;

use App\Models\AccountsReceivable;
use App\Models\AuditLog;
use App\Models\CustomerCredit;
use App\Models\CustomerReturn;
use App\Models\DeliveryNote;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\ProductUom;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleLineBatchAllocation;
use App\Models\Store;
use App\Services\Finance\DocumentJournals;
use App\Services\Finance\JournalPoster;
use App\Services\Finance\SalesJournalMapper;
use App\Services\Inventory\FefoAllocator;
use App\Services\Inventory\StockLedgerService;
use App\Services\Tax\EtimsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SaleCorrectionBlockedException extends \RuntimeException {}

/**
 * Corrects a posted sales invoice — its date, and each line's quantity and
 * unit price — as if the right figures had been posted, with the trail
 * kept:
 *
 *  - Stock: a quantity change posts SALE_CORRECTION ledger rows — returned
 *    to the batches it was drawn from, or drawn FEFO from the sale's store.
 *  - GL: every journal the sale stands on is reversed on its own date and
 *    the corrected sale posted afresh on the (corrected) invoice date. The
 *    money taken at the till stays as taken; whatever the corrected total
 *    leaves unpaid is on the customer's account.
 *  - AR: an ADJUSTMENT row moves the customer's balance by the difference.
 *
 * Refused for: voided sales, sales already reported to KRA through eTIMS
 * (a credit note is the only correction KRA accepts), sales with returns
 * or delivery notes against them, a total below the money already
 * received, and — through JournalPoster — any date in a closed period.
 */
class SaleCorrectionService
{
    /** The accounts a till payment lands in (PaymentMethodAccounts). */
    private const PAYMENT_ROLES = ['CASH', 'BANK', 'MPESA_CLEARING'];

    public function __construct(
        private readonly StockLedgerService $ledger,
        private readonly JournalPoster $journalPoster,
        private readonly DocumentJournals $journals,
        private readonly SalesJournalMapper $journalMapper,
        private readonly FefoAllocator $fefo,
    ) {}

    /**
     * @param  array{posted_at?: ?string, lines?: list<array{id: string, qty?: string|int|float, unit_price?: string|int|float}>}  $changes
     */
    public function correct(Sale $sale, array $changes, string $reason, int $userId): Sale
    {
        return DB::transaction(function () use ($sale, $changes, $reason, $userId) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $this->guard($sale);
            $before = $this->snapshot($sale);

            $live = $this->journals->live($sale->id, ['sale', 'sale_correction']);
            $paymentLines = $this->paymentLines($live);
            $paidAtTill = array_reduce($paymentLines, fn (string $sum, array $l) => bcadd($sum, $l['debit'], 4), '0.0000');

            if (! empty($changes['posted_at'])) {
                $date = Carbon::parse($changes['posted_at']);
                $sale->posted_at = $date->toDateString() === $sale->posted_at->toDateString() ? $sale->posted_at : $date->setTimeFrom($sale->posted_at);
            }

            $store = Store::findOrFail($sale->store_id);
            foreach ($changes['lines'] ?? [] as $change) {
                $line = $sale->lines()->whereKey($change['id'])->firstOrFail();
                $this->correctLine($sale, $store, $line, $change, $userId);
            }

            $this->retotal($sale);
            $grand = (string) $sale->grand_total;

            $received = (string) DB::table('payment_allocations')->where('allocated_to_type', 'sale')->where('allocated_to_id', $sale->id)->sum('amount');
            if (bccomp($grand, $received, 4) < 0) {
                throw new SaleCorrectionBlockedException("The corrected total ({$grand}) is below the {$received} already received for this sale. Refund the difference through a customer return instead.");
            }
            $creditAmount = bccomp($grand, $paidAtTill, 4) > 0 ? bcsub($grand, $paidAtTill, 4) : '0.0000';
            if (bccomp($creditAmount, '0', 4) > 0 && ! $sale->customer_id) {
                throw new SaleCorrectionBlockedException("The corrected total ({$grand}) is more than the {$paidAtTill} paid at the till, and this walk-in sale has no customer account to carry the balance.");
            }

            // GL: take back what the sale stands on, post the corrected sale afresh.
            foreach ($live as $journal) {
                $this->journals->reverse($journal, 'sale_correction', "Sale {$sale->doc_number} corrected", $userId);
            }
            $this->journalPoster->post([
                'organisation_id' => $sale->organisation_id,
                'branch_id' => $sale->branch_id,
                'entry_date' => $sale->posted_at,
                'source_doc_type' => 'sale_correction',
                'source_doc_id' => $sale->id,
                'narration' => "Sale {$sale->doc_number} corrected: {$reason}",
                'posted_by' => $userId,
            ], array_merge($paymentLines, $this->journalMapper->buildLines($sale->fresh(), [], $creditAmount)));

            $this->adjustReceivable($sale, $creditAmount);

            // Not yet with KRA (or only in the local log): transmit the corrected invoice.
            if (in_array($sale->etims_status, [EtimsService::PENDING, EtimsService::FAILED, EtimsService::SUBMITTED], true)) {
                app(EtimsService::class)->queue($sale);
            }

            $sale = $sale->fresh(['lines.batchAllocations']);
            AuditLog::record('SALE_CORRECTED', 'sale', $sale->id, [
                'user_id' => $userId,
                'branch_id' => $sale->branch_id,
                'reference' => $sale->doc_number,
                'reason' => $reason,
                'before_json' => $before,
                'after_json' => $this->snapshot($sale),
            ]);

            return $sale;
        });
    }

    private function guard(Sale $sale): void
    {
        if ($sale->status !== 'POSTED') {
            throw new SaleCorrectionBlockedException("Sale {$sale->doc_number} is {$sale->status}; only a posted sale can be corrected.");
        }
        if ($this->reportedToKra($sale)) {
            throw new SaleCorrectionBlockedException("Sale {$sale->doc_number} has already been reported to KRA through eTIMS (control code {$sale->etims_control_code}). KRA only accepts a credit note against it: record a customer return, then a new sale with the right figures.");
        }
        if (CustomerReturn::where('sale_id', $sale->id)->exists()) {
            throw new SaleCorrectionBlockedException("Sale {$sale->doc_number} has returns recorded against it; correct it through a further return instead.");
        }
        if (DeliveryNote::where('sale_id', $sale->id)->exists()) {
            throw new SaleCorrectionBlockedException("Sale {$sale->doc_number} was posted by a wholesale dispatch; correct it through a return or a void and re-dispatch.");
        }
    }

    /** The local "log" eTIMS driver records submissions without sending them; those were never with KRA. */
    private function reportedToKra(Sale $sale): bool
    {
        return $sale->etims_status === EtimsService::SUBMITTED && ! str_starts_with((string) $sale->etims_control_code, 'LOG-');
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function correctLine(Sale $sale, Store $store, SaleLine $line, array $change, int $userId): void
    {
        if ($line->is_bonus) {
            throw new SaleCorrectionBlockedException("Line {$line->line_number} is a bonus line; it follows the promotion and cannot be corrected by hand.");
        }

        $qty = bcadd((string) ($change['qty'] ?? $line->qty), '0', 4);
        $priceChanged = isset($change['unit_price']) && bccomp((string) $change['unit_price'], (string) $line->unit_price, 4) !== 0;
        $unitPrice = bcadd((string) ($change['unit_price'] ?? $line->unit_price), '0', 4);
        // A corrected price is the price: no discount is left on top of it.
        $listPrice = $priceChanged ? $unitPrice : (string) $line->list_price;

        $listAmount = bcmul($listPrice, $qty, 4);
        $netAmount = bcmul($unitPrice, $qty, 4);
        $discount = bcsub($listAmount, $netAmount, 4);
        $taxAmount = $this->round2(bcmul($netAmount, bcdiv((string) $line->tax_rate, '100', 6), 6));

        $product = Product::findOrFail($line->product_id);
        $uom = ProductUom::where('product_id', $product->id)->where('uom_id', $line->uom_id)->firstOrFail();
        $newBase = $product->toBase($uom, $qty);
        $delta = bcsub($newBase, (string) $line->qty_base, 4);

        if (bccomp($delta, '0', 4) < 0) {
            $this->returnStock($sale, $line, bcmul($delta, '-1', 4), $userId);
        } elseif (bccomp($delta, '0', 4) > 0) {
            $this->drawStock($sale, $store, $product, $line, $delta, (int) $uom->factor_to_base, $userId);
        }

        $lineCost = '0.0000';
        foreach ($line->batchAllocations()->get() as $allocation) {
            $lineCost = bcadd($lineCost, bcmul((string) $allocation->qty_base, (string) $allocation->unit_cost, 4), 4);
        }

        $line->update([
            'qty' => $qty,
            'qty_base' => $newBase,
            'list_price' => $listPrice,
            'unit_price' => $unitPrice,
            'discount_amount' => $discount,
            'discount_pct' => bccomp($listPrice, '0', 4) > 0 ? bcdiv(bcmul(bcsub($listPrice, $unitPrice, 4), '100', 4), $listPrice, 3) : '0',
            'tax_amount' => $taxAmount,
            'line_total' => bcadd($netAmount, $taxAmount, 4),
            'unit_cost' => bccomp($newBase, '0', 4) > 0 ? bcdiv($lineCost, $newBase, 4) : '0',
            'line_cost' => $lineCost,
        ]);
    }

    /** Puts the excess back into the batches the line drew from, latest-expiring first. */
    private function returnStock(Sale $sale, SaleLine $line, string $qty, int $userId): void
    {
        $allocations = $line->batchAllocations()->with('batch:id,expiry_date')->get()
            ->sortByDesc(fn (SaleLineBatchAllocation $a) => $a->batch?->expiry_date?->toDateString());

        foreach ($allocations as $allocation) {
            if (bccomp($qty, '0', 4) <= 0) {
                break;
            }
            $take = bccomp((string) $allocation->qty_base, $qty, 4) < 0 ? (string) $allocation->qty_base : $qty;
            $this->postLedger($sale, $line, $allocation->batch_id, $allocation->store_id, $take, (string) $allocation->unit_cost, $userId);

            $left = bcsub((string) $allocation->qty_base, $take, 4);
            bccomp($left, '0', 4) > 0 ? $allocation->update(['qty_base' => $left]) : $allocation->delete();
            $qty = bcsub($qty, $take, 4);
        }
    }

    private function drawStock(Sale $sale, Store $store, Product $product, SaleLine $line, string $qty, int $factor, int $userId): void
    {
        $allocations = $this->fefo->allocate($product, $store, $qty, packIntegrityRequired: false, saleUomFactor: $factor, minShelfLifeDays: 0);

        foreach ($allocations as $a) {
            SaleLineBatchAllocation::create([
                'sale_line_id' => $line->id,
                'batch_id' => $a->batchId,
                'store_id' => $store->id,
                'qty_base' => $a->qty,
                'unit_cost' => $a->unitCost,
                'is_bonus' => false,
                'fefo_overridden' => false,
            ]);
            $this->postLedger($sale, $line, $a->batchId, $store->id, bcmul($a->qty, '-1', 4), $a->unitCost, $userId);
        }
    }

    private function postLedger(Sale $sale, SaleLine $line, string $batchId, string $storeId, string $qty, string $cost, int $userId): void
    {
        $this->ledger->post([
            'organisation_id' => $sale->organisation_id,
            'txn_type' => 'SALE_CORRECTION',
            'product_id' => $line->product_id,
            'batch_id' => $batchId,
            'store_id' => $storeId,
            'qty_base' => $qty,
            'unit_cost' => $cost,
            'source_doc_type' => 'sale',
            'source_doc_id' => $sale->id,
            'source_doc_line_id' => $line->id,
            'user_id' => $userId,
            'branch_id' => $sale->branch_id,
        ]);
    }

    private function retotal(Sale $sale): void
    {
        $subtotal = $discount = $tax = $cost = '0.0000';
        foreach ($sale->lines()->get() as $line) {
            $subtotal = bcadd($subtotal, bcmul((string) $line->list_price, (string) $line->qty, 4), 4);
            $discount = bcadd($discount, (string) $line->discount_amount, 4);
            $tax = bcadd($tax, (string) $line->tax_amount, 4);
            $cost = bcadd($cost, (string) $line->line_cost, 4);
        }

        $sale->fill([
            'subtotal' => $subtotal, 'discount_total' => $discount, 'tax_total' => $tax, 'cost_total' => $cost,
            'grand_total' => bcadd(bcsub($subtotal, $discount, 4), $tax, 4),
        ])->save();
    }

    /**
     * The till's money lines of the journal the sale stands on (cash, bank,
     * M-PESA) — the payments are facts the correction does not change.
     *
     * @param  iterable<int, JournalEntry>  $live
     * @return list<array{account_id: string, debit: string, narration: ?string}>
     */
    private function paymentLines(iterable $live): array
    {
        $lines = [];
        foreach ($live as $journal) {
            foreach ($journal->lines as $line) {
                /** @var JournalEntryLine $line */
                $role = $line->account?->system_role;
                if (bccomp((string) $line->debit_amount, '0', 4) > 0 && in_array($role, self::PAYMENT_ROLES, true)) {
                    $lines[] = ['account_id' => $line->account_id, 'debit' => (string) $line->debit_amount, 'narration' => $line->narration];
                }
            }
        }

        return $lines;
    }

    private function adjustReceivable(Sale $sale, string $creditAmount): void
    {
        $current = (string) AccountsReceivable::where('sale_id', $sale->id)->whereIn('txn_type', ['INVOICE', 'ADJUSTMENT'])->sum('amount');
        $delta = bcsub($creditAmount, $current, 4);
        if (bccomp($delta, '0', 4) === 0 || ! $sale->customer_id) {
            return;
        }

        $credit = CustomerCredit::where('customer_id', $sale->customer_id)->lockForUpdate()->first()
            ?? CustomerCredit::create(['customer_id' => $sale->customer_id, 'credit_limit' => '0']);
        $newBalance = bcadd((string) $credit->current_balance, $delta, 4);

        AccountsReceivable::create([
            'organisation_id' => $sale->organisation_id,
            'customer_id' => $sale->customer_id,
            'txn_type' => 'ADJUSTMENT',
            'sale_id' => $sale->id,
            'amount' => $delta,
            'balance_after' => $newBalance,
            'branch_id' => $sale->branch_id,
            'created_at' => now(),
        ]);
        $credit->update(['current_balance' => $newBalance]);
    }

    /** @return array<string, mixed> */
    private function snapshot(Sale $sale): array
    {
        return [
            'posted_at' => $sale->posted_at?->toDateTimeString(),
            'subtotal' => (string) $sale->subtotal,
            'discount_total' => (string) $sale->discount_total,
            'tax_total' => (string) $sale->tax_total,
            'grand_total' => (string) $sale->grand_total,
            'lines' => $sale->lines()->get()->map(fn (SaleLine $l) => $l->only(['id', 'qty', 'list_price', 'unit_price', 'discount_amount', 'tax_amount', 'line_total']))->all(),
        ];
    }

    private function round2(string $value): string
    {
        $shift = bccomp($value, '0', 6) >= 0 ? '0.005' : '-0.005';

        return bcadd(bcadd($value, $shift, 6), '0', 2);
    }
}
