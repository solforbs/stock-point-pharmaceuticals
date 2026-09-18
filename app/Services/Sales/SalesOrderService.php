<?php

namespace App\Services\Sales;

use App\Models\AuditLog;
use App\Models\CustomerCredit;
use App\Models\NumberSequence;
use App\Models\Product;
use App\Models\ProductUom;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockBalance;
use App\Models\StockReservation;
use App\Models\Store;
use App\Services\Inventory\FefoAllocator;
use Illuminate\Support\Facades\DB;

class InvalidSalesOrderStatusException extends \RuntimeException {}

class OrderOnCreditHoldException extends \RuntimeException {}

class OrderCreditLimitExceededException extends \RuntimeException
{
    public function __construct(public readonly string $limit, public readonly string $exposure, public readonly string $shortfall)
    {
        parent::__construct(bccomp($limit, '0', 4) === 0
            ? 'This customer has no credit account (limit 0.00). Choose cash on delivery, set a credit limit in Credit Control, or ask a manager to override.'
            : "This order would take the customer {$shortfall} over their credit limit of {$limit} (already owed or on order: {$exposure}). Choose cash on delivery, reduce the order, or ask a manager to override.");
    }
}

/**
 * Part 6.9 / V6 order-management flow, stage 2 — confirming an order is
 * where stock gets FEFO-reserved (batch-committed up front, not left for
 * picking to decide) and credit gets checked. No ledger or journal entry
 * happens here: that's DispatchService's job, at the point stock actually
 * leaves the building.
 */
class SalesOrderService
{
    public function __construct(private readonly FefoAllocator $fefo) {}

    /**
     * @param  array{
     *     organisation_id: string, branch_id: string, store_id: string, sale_mode?: string,
     *     sub_type?: ?string, customer_id?: ?string, quotation_id?: ?string, user_id: int,
     *     required_date?: ?string, idempotency_key: string, payment_terms?: ?string,
     *     lines: list<array{
     *         product_id: string, uom_id: string, qty: string, list_price: string, unit_price: string,
     *         discount_amount?: string, discount_pct?: string, discount_source?: ?string,
     *         tax_code_id?: ?string, tax_rate?: string,
     *     }>,
     * }  $data
     */
    public function create(array $data): SalesOrder
    {
        if ($existing = SalesOrder::where('idempotency_key', $data['idempotency_key'])->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($data) {
            $order = SalesOrder::create([
                'organisation_id' => $data['organisation_id'],
                'branch_id' => $data['branch_id'],
                'store_id' => $data['store_id'],
                'sale_mode' => $data['sale_mode'] ?? 'WHOLESALE',
                'sub_type' => $data['sub_type'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'quotation_id' => $data['quotation_id'] ?? null,
                'user_id' => $data['user_id'],
                'doc_number' => NumberSequence::next($data['organisation_id'], 'SALES_ORDER', $data['branch_id'], 'SO'),
                'status' => 'DRAFT',
                'required_date' => $data['required_date'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? self::defaultPaymentTerms($data['customer_id'] ?? null),
                'idempotency_key' => $data['idempotency_key'],
                'subtotal' => '0', 'discount_total' => '0', 'tax_total' => '0', 'grand_total' => '0', 'cost_total' => '0',
            ]);

            $subtotal = '0.0000';
            $discountTotal = '0.0000';
            $taxTotal = '0.0000';

            foreach ($data['lines'] as $i => $lineData) {
                $totals = $this->buildLine($order, $i + 1, $lineData);
                $subtotal = bcadd($subtotal, $totals['list_amount'], 4);
                $discountTotal = bcadd($discountTotal, $totals['discount_amount'], 4);
                $taxTotal = bcadd($taxTotal, $totals['tax_amount'], 4);
            }

            $grandTotal = bcadd(bcsub($subtotal, $discountTotal, 4), $taxTotal, 4);
            $order->update([
                'subtotal' => $subtotal, 'discount_total' => $discountTotal,
                'tax_total' => $taxTotal, 'grand_total' => $grandTotal,
            ]);

            return $order->fresh(['lines']);
        });
    }

    /**
     * @param  array{user_id: int, required_date?: ?string, idempotency_key: string, payment_terms?: ?string}  $meta
     */
    public function createFromQuotation(Quotation $quotation, array $meta): SalesOrder
    {
        if (! in_array($quotation->status, ['DRAFT', 'SENT'], true)) {
            throw new InvalidSalesOrderStatusException("Quotation {$quotation->doc_number} is {$quotation->status} and cannot be converted.");
        }

        return DB::transaction(function () use ($quotation, $meta) {
            $lines = $quotation->lines()->orderBy('line_number')->get()->map(fn (QuotationLine $l) => [
                'product_id' => $l->product_id,
                'uom_id' => $l->uom_id,
                'qty' => (string) $l->qty,
                'list_price' => (string) $l->list_price,
                'unit_price' => (string) $l->unit_price,
                'discount_amount' => (string) $l->discount_amount,
                'discount_pct' => (string) $l->discount_pct,
                'discount_source' => $l->discount_source,
                'tax_code_id' => $l->tax_code_id,
                'tax_rate' => (string) $l->tax_rate,
            ])->all();

            $order = $this->create([
                'organisation_id' => $quotation->organisation_id,
                'branch_id' => $quotation->branch_id,
                'store_id' => $quotation->store_id,
                'sale_mode' => $quotation->sale_mode,
                'sub_type' => 'QUOTATION_CONVERTED',
                'customer_id' => $quotation->customer_id,
                'quotation_id' => $quotation->id,
                'user_id' => $meta['user_id'],
                'required_date' => $meta['required_date'] ?? null,
                'payment_terms' => $meta['payment_terms'] ?? null,
                'idempotency_key' => $meta['idempotency_key'],
                'lines' => $lines,
            ]);

            $quotation->update(['status' => 'CONVERTED', 'converted_sales_order_id' => $order->id]);

            return $order;
        });
    }

    /**
     * Part 10.4 — an order on ACCOUNT must fit the customer's credit limit
     * unless $creditOverrideReason is given (the caller has checked the
     * customer.credit.override permission); CASH_ON_DELIVERY extends no
     * credit, so only a credit hold stops it.
     */
    public function confirm(SalesOrder $order, int $userId, ?string $creditOverrideReason = null): SalesOrder
    {
        if ($order->status !== 'DRAFT') {
            throw new InvalidSalesOrderStatusException("Sales order {$order->doc_number} is {$order->status}, not DRAFT.");
        }

        return DB::transaction(function () use ($order, $userId, $creditOverrideReason) {
            if ($order->customer_id) {
                $this->checkCredit($order, $userId, $creditOverrideReason);
            }

            $store = Store::findOrFail($order->store_id);
            $costTotal = '0.0000';

            foreach ($order->lines as $line) {
                $product = Product::findOrFail($line->product_id);
                $uom = ProductUom::where('product_id', $product->id)->where('uom_id', $line->uom_id)->firstOrFail();

                $allocations = $this->fefo->allocate(
                    $product, $store, (string) $line->qty_base,
                    packIntegrityRequired: $product->pack_integrity,
                    saleUomFactor: (int) $uom->factor_to_base,
                    minShelfLifeDays: 90,
                );

                $lineCost = '0.0000';
                foreach ($allocations as $a) {
                    StockReservation::create([
                        'organisation_id' => $order->organisation_id,
                        'product_id' => $product->id,
                        'batch_id' => $a->batchId,
                        'store_id' => $store->id,
                        'qty_base' => $a->qty,
                        'source_doc_type' => 'sales_order_line',
                        'source_doc_id' => $line->id,
                        'status' => 'ACTIVE',
                        'expires_at' => now()->addDays(7),
                        'created_by' => $userId,
                    ]);

                    $this->adjustReserved($product->id, $a->batchId, $store->id, $a->qty);
                    $lineCost = bcadd($lineCost, bcmul($a->qty, $a->unitCost, 4), 4);
                }

                $unitCost = bccomp((string) $line->qty_base, '0', 4) > 0
                    ? bcdiv($lineCost, (string) $line->qty_base, 4)
                    : '0.0000';

                $line->update(['qty_reserved_base' => $line->qty_base, 'unit_cost' => $unitCost]);
                $costTotal = bcadd($costTotal, $lineCost, 4);
            }

            $order->update(['status' => 'CONFIRMED', 'cost_total' => $costTotal]);

            AuditLog::record('SALES_ORDER_CONFIRMED', 'sales_order', $order->id, [
                'reference' => $order->doc_number,
            ]);

            return $order->fresh(['lines']);
        });
    }

    public function cancel(SalesOrder $order, string $reason, int $userId): SalesOrder
    {
        if (in_array($order->status, ['FULFILLED', 'CANCELLED'], true)) {
            throw new InvalidSalesOrderStatusException("Sales order {$order->doc_number} is {$order->status} and cannot be cancelled.");
        }

        return DB::transaction(function () use ($order, $reason, $userId) {
            $lineIds = $order->lines()->pluck('id');

            $reservations = StockReservation::where('source_doc_type', 'sales_order_line')
                ->whereIn('source_doc_id', $lineIds)
                ->where('status', 'ACTIVE')
                ->get();

            foreach ($reservations as $reservation) {
                $this->adjustReserved(
                    $reservation->product_id, $reservation->batch_id, $reservation->store_id,
                    bcmul((string) $reservation->qty_base, '-1', 4),
                );
                $reservation->update(['status' => 'RELEASED']);
            }

            $order->update([
                'status' => 'CANCELLED', 'cancelled_by' => $userId,
                'cancel_reason' => $reason, 'cancelled_at' => now(),
            ]);

            AuditLog::record('SALES_ORDER_CANCELLED', 'sales_order', $order->id, [
                'reference' => $order->doc_number,
                'reason' => $reason,
            ]);

            return $order->fresh();
        });
    }

    public static function defaultPaymentTerms(?string $customerId): string
    {
        if (! $customerId) {
            return 'CASH_ON_DELIVERY';
        }
        $limit = (string) (CustomerCredit::where('customer_id', $customerId)->value('credit_limit') ?? '0');

        return bccomp($limit, '0', 4) > 0 ? 'ACCOUNT' : 'CASH_ON_DELIVERY';
    }

    private function checkCredit(SalesOrder $order, int $userId, ?string $overrideReason): void
    {
        $credit = CustomerCredit::firstOrCreate(['customer_id' => $order->customer_id], ['credit_limit' => '0']);

        if ($credit->on_hold) {
            throw new OrderOnCreditHoldException("Customer is on credit hold: {$credit->hold_reason}");
        }

        if ($order->payment_terms === 'CASH_ON_DELIVERY') {
            return;
        }

        // Part 10.4 — other open orders already count against the limit; the
        // order being confirmed is still DRAFT, so it is not double-counted.
        $exposure = $credit->exposure();
        $projected = bcadd($exposure, (string) $order->grand_total, 4);
        if (bccomp($projected, (string) $credit->credit_limit, 4) <= 0) {
            return;
        }

        $shortfall = bcsub($projected, (string) $credit->credit_limit, 4);
        if ($overrideReason !== null && trim($overrideReason) !== '') {
            $order->update(['credit_override_by' => $userId, 'credit_override_reason' => $overrideReason, 'credit_override_at' => now()]);
            AuditLog::record('CREDIT_LIMIT_OVERRIDDEN', 'sales_order', $order->id, [
                'user_id' => $userId,
                'reference' => $order->doc_number,
                'reason' => $overrideReason,
                'after_json' => ['limit' => (string) $credit->credit_limit, 'exposure' => $exposure, 'order_total' => (string) $order->grand_total, 'shortfall' => $shortfall],
            ]);

            return;
        }

        throw new OrderCreditLimitExceededException((string) $credit->credit_limit, $exposure, $shortfall);
    }

    /**
     * @return array{list_amount: string, discount_amount: string, tax_amount: string}
     */
    private function buildLine(SalesOrder $order, int $lineNumber, array $lineData): array
    {
        $product = Product::findOrFail($lineData['product_id']);
        $uom = ProductUom::where('product_id', $product->id)->where('uom_id', $lineData['uom_id'])->firstOrFail();
        $qtyBase = $product->toBase($uom, $lineData['qty']);

        $listAmount = bcmul($lineData['list_price'], $lineData['qty'], 4);
        $discountAmount = $lineData['discount_amount'] ?? '0';
        $netAmount = bcsub($listAmount, $discountAmount, 4);
        $taxRate = $lineData['tax_rate'] ?? '0';
        $taxAmount = bcmul($netAmount, bcdiv($taxRate, '100', 6), 4);
        $lineTotal = bcadd($netAmount, $taxAmount, 4);

        SalesOrderLine::create([
            'sales_order_id' => $order->id,
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
            'tax_code_id' => $lineData['tax_code_id'] ?? null,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
        ]);

        return ['list_amount' => $listAmount, 'discount_amount' => $discountAmount, 'tax_amount' => $taxAmount];
    }

    private function adjustReserved(string $productId, string $batchId, string $storeId, string $deltaQty): void
    {
        $balance = StockBalance::query()
            ->where('product_id', $productId)->where('batch_id', $batchId)->where('store_id', $storeId)
            ->lockForUpdate()->firstOrFail();

        $balance->qty_reserved = bcadd((string) $balance->qty_reserved, $deltaQty, 4);
        $balance->save();
    }
}
