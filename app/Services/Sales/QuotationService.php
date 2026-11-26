<?php

namespace App\Services\Sales;

use App\Models\AuditLog;
use App\Models\NumberSequence;
use App\Models\Product;
use App\Models\ProductUom;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\SalesOrder;
use App\Services\Pricing\Money;
use App\Services\Pricing\PriceQuoteService;
use Illuminate\Support\Facades\DB;

class InvalidQuotationStatusException extends \RuntimeException {}

/**
 * Part 6.9 / V6 order-management flow, stage 1 — a quotation is a persisted
 * seven-step price quote: it moves no stock and posts no journal. Its lines
 * are exactly what the customer was offered, so a later acceptance is
 * honoured unchanged (subject to the credit check at order confirmation).
 */
class QuotationService
{
    public function __construct(
        private readonly PriceQuoteService $pricing,
        private readonly SalesOrderService $salesOrders,
    ) {}

    /**
     * @param  array{
     *     organisation_id: string, branch_id: string, store_id: string, sale_mode?: string,
     *     customer_id?: ?string, user_id: int, valid_until: string, notes?: ?string, header_discount?: ?string,
     *     lines: list<array{
     *         product_id: string, uom_id: string, qty?: string, quantity?: string,
     *         requested_discount_pct?: ?string, requested_discount_reason?: ?string,
     *         selling_price?: ?string,
     *     }>,
     * }  $data
     */
    public function create(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            $saleMode = $data['sale_mode'] ?? 'WHOLESALE';

            $quote = $this->pricing->quote([
                'organisation_id' => $data['organisation_id'],
                'branch_id' => $data['branch_id'],
                'store_id' => $data['store_id'],
                'sale_mode' => $saleMode,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $data['user_id'],
                'header_discount' => $data['header_discount'] ?? null,
                'price_is_free' => true,
                'lines' => array_map(fn ($l) => [
                    'product_id' => $l['product_id'],
                    'uom_id' => $l['uom_id'],
                    'quantity' => $l['quantity'] ?? $l['qty'],
                    'requested_discount_pct' => $l['requested_discount_pct'] ?? null,
                    'requested_discount_reason' => $l['requested_discount_reason'] ?? null,
                    'selling_price' => $l['selling_price'] ?? null,
                ], $data['lines']),
            ]);

            $quotation = Quotation::create([
                'organisation_id' => $data['organisation_id'],
                'branch_id' => $data['branch_id'],
                'store_id' => $data['store_id'],
                'sale_mode' => $saleMode,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $data['user_id'],
                'doc_number' => NumberSequence::next($data['organisation_id'], 'QUOTE', $data['branch_id'], 'QT'),
                'status' => 'DRAFT',
                'valid_until' => $data['valid_until'],
                'notes' => $data['notes'] ?? null,
                'subtotal' => $quote['totals']['subtotal'],
                'discount_total' => $quote['totals']['discount'],
                'tax_total' => $quote['totals']['tax'],
                'grand_total' => $quote['totals']['grand_total'],
            ]);

            foreach ($quote['lines'] as $i => $line) {
                QuotationLine::create($this->pricedLine($line) + ['quotation_id' => $quotation->id, 'line_number' => $i + 1]);
            }

            return $quotation->fresh(['lines']);
        });
    }

    /**
     * Replaces the lines of a quotation that has not yet become an order. A
     * line sent back with its id and the same product, unit and price keeps
     * what the customer was quoted, scaled to the new quantity; a new line, or
     * one whose unit, price or discount changed, is priced afresh.
     *
     * @param  array{
     *     user_id: int, valid_until: string, notes?: ?string,
     *     lines: list<array{
     *         id?: ?string, product_id: string, uom_id: string, quantity: string,
     *         requested_discount_pct?: ?string, requested_discount_reason?: ?string,
     *         selling_price?: ?string,
     *     }>,
     * }  $data
     */
    public function update(Quotation $quotation, array $data): Quotation
    {
        return DB::transaction(function () use ($quotation, $data) {
            $quotation = Quotation::whereKey($quotation->id)->lockForUpdate()->firstOrFail();
            if (! in_array($quotation->status, ['DRAFT', 'SENT'], true)) {
                throw new InvalidQuotationStatusException("Quotation {$quotation->doc_number} is {$quotation->status} and cannot be edited.");
            }

            $existing = $quotation->lines()->orderBy('line_number')->get()->keyBy('id');
            $before = $this->snapshot($quotation);

            $lines = [];
            $toPrice = [];
            foreach (array_values($data['lines']) as $i => $input) {
                $old = empty($input['id']) ? null : $existing->get($input['id']);
                if (! empty($input['id']) && ! $old) {
                    throw new \InvalidArgumentException("Line {$input['id']} does not belong to quotation {$quotation->doc_number}.");
                }

                $sameItem = $old && $old->product_id === $input['product_id'] && $old->uom_id === $input['uom_id'];
                if ($sameItem && $this->keepsQuotedPrice($old, $input)) {
                    $lines[$i] = $this->keptLine($old, (string) $input['quantity']);

                    continue;
                }

                // A discount added to a quoted line comes off the price the customer saw.
                $sellingPrice = $input['selling_price'] ?? null;
                if ($sameItem && empty($sellingPrice)) {
                    $sellingPrice = Money::round(bcmul((string) $old->list_price, bcadd('1', bcdiv((string) $old->tax_rate, '100', 6), 6), 6), 2);
                }

                $toPrice[$i] = [
                    'product_id' => $input['product_id'],
                    'uom_id' => $input['uom_id'],
                    'quantity' => (string) $input['quantity'],
                    'requested_discount_pct' => $input['requested_discount_pct'] ?? null,
                    'requested_discount_reason' => $input['requested_discount_reason'] ?? null,
                    'selling_price' => $sellingPrice,
                ];
            }

            if ($toPrice !== []) {
                $quote = $this->pricing->quote([
                    'organisation_id' => $quotation->organisation_id,
                    'branch_id' => $quotation->branch_id,
                    'store_id' => $quotation->store_id,
                    'sale_mode' => $quotation->sale_mode,
                    'customer_id' => $quotation->customer_id,
                    'user_id' => $data['user_id'],
                    'price_is_free' => true,
                    'lines' => array_values($toPrice),
                ]);

                foreach (array_keys($toPrice) as $position => $i) {
                    $lines[$i] = $this->pricedLine($quote['lines'][$position]);
                }
            }
            ksort($lines);

            $quotation->lines()->delete();
            $subtotal = $discountTotal = $taxTotal = $grandTotal = '0.0000';
            foreach ($lines as $i => $line) {
                QuotationLine::create($line + ['quotation_id' => $quotation->id, 'line_number' => $i + 1]);

                $lineSubtotal = bcadd(bcsub($line['line_total'], $line['tax_amount'], 4), $line['discount_amount'], 4);
                $subtotal = bcadd($subtotal, $lineSubtotal, 4);
                $discountTotal = bcadd($discountTotal, $line['discount_amount'], 4);
                $taxTotal = bcadd($taxTotal, $line['tax_amount'], 4);
                $grandTotal = bcadd($grandTotal, $line['line_total'], 4);
            }

            $quotation->update([
                'valid_until' => $data['valid_until'],
                'notes' => $data['notes'] ?? null,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
            ]);

            AuditLog::record('QUOTATION_UPDATED', 'quotation', $quotation->id, [
                'user_id' => $data['user_id'],
                'reference' => $quotation->doc_number,
                'before_json' => $before,
                'after_json' => $this->snapshot($quotation),
            ]);

            return $quotation->fresh(['lines']);
        });
    }

    public function send(Quotation $quotation): Quotation
    {
        if ($quotation->status !== 'DRAFT') {
            throw new InvalidQuotationStatusException("Quotation {$quotation->doc_number} is {$quotation->status}, not DRAFT.");
        }

        $quotation->update(['status' => 'SENT']);

        return $quotation->fresh();
    }

    public function cancel(Quotation $quotation): Quotation
    {
        if (in_array($quotation->status, ['CONVERTED', 'CANCELLED'], true)) {
            throw new InvalidQuotationStatusException("Quotation {$quotation->doc_number} is {$quotation->status} and cannot be cancelled.");
        }

        $quotation->update(['status' => 'CANCELLED']);

        return $quotation->fresh();
    }

    /**
     * @param  array{user_id: int, required_date?: ?string, idempotency_key: string}  $meta
     */
    public function convertToSalesOrder(Quotation $quotation, array $meta): SalesOrder
    {
        if ($quotation->valid_until && $quotation->valid_until->isPast()) {
            throw new InvalidQuotationStatusException("Quotation {$quotation->doc_number} expired on {$quotation->valid_until->toDateString()}.");
        }

        return $this->salesOrders->createFromQuotation($quotation, $meta);
    }

    /**
     * No discount asked for, and the price either left alone or sent back as
     * the (VAT inclusive) unit price already on the quotation.
     *
     * @param  array<string, mixed>  $input
     */
    private function keepsQuotedPrice(QuotationLine $line, array $input): bool
    {
        if (bccomp((string) ($input['requested_discount_pct'] ?? '0'), '0', 4) > 0) {
            return false;
        }
        if (empty($input['selling_price'])) {
            return true;
        }
        $quoted = Money::round(bcdiv((string) $line->line_total, (string) $line->qty, 8), 2);

        return bccomp(Money::round((string) $input['selling_price'], 2), $quoted, 4) === 0;
    }

    /**
     * The quoted line at a new quantity: the same unit price, with the
     * discount and total in proportion and VAT as the remainder.
     *
     * @return array<string, mixed>
     */
    private function keptLine(QuotationLine $line, string $quantity): array
    {
        $kept = $line->only([
            'product_id', 'uom_id', 'qty', 'qty_base', 'list_price', 'unit_price', 'discount_amount',
            'discount_pct', 'discount_source', 'tax_code_id', 'tax_rate', 'tax_amount', 'line_total',
        ]);
        $quantity = bcadd($quantity, '0', 4);
        if (bccomp($quantity, (string) $line->qty, 4) === 0) {
            return $kept;
        }

        $product = Product::findOrFail($line->product_id);
        if ($product->is_discrete && bccomp($quantity, bcdiv($quantity, '1', 0), 4) !== 0) {
            throw new \InvalidArgumentException("{$product->code} is sold in whole units only (Part 5.5); {$quantity} is not a whole number.");
        }
        $uom = ProductUom::where('product_id', $product->id)->where('uom_id', $line->uom_id)->firstOrFail();

        $scale = fn (string $amount) => Money::round(bcdiv(bcmul($amount, $quantity, 8), (string) $line->qty, 8), 2);
        $discount = $scale((string) $line->discount_amount);
        $total = $scale((string) $line->line_total);
        $net = bcsub(Money::round(bcmul((string) $line->list_price, $quantity, 8), 2), $discount, 4);

        return array_merge($kept, [
            'qty' => $quantity,
            'qty_base' => $product->toBase($uom, $quantity),
            'discount_amount' => $discount,
            'tax_amount' => bcsub($total, $net, 4),
            'line_total' => $total,
        ]);
    }

    /**
     * @param  array<string, mixed>  $line  one line of a price quote
     * @return array<string, mixed>
     */
    private function pricedLine(array $line): array
    {
        return [
            'product_id' => $line['product_id'],
            'uom_id' => $line['uom_id'],
            'qty' => $line['quantity'],
            'qty_base' => $line['qty_base'],
            'list_price' => $line['break_price'],
            'unit_price' => $line['unit_price'],
            'discount_amount' => $line['discount_amount'],
            'discount_pct' => $line['discount_pct'],
            'discount_source' => $line['discount_source'] === 'NONE' ? $line['price_source'] : $line['discount_source'],
            'tax_code_id' => $line['tax_code_id'],
            'tax_rate' => $line['tax_rate'],
            'tax_amount' => $line['tax_amount'],
            'line_total' => $line['line_total'],
        ];
    }

    /**
     * What the audit trail keeps of a quotation before and after an edit.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Quotation $quotation): array
    {
        return [
            'valid_until' => $quotation->valid_until?->toDateString(),
            'grand_total' => (string) $quotation->grand_total,
            'lines' => $quotation->lines()->orderBy('line_number')->get()->map(fn (QuotationLine $l) => [
                'product_id' => $l->product_id,
                'uom_id' => $l->uom_id,
                'qty' => (string) $l->qty,
                'unit_price' => (string) $l->unit_price,
                'line_total' => (string) $l->line_total,
            ])->all(),
        ];
    }
}
