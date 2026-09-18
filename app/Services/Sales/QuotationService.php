<?php

namespace App\Services\Sales;

use App\Models\NumberSequence;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\SalesOrder;
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
                'lines' => array_map(fn ($l) => [
                    'product_id' => $l['product_id'],
                    'uom_id' => $l['uom_id'],
                    'quantity' => $l['quantity'] ?? $l['qty'],
                    'requested_discount_pct' => $l['requested_discount_pct'] ?? null,
                    'requested_discount_reason' => $l['requested_discount_reason'] ?? null,
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
                QuotationLine::create([
                    'quotation_id' => $quotation->id,
                    'line_number' => $i + 1,
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
                ]);
            }

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
}
