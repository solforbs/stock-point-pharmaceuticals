<?php

namespace App\Services\Sales;

use App\Models\AccountsReceivable;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteLine;
use App\Models\DeliveryNoteLineBatchAllocation;
use App\Models\NumberSequence;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PickingList;
use App\Models\PickingListLine;
use App\Models\ProductUom;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleLineBatchAllocation;
use App\Models\SalesOrderLine;
use App\Models\StockBalance;
use App\Models\StockReservation;
use App\Services\Finance\JournalPoster;
use App\Services\Finance\SalesJournalMapper;
use App\Services\Inventory\StockLedgerService;
use App\Services\Tax\EtimsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvalidDeliveryNoteStatusException extends \RuntimeException {}

/**
 * Part 6.9 / V6 order-management flow, stages 4-5 — dispatch is the actual
 * stock + financial posting event (mirrors CheckoutService's atomic Sale
 * creation, but against already-reserved batches instead of a fresh FEFO
 * run, since that decision was already made and locked in at order-confirm
 * time). Delivery confirmation afterwards is proof-of-receipt only — it
 * never touches stock or the ledger again; one document, one posting event.
 *
 * Credit is checked once, at order-confirm (SalesOrderService::confirm) —
 * not re-checked here. Blocking a dispatch after goods are already picked
 * against a reservation would be operationally disruptive, not protective.
 */
class DispatchService
{
    public function __construct(
        private readonly StockLedgerService $ledger,
        private readonly JournalPoster $journalPoster,
        private readonly SalesJournalMapper $journalMapper,
    ) {}

    /**
     * @param  array{idempotency_key: string}  $meta
     */
    public function createFromPickingList(PickingList $pickingList, array $meta): DeliveryNote
    {
        if ($pickingList->status !== 'COMPLETED') {
            throw new InvalidDeliveryNoteStatusException("Picking list {$pickingList->doc_number} is {$pickingList->status}, not COMPLETED.");
        }

        if ($existing = DeliveryNote::where('idempotency_key', $meta['idempotency_key'])->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($pickingList, $meta) {
            $order = $pickingList->salesOrder;

            $note = DeliveryNote::create([
                'organisation_id' => $order->organisation_id,
                'branch_id' => $order->branch_id,
                'store_id' => $order->store_id,
                'sales_order_id' => $order->id,
                'picking_list_id' => $pickingList->id,
                'customer_id' => $order->customer_id,
                'doc_number' => NumberSequence::next($order->organisation_id, 'DELIVERY_NOTE', $order->branch_id, 'DN'),
                'status' => 'DRAFT',
                'idempotency_key' => $meta['idempotency_key'],
            ]);

            $grouped = $pickingList->lines()->whereIn('status', ['PICKED', 'SHORT'])->get()->groupBy('sales_order_line_id');

            $lineNumber = 0;
            foreach ($grouped as $pickLines) {
                $dispatchQtyBase = $pickLines->reduce(fn ($c, $l) => bcadd($c, (string) $l->qty_picked_base, 4), '0.0000');
                if (bccomp($dispatchQtyBase, '0', 4) <= 0) {
                    continue;
                }

                $lineNumber++;
                $this->buildDeliveryNoteLine($note, $lineNumber, $pickLines->first()->salesOrderLine, $dispatchQtyBase, $pickLines);
            }

            return $note->fresh(['lines.batchAllocations']);
        });
    }

    /**
     * @param  array{
     *     user_id: int, vehicle_reg?: ?string, driver_name?: ?string, driver_phone?: ?string,
     *     payments?: list<array{method: string, reference?: ?string, amount: string}>,
     * }  $meta
     */
    public function dispatch(DeliveryNote $note, array $meta): DeliveryNote
    {
        if ($note->status !== 'DRAFT') {
            throw new InvalidDeliveryNoteStatusException("Delivery note {$note->doc_number} is {$note->status}, not DRAFT.");
        }

        return DB::transaction(function () use ($note, $meta) {
            $saleIdempotencyKey = 'DN:'.$note->id;

            if ($existingSale = Sale::where('idempotency_key', $saleIdempotencyKey)->first()) {
                $note->update([
                    'status' => 'DISPATCHED',
                    'dispatched_at' => $note->dispatched_at ?? now(),
                    'sale_id' => $existingSale->id,
                ]);

                return $note->fresh();
            }

            $order = $note->salesOrder;
            $lines = $note->lines()->with(['batchAllocations', 'salesOrderLine'])->get();

            $sale = Sale::create([
                'organisation_id' => $order->organisation_id,
                'branch_id' => $order->branch_id,
                'store_id' => $order->store_id,
                'sale_mode' => $order->sale_mode,
                'sub_type' => 'WHOLESALE_ORDER',
                'customer_id' => $order->customer_id,
                'quote_id' => $order->quotation_id,
                'user_id' => $meta['user_id'],
                'doc_number' => NumberSequence::next($order->organisation_id, 'SALE', $order->branch_id, 'INV'),
                'status' => 'POSTED',
                'subtotal' => '0', 'discount_total' => '0', 'tax_total' => '0', 'grand_total' => '0', 'cost_total' => '0',
                'idempotency_key' => $saleIdempotencyKey,
                'posted_at' => now(),
            ]);

            $subtotal = '0.0000';
            $discountTotal = '0.0000';
            $taxTotal = '0.0000';
            $costTotal = '0.0000';

            foreach ($lines as $i => $dnLine) {
                $saleLine = SaleLine::create([
                    'sale_id' => $sale->id,
                    'line_number' => $i + 1,
                    'product_id' => $dnLine->product_id,
                    'uom_id' => $dnLine->uom_id,
                    'qty' => $dnLine->qty,
                    'qty_base' => $dnLine->qty_base,
                    'list_price' => $dnLine->list_price,
                    'unit_price' => $dnLine->unit_price,
                    'discount_amount' => $dnLine->discount_amount,
                    'discount_pct' => $dnLine->discount_pct,
                    'discount_source' => $dnLine->discount_source,
                    'tax_code_id' => $dnLine->tax_code_id,
                    'tax_rate' => $dnLine->tax_rate,
                    'tax_amount' => $dnLine->tax_amount,
                    'line_total' => $dnLine->line_total,
                    'unit_cost' => $dnLine->unit_cost,
                    'line_cost' => $dnLine->line_cost,
                    'is_bonus' => false,
                ]);

                foreach ($dnLine->batchAllocations as $alloc) {
                    SaleLineBatchAllocation::create([
                        'sale_line_id' => $saleLine->id,
                        'batch_id' => $alloc->batch_id,
                        'store_id' => $alloc->store_id,
                        'qty_base' => $alloc->qty_base,
                        'unit_cost' => $alloc->unit_cost,
                        'is_bonus' => false,
                    ]);

                    $this->ledger->post([
                        'organisation_id' => $order->organisation_id,
                        'txn_type' => 'SALE',
                        'product_id' => $dnLine->product_id,
                        'batch_id' => $alloc->batch_id,
                        'store_id' => $alloc->store_id,
                        'qty_base' => bcmul((string) $alloc->qty_base, '-1', 4),
                        'unit_cost' => (string) $alloc->unit_cost,
                        'source_doc_type' => 'delivery_note',
                        'source_doc_id' => $note->id,
                        'source_doc_line_id' => $saleLine->id,
                        'user_id' => $meta['user_id'],
                        'branch_id' => $order->branch_id,
                    ]);

                    $this->consumeReservation((string) $dnLine->sales_order_line_id, (string) $alloc->batch_id, (string) $alloc->store_id, (string) $alloc->qty_base);
                }

                $subtotal = bcadd($subtotal, bcmul((string) $dnLine->list_price, (string) $dnLine->qty, 4), 4);
                $discountTotal = bcadd($discountTotal, (string) $dnLine->discount_amount, 4);
                $taxTotal = bcadd($taxTotal, (string) $dnLine->tax_amount, 4);
                $costTotal = bcadd($costTotal, (string) $dnLine->line_cost, 4);

                $orderLine = $dnLine->salesOrderLine;
                $orderLine->update([
                    'qty_dispatched_base' => bcadd((string) $orderLine->qty_dispatched_base, (string) $dnLine->qty_base, 4),
                ]);
            }

            $grandTotal = bcadd(bcsub($subtotal, $discountTotal, 4), $taxTotal, 4);
            $sale->update([
                'subtotal' => $subtotal, 'discount_total' => $discountTotal,
                'tax_total' => $taxTotal, 'grand_total' => $grandTotal, 'cost_total' => $costTotal,
            ]);

            [$paidByMethod, $creditAmount] = $this->postPayments($sale, $grandTotal, $meta['payments'] ?? []);

            if (bccomp($creditAmount, '0', 4) > 0) {
                $this->postCredit($sale, $creditAmount);
            }

            $journalLines = $this->journalMapper->buildLines($sale->fresh(['lines']), $paidByMethod, $creditAmount);
            $this->journalPoster->post([
                'organisation_id' => $order->organisation_id,
                'branch_id' => $order->branch_id,
                'entry_date' => now(),
                'source_doc_type' => 'delivery_note',
                'source_doc_id' => $note->id,
                'narration' => "Dispatch {$note->doc_number} (order {$order->doc_number})",
                'posted_by' => $meta['user_id'],
            ], $journalLines);

            $note->update([
                'status' => 'DISPATCHED',
                'dispatched_at' => now(),
                'sale_id' => $sale->id,
                'vehicle_reg' => $meta['vehicle_reg'] ?? null,
                'driver_name' => $meta['driver_name'] ?? null,
                'driver_phone' => $meta['driver_phone'] ?? null,
            ]);

            $allDispatched = $order->lines()->get()->every(
                fn ($l) => bccomp((string) $l->qty_dispatched_base, (string) $l->qty_base, 4) >= 0
            );
            $order->update(['status' => $allDispatched ? 'FULFILLED' : 'PARTIALLY_FULFILLED']);

            AuditLog::record('SALES_ORDER_DISPATCHED', 'sale', $sale->id, [
                'reference' => $sale->doc_number,
                'after_json' => ['grand_total' => $grandTotal, 'delivery_note' => $note->doc_number],
            ]);

            // Part 13.6 — the dispatch invoice is fiscalised after commit.
            app(EtimsService::class)->queue($sale);

            return $note->fresh(['lines.batchAllocations']);
        });
    }

    public function confirmDelivery(DeliveryNote $note, string $receivedByName): DeliveryNote
    {
        if ($note->status !== 'DISPATCHED') {
            throw new InvalidDeliveryNoteStatusException("Delivery note {$note->doc_number} is {$note->status}, not DISPATCHED.");
        }

        $note->update(['status' => 'DELIVERED', 'delivered_at' => now(), 'received_by_name' => $receivedByName]);

        AuditLog::record('DELIVERY_CONFIRMED', 'delivery_note', $note->id, [
            'reference' => $note->doc_number,
        ]);

        return $note->fresh();
    }

    /**
     * @param  Collection<int, PickingListLine>  $pickLines
     */
    private function buildDeliveryNoteLine(DeliveryNote $note, int $lineNumber, SalesOrderLine $orderLine, string $dispatchQtyBase, Collection $pickLines): void
    {
        $ratio = bccomp((string) $orderLine->qty_base, '0', 4) > 0
            ? bcdiv($dispatchQtyBase, (string) $orderLine->qty_base, 6)
            : '0';

        $product = $orderLine->product;
        $uom = ProductUom::where('product_id', $orderLine->product_id)->where('uom_id', $orderLine->uom_id)->firstOrFail();
        $qty = $product->fromBase($uom, $dispatchQtyBase);

        $discountAmount = bcmul((string) $orderLine->discount_amount, $ratio, 4);
        $taxAmount = bcmul((string) $orderLine->tax_amount, $ratio, 4);
        $listAmount = bcmul((string) $orderLine->list_price, $qty, 4);
        $netAmount = bcsub($listAmount, $discountAmount, 4);
        $lineTotal = bcadd($netAmount, $taxAmount, 4);

        $lineCost = $pickLines->reduce(
            fn ($c, $l) => bcadd($c, bcmul((string) $l->qty_picked_base, (string) $l->unit_cost, 4), 4),
            '0.0000'
        );
        $unitCost = bccomp($dispatchQtyBase, '0', 4) > 0 ? bcdiv($lineCost, $dispatchQtyBase, 4) : '0.0000';

        $deliveryLine = DeliveryNoteLine::create([
            'delivery_note_id' => $note->id,
            'sales_order_line_id' => $orderLine->id,
            'line_number' => $lineNumber,
            'product_id' => $orderLine->product_id,
            'uom_id' => $orderLine->uom_id,
            'qty' => $qty,
            'qty_base' => $dispatchQtyBase,
            'list_price' => $orderLine->list_price,
            'unit_price' => $orderLine->unit_price,
            'discount_amount' => $discountAmount,
            'discount_pct' => $orderLine->discount_pct,
            'discount_source' => $orderLine->discount_source,
            'tax_code_id' => $orderLine->tax_code_id,
            'tax_rate' => $orderLine->tax_rate,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
            'unit_cost' => $unitCost,
            'line_cost' => $lineCost,
        ]);

        foreach ($pickLines as $pickLine) {
            if (bccomp((string) $pickLine->qty_picked_base, '0', 4) <= 0) {
                continue;
            }

            DeliveryNoteLineBatchAllocation::create([
                'delivery_note_line_id' => $deliveryLine->id,
                'batch_id' => $pickLine->batch_id,
                'store_id' => $pickLine->store_id,
                'qty_base' => $pickLine->qty_picked_base,
                'unit_cost' => $pickLine->unit_cost,
            ]);
        }
    }

    private function consumeReservation(string $salesOrderLineId, string $batchId, string $storeId, string $qty): void
    {
        $balance = StockBalance::query()
            ->where('batch_id', $batchId)->where('store_id', $storeId)
            ->lockForUpdate()->firstOrFail();

        $balance->qty_reserved = bcsub((string) $balance->qty_reserved, $qty, 4);
        $balance->save();

        $reservation = StockReservation::where('source_doc_type', 'sales_order_line')
            ->where('source_doc_id', $salesOrderLineId)
            ->where('batch_id', $batchId)
            ->where('status', 'ACTIVE')
            ->first();

        if (! $reservation) {
            return;
        }

        $remaining = bcsub((string) $reservation->qty_base, $qty, 4);
        $reservation->update(bccomp($remaining, '0', 4) <= 0
            ? ['status' => 'CONSUMED', 'qty_base' => '0']
            : ['qty_base' => $remaining]);
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
            $creditAmount = '0.0000';
        }

        return [$paidByMethod, $creditAmount];
    }

    private function postCredit(Sale $sale, string $creditAmount): void
    {
        if (! $sale->customer_id) {
            throw new \RuntimeException('A credit balance requires a customer on the sale.');
        }

        $customer = Customer::findOrFail($sale->customer_id);
        $credit = CustomerCredit::firstOrCreate(['customer_id' => $customer->id], ['credit_limit' => '0']);
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
