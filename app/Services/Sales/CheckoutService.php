<?php

namespace App\Services\Sales;

use App\Models\AccountsReceivable;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\NumberSequence;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Product;
use App\Models\ProductUom;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleLineBatchAllocation;
use App\Models\Store;
use App\Services\Finance\JournalPoster;
use App\Services\Finance\SalesJournalMapper;
use App\Services\Inventory\FefoAllocator;
use App\Services\Inventory\StockLedgerService;
use App\Services\Pricing\PriceQuoteService;
use App\Services\Tax\EtimsService;
use Illuminate\Support\Facades\DB;

class CreditLimitExceededException extends \RuntimeException
{
    public function __construct(public readonly string $limit, public readonly string $exposure, public readonly string $shortfall)
    {
        parent::__construct("Credit limit exceeded: limit {$limit}, exposure {$exposure}, shortfall {$shortfall}.");
    }
}

class CreditHoldException extends \RuntimeException {}

class ApprovalRequiredException extends \RuntimeException
{
    /**
     * @param  list<string>  $lineRefs
     */
    public function __construct(public readonly array $lineRefs)
    {
        parent::__construct('This quote needs a named approver before it can post: lines '.implode(', ', $lineRefs).'.');
    }
}

class PaymentMismatchException extends \RuntimeException
{
    public function __construct(public readonly string $grandTotal, public readonly string $tendered)
    {
        parent::__construct("Payments ({$tendered}) do not cover the sale total ({$grandTotal}) and no customer is on the sale to carry the balance.");
    }
}

/**
 * Part 6.5 / V6 Part 10.3 — the checkout atomic transaction. "All of the
 * above or none. There is no partial sale." Patient/prescription-specific
 * steps (controlled_drug_entry, dispensing_record) are deliberately not
 * implemented yet — those tables don't exist until the Dispensing phase —
 * but sale_mode already accepts DISPENSING so this engine doesn't need to
 * change shape when that phase adds them.
 *
 * Two entry points:
 *   - checkoutFromQuote(): the only path the API exposes. The server
 *     re-validates the quote and takes every price from it — the client
 *     never decides a price (Part 4.1).
 *   - checkout(): internal, pre-resolved lines (dispatch, tests, migration).
 */
class CheckoutService
{
    public function __construct(
        private readonly FefoAllocator $fefo,
        private readonly StockLedgerService $ledger,
        private readonly JournalPoster $journalPoster,
        private readonly SalesJournalMapper $journalMapper,
        private readonly PriceQuoteService $quotes,
    ) {}

    /**
     * @param  array{
     *     store_id?: string, user_id: int, idempotency_key: string, terminal_id?: ?string, sub_type?: ?string,
     *     payments?: list<array{method: string, reference?: ?string, amount: string}>,
     *     approved_by?: ?int,
     * }  $meta
     */
    public function checkoutFromQuote(string $quoteId, array $meta): Sale
    {
        if ($existing = Sale::where('idempotency_key', $meta['idempotency_key'])->first()) {
            return $existing;
        }

        ['log' => $log, 'quote' => $quote] = $this->quotes->revalidate($quoteId);
        $payload = $log->payload_json;

        $needsApproval = collect($quote['lines'])->filter(fn ($l) => $l['approval_required'])->pluck('line_ref')->all();
        if ($quote['approval_required'] && empty($meta['approved_by'])) {
            throw new ApprovalRequiredException($needsApproval ?: ['header']);
        }

        $lines = [];
        foreach ($quote['lines'] as $line) {
            $lines[] = [
                'product_id' => $line['product_id'],
                'uom_id' => $line['uom_id'],
                'qty' => $line['quantity'],
                'list_price' => $line['break_price'],
                'unit_price' => $line['unit_price'],
                'discount_amount' => $line['discount_amount'],
                'discount_pct' => $line['discount_pct'],
                'discount_source' => $line['discount_source'] === 'NONE' ? null : $line['discount_source'],
                'approved_by' => $line['approval_required'] ? ($meta['approved_by'] ?? null) : null,
                'tax_code_id' => $line['tax_code_id'],
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'batch_id' => $line['batch_id'] ?? null,
                'override_reason' => $line['override_reason'] ?? null,
                'min_shelf_life_days' => $quote['min_shelf_life_days'],
            ];

            if (bccomp((string) $line['bonus_qty'], '0', 4) > 0) {
                $lines[] = [
                    'product_id' => $line['bonus_product_id'] ?? $line['product_id'],
                    'uom_id' => $line['uom_id'],
                    'qty' => $line['bonus_qty'],
                    'list_price' => '0.0000',
                    'unit_price' => '0.0000',
                    'discount_source' => 'BONUS:'.$line['bonus_promotion_id'],
                    'tax_rate' => '0',
                    'tax_amount' => '0.0000',
                    'is_bonus' => true,
                    'min_shelf_life_days' => $quote['min_shelf_life_days'],
                ];
            }
        }

        return $this->checkout([
            'organisation_id' => $payload['organisation_id'],
            'branch_id' => $payload['branch_id'],
            'store_id' => $meta['store_id'] ?? $payload['store_id'],
            'sale_mode' => $payload['sale_mode'],
            'sub_type' => $meta['sub_type'] ?? null,
            'customer_id' => $payload['customer_id'] ?? null,
            'user_id' => $meta['user_id'],
            'terminal_id' => $meta['terminal_id'] ?? null,
            'quote_id' => $quoteId,
            'idempotency_key' => $meta['idempotency_key'],
            'lines' => $lines,
            'payments' => $meta['payments'] ?? [],
        ]);
    }

    /**
     * @param  array{
     *     organisation_id: string, branch_id: string, store_id: string, sale_mode: string,
     *     sub_type?: ?string, customer_id?: ?string, user_id: int, terminal_id?: ?string, quote_id?: ?string,
     *     idempotency_key: string,
     *     lines: list<array{
     *         product_id: string, uom_id: string, qty: string, list_price: string, unit_price: string,
     *         discount_amount?: string, discount_pct?: string, discount_source?: ?string, approved_by?: ?int,
     *         tax_code_id?: ?string, tax_rate?: string, tax_amount?: ?string, is_bonus?: bool,
     *         pack_integrity_override?: ?bool, min_shelf_life_days?: int,
     *         batch_id?: ?string, override_reason?: ?string,
     *     }>,
     *     payments?: list<array{method: string, reference?: ?string, amount: string}>,
     * }  $data
     */
    public function checkout(array $data): Sale
    {
        if ($existing = Sale::where('idempotency_key', $data['idempotency_key'])->first()) {
            return $existing; // Part 6.6 — duplicate request returns the original result, never a second sale.
        }

        return DB::transaction(function () use ($data) {
            $store = Store::findOrFail($data['store_id']);

            $subtotal = '0.0000';
            $discountTotal = '0.0000';
            $taxTotal = '0.0000';
            $costTotal = '0.0000';

            $sale = Sale::create([
                'organisation_id' => $data['organisation_id'],
                'branch_id' => $data['branch_id'],
                'store_id' => $store->id,
                'sale_mode' => $data['sale_mode'],
                'sub_type' => $data['sub_type'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'quote_id' => $data['quote_id'] ?? null,
                'user_id' => $data['user_id'],
                'terminal_id' => $data['terminal_id'] ?? null,
                'doc_number' => NumberSequence::next($data['organisation_id'], 'SALE', $data['branch_id'], 'INV'),
                'status' => 'POSTED',
                'subtotal' => '0', 'discount_total' => '0', 'tax_total' => '0', 'grand_total' => '0', 'cost_total' => '0',
                'idempotency_key' => $data['idempotency_key'],
                'posted_at' => now(),
            ]);

            foreach ($data['lines'] as $i => $lineData) {
                [$lineTotals, $lineCost] = $this->postLine($sale, $store, $i + 1, $lineData);
                $subtotal = bcadd($subtotal, $lineTotals['list_amount'], 4);
                $discountTotal = bcadd($discountTotal, $lineTotals['discount_amount'], 4);
                $taxTotal = bcadd($taxTotal, $lineTotals['tax_amount'], 4);
                $costTotal = bcadd($costTotal, $lineCost, 4);
            }

            $grandTotal = bcadd(bcsub($subtotal, $discountTotal, 4), $taxTotal, 4);

            $sale->update([
                'subtotal' => $subtotal, 'discount_total' => $discountTotal,
                'tax_total' => $taxTotal, 'grand_total' => $grandTotal, 'cost_total' => $costTotal,
            ]);

            [$paidByMethod, $creditAmount] = $this->postPayments($sale, $grandTotal, $data['payments'] ?? []);

            if (bccomp($creditAmount, '0', 4) > 0) {
                $this->postCredit($sale, $creditAmount);
            }

            $journalLines = $this->journalMapper->buildLines($sale->fresh(['lines']), $paidByMethod, $creditAmount);
            $this->journalPoster->post([
                'organisation_id' => $data['organisation_id'],
                'branch_id' => $data['branch_id'],
                'entry_date' => now(),
                'source_doc_type' => 'sale',
                'source_doc_id' => $sale->id,
                'narration' => "Sale {$sale->doc_number}",
                'posted_by' => $data['user_id'],
            ], $journalLines);

            AuditLog::record('SALE_POSTED', 'sale', $sale->id, [
                'user_id' => $data['user_id'],
                'branch_id' => $data['branch_id'],
                'terminal_id' => $data['terminal_id'] ?? null,
                'reference' => $sale->doc_number,
                'after_json' => ['grand_total' => $grandTotal, 'sale_mode' => $sale->sale_mode, 'quote_id' => $data['quote_id'] ?? null],
            ]);

            // Part 13.6 — fiscalisation is queued after commit and never blocks the sale.
            app(EtimsService::class)->queue($sale);

            return $sale->fresh(['lines.batchAllocations']);
        });
    }

    /**
     * @return array{0: array{list_amount: string, discount_amount: string, tax_amount: string}, 1: string}
     */
    private function postLine(Sale $sale, Store $store, int $lineNumber, array $lineData): array
    {
        $product = Product::findOrFail($lineData['product_id']);
        $uom = ProductUom::where('product_id', $product->id)->where('uom_id', $lineData['uom_id'])->firstOrFail();

        $qtyBase = $product->toBase($uom, $lineData['qty']);
        $isBonus = $lineData['is_bonus'] ?? false;
        $packIntegrity = $lineData['pack_integrity_override'] ?? $product->pack_integrity;
        $overrideBatch = $lineData['batch_id'] ?? null;

        if ($overrideBatch) {
            $allocations = $this->fefo->allocateFromBatch($product, $store, $overrideBatch, $qtyBase);
        } else {
            $allocations = $this->fefo->allocate(
                $product, $store, $qtyBase,
                packIntegrityRequired: $packIntegrity,
                saleUomFactor: (int) $uom->factor_to_base,
                minShelfLifeDays: $lineData['min_shelf_life_days'] ?? 90,
            );
        }

        $listAmount = bcmul($lineData['list_price'], $lineData['qty'], 4);
        $discountAmount = $lineData['discount_amount'] ?? '0';
        $netAmount = bcsub($listAmount, $discountAmount, 4);
        $taxRate = $lineData['tax_rate'] ?? '0';
        // A quoted line carries its server-computed tax; a pre-resolved line computes it here.
        $taxAmount = $lineData['tax_amount'] ?? bcmul($netAmount, bcdiv($taxRate, '100', 6), 4);
        $lineTotal = bcadd($netAmount, $taxAmount, 4);

        $lineCost = '0.0000';
        foreach ($allocations as $a) {
            $lineCost = bcadd($lineCost, bcmul($a->qty, $a->unitCost, 4), 4);
        }

        $saleLine = SaleLine::create([
            'sale_id' => $sale->id,
            'line_number' => $lineNumber,
            'product_id' => $product->id,
            'uom_id' => $uom->uom_id,
            'qty' => $lineData['qty'],
            'qty_base' => $qtyBase,
            'list_price' => $lineData['list_price'],
            'unit_price' => $lineData['unit_price'],
            'discount_amount' => $discountAmount,
            'discount_pct' => $lineData['discount_pct'] ?? '0',
            'discount_source' => $lineData['discount_source'] ?? null,
            'approved_by' => $lineData['approved_by'] ?? null,
            'tax_code_id' => $lineData['tax_code_id'] ?? null,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
            'unit_cost' => bccomp($qtyBase, '0', 4) > 0 ? bcdiv($lineCost, $qtyBase, 4) : '0',
            'line_cost' => $lineCost,
            'is_bonus' => $isBonus,
            'fefo_overridden' => (bool) $overrideBatch,
            'override_reason' => $overrideBatch ? ($lineData['override_reason'] ?? null) : null,
        ]);

        foreach ($allocations as $a) {
            SaleLineBatchAllocation::create([
                'sale_line_id' => $saleLine->id,
                'batch_id' => $a->batchId,
                'store_id' => $store->id,
                'qty_base' => $a->qty,
                'unit_cost' => $a->unitCost,
                'is_bonus' => $isBonus,
                'fefo_overridden' => (bool) $overrideBatch,
                'override_reason' => $overrideBatch ? ($lineData['override_reason'] ?? null) : null,
            ]);

            $this->ledger->post([
                'organisation_id' => $sale->organisation_id,
                'txn_type' => $isBonus ? 'SALE_BONUS' : 'SALE',
                'product_id' => $product->id,
                'batch_id' => $a->batchId,
                'store_id' => $store->id,
                'qty_base' => bcmul($a->qty, '-1', 4),
                'unit_cost' => $a->unitCost,
                'source_doc_type' => 'sale',
                'source_doc_id' => $sale->id,
                'source_doc_line_id' => $saleLine->id,
                'user_id' => $sale->user_id,
                'branch_id' => $sale->branch_id,
            ]);
        }

        if ($overrideBatch) {
            // Part 7.4 — a silent override is no FEFO at all.
            AuditLog::record('FEFO_OVERRIDDEN', 'sale_line', $saleLine->id, [
                'user_id' => $sale->user_id,
                'branch_id' => $sale->branch_id,
                'reference' => $sale->doc_number,
                'reason' => $lineData['override_reason'] ?? null,
                'after_json' => ['batch_id' => $overrideBatch, 'qty_base' => $qtyBase],
            ]);
        }

        return [['list_amount' => $listAmount, 'discount_amount' => $discountAmount, 'tax_amount' => $taxAmount], $lineCost];
    }

    /**
     * @return array{0: array<string, string>, 1: string} [paidByMethod, creditAmount]
     */
    private function postPayments(Sale $sale, string $grandTotal, array $payments): array
    {
        $paidByMethod = [];
        $totalPaid = '0.0000';

        foreach ($payments as $p) {
            $payment = Payment::create([
                'organisation_id' => $sale->organisation_id,
                'branch_id' => $sale->branch_id,
                'customer_id' => $sale->customer_id,
                'method' => $p['method'],
                'reference' => $p['reference'] ?? null,
                'amount' => $p['amount'],
                'received_by' => $sale->user_id,
                'received_at' => now(),
            ]);

            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'allocated_to_type' => 'sale',
                'allocated_to_id' => $sale->id,
                'amount' => $p['amount'],
            ]);

            $paidByMethod[$p['method']] = bcadd($paidByMethod[$p['method']] ?? '0', $p['amount'], 4);
            $totalPaid = bcadd($totalPaid, $p['amount'], 4);
        }

        $creditAmount = bcsub($grandTotal, $totalPaid, 4);
        if (bccomp($creditAmount, '0', 4) < 0) {
            $creditAmount = '0.0000'; // overpayment isn't this method's concern (change/refund handled at the till)
        }

        if (bccomp($creditAmount, '0', 4) > 0 && ! $sale->customer_id) {
            throw new PaymentMismatchException($grandTotal, $totalPaid);
        }

        return [$paidByMethod, $creditAmount];
    }

    private function postCredit(Sale $sale, string $creditAmount): void
    {
        if (! $sale->customer_id) {
            throw new \RuntimeException('A credit balance requires a customer on the sale.');
        }

        $customer = Customer::findOrFail($sale->customer_id);
        $credit = CustomerCredit::query()->where('customer_id', $customer->id)->lockForUpdate()->first()
            ?? CustomerCredit::create(['customer_id' => $customer->id, 'credit_limit' => '0']);

        // Part 10.4 / decision 5 — a customer on hold gets no new credit at all.
        if ($credit->on_hold) {
            throw new CreditHoldException("Customer {$customer->code} is on credit hold: {$credit->hold_reason}");
        }

        // Part 10.4 — exposure counts open (undelivered) sales orders as well
        // as outstanding invoices; the cart on top of that must fit the limit.
        $exposure = $credit->exposure();
        $projected = bcadd($exposure, $creditAmount, 4);
        if (bccomp($projected, (string) $credit->credit_limit, 4) > 0) {
            throw new CreditLimitExceededException(
                (string) $credit->credit_limit,
                $exposure,
                bcsub($projected, (string) $credit->credit_limit, 4),
            );
        }

        $newBalance = bcadd((string) $credit->current_balance, $creditAmount, 4);

        AccountsReceivable::create([
            'organisation_id' => $sale->organisation_id,
            'customer_id' => $customer->id,
            'txn_type' => 'INVOICE',
            'sale_id' => $sale->id,
            'amount' => $creditAmount,
            'balance_after' => $newBalance,
            'branch_id' => $sale->branch_id,
            'created_at' => now(),
        ]);

        $credit->update(['current_balance' => $newBalance]);
    }
}
