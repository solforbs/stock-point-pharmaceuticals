<?php

namespace App\Services\Procurement;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\NumberSequence;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\Rfq;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class InvalidRequisitionStatusException extends \RuntimeException {}

/**
 * Part 9.1 — the chain starts here: REQUISITION → approval → supplier
 * selection → PURCHASE ORDER. Quantities are held in base units; the
 * purchase UOM and price are chosen when the requisition becomes an order.
 */
class RequisitionService
{
    /**
     * @param  array{branch_id: string, user_id: int, needed_by?: ?string, notes?: ?string, lines: list<array{product_id: string, qty_base: string, notes?: ?string}>}  $data
     */
    public function create(array $data): Requisition
    {
        return DB::transaction(function () use ($data) {
            $organisationId = (string) Branch::whereKey($data['branch_id'])->value('organisation_id');

            $requisition = Requisition::create([
                'doc_number' => NumberSequence::next($organisationId, 'REQUISITION', $data['branch_id'], 'REQ'),
                'branch_id' => $data['branch_id'],
                'status' => 'DRAFT',
                'requested_by' => $data['user_id'],
                'needed_by' => $data['needed_by'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                if (bccomp((string) $line['qty_base'], '0', 4) <= 0) {
                    throw new \InvalidArgumentException('A requisition line quantity must be positive.');
                }
                RequisitionLine::create([
                    'requisition_id' => $requisition->id,
                    'product_id' => $line['product_id'],
                    'qty_requested' => bcadd((string) $line['qty_base'], '0', 4),
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            AuditLog::record('REQUISITION_CREATED', 'requisition', $requisition->id, [
                'user_id' => $data['user_id'], 'branch_id' => $data['branch_id'], 'reference' => $requisition->doc_number,
            ]);

            return $requisition->fresh(['lines']);
        });
    }

    /**
     * Replaces the header and lines of a requisition nobody has approved yet:
     * a product swapped for another, a line dropped, a quantity corrected.
     *
     * @param  array{needed_by?: ?string, notes?: ?string, lines: list<array{product_id: string, qty_base: string|int|float, notes?: ?string}>}  $data
     */
    public function update(Requisition $requisition, array $data, int $userId): Requisition
    {
        $this->assertStatus($requisition, ['DRAFT', 'PENDING_APPROVAL']);

        return DB::transaction(function () use ($requisition, $data, $userId) {
            $before = $requisition->lines()->get(['product_id', 'qty_requested', 'notes'])->toArray();

            $requisition->update([
                'needed_by' => $data['needed_by'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $requisition->lines()->delete();
            foreach ($data['lines'] as $line) {
                if (bccomp((string) $line['qty_base'], '0', 4) <= 0) {
                    throw new \InvalidArgumentException('A requisition line quantity must be positive.');
                }
                RequisitionLine::create([
                    'requisition_id' => $requisition->id,
                    'product_id' => $line['product_id'],
                    'qty_requested' => bcadd((string) $line['qty_base'], '0', 4),
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            AuditLog::record('REQUISITION_UPDATED', 'requisition', $requisition->id, [
                'user_id' => $userId, 'reference' => $requisition->doc_number,
                'before_json' => ['lines' => $before], 'after_json' => ['lines' => $data['lines']],
            ]);

            return $requisition->fresh(['lines']);
        });
    }

    public function submit(Requisition $requisition, int $userId): Requisition
    {
        $this->assertStatus($requisition, ['DRAFT']);
        if ($requisition->lines()->count() === 0) {
            throw new \DomainException('A requisition needs at least one line before it is submitted.');
        }
        $requisition->update(['status' => 'PENDING_APPROVAL']);
        AuditLog::record('REQUISITION_SUBMITTED', 'requisition', $requisition->id, ['user_id' => $userId, 'reference' => $requisition->doc_number]);

        return $requisition->fresh(['lines']);
    }

    public function approve(Requisition $requisition, int $approverId): Requisition
    {
        $this->assertStatus($requisition, ['PENDING_APPROVAL']);
        $requisition->update(['status' => 'APPROVED', 'approved_by' => $approverId]);
        AuditLog::record('REQUISITION_APPROVED', 'requisition', $requisition->id, ['user_id' => $approverId, 'reference' => $requisition->doc_number]);

        return $requisition->fresh(['lines']);
    }

    public function reject(Requisition $requisition, int $approverId, string $reason): Requisition
    {
        $this->assertStatus($requisition, ['PENDING_APPROVAL']);
        $requisition->update(['status' => 'REJECTED', 'approved_by' => $approverId]);
        AuditLog::record('REQUISITION_REJECTED', 'requisition', $requisition->id, ['user_id' => $approverId, 'reference' => $requisition->doc_number, 'reason' => $reason]);

        return $requisition->fresh(['lines']);
    }

    /**
     * The approved requisition goes out for quotation: its lines become a
     * request for quotation (in each product's base unit) addressed to the
     * chosen suppliers. The RFQ's PDF goes to the supplier, their confirmed
     * prices are recorded against it, and its award raises the purchase
     * order, which still points back to this requisition.
     *
     * @param  array{supplier_ids: list<string>, user_id: int, title?: ?string, needed_by?: ?string, notes?: ?string, supplier_products?: array<string, list<string>|null>}  $data
     */
    public function requestQuotation(Requisition $requisition, array $data, RfqService $rfqs): Rfq
    {
        $this->assertStatus($requisition, ['APPROVED']);

        return DB::transaction(function () use ($requisition, $data, $rfqs) {
            $requisition->load('lines.product');

            $rfq = $rfqs->create([
                'branch_id' => $requisition->branch_id,
                'requisition_id' => $requisition->id,
                'user_id' => $data['user_id'],
                'title' => $data['title'] ?? "Quotation for {$requisition->doc_number}",
                'needed_by' => $data['needed_by'] ?? $requisition->needed_by?->toDateString(),
                'notes' => $data['notes'] ?? $requisition->notes,
                'lines' => $requisition->lines->map(fn (RequisitionLine $line) => [
                    'product_id' => $line->product_id,
                    'uom_id' => $line->product->base_uom_id,
                    'qty' => (string) $line->qty_requested,
                    'notes' => $line->notes,
                ])->all(),
                'supplier_ids' => $data['supplier_ids'],
                'supplier_products' => $data['supplier_products'] ?? [],
            ]);

            $requisition->update(['status' => 'CONVERTED']);
            AuditLog::record('REQUISITION_SENT_FOR_QUOTATION', 'requisition', $requisition->id, [
                'user_id' => $data['user_id'], 'reference' => $requisition->doc_number, 'after_json' => ['rfq' => $rfq->doc_number],
            ]);

            return $rfq;
        });
    }

    /**
     * Single sourcing: the approved requisition becomes one purchase order
     * (DRAFT, awaiting its own approval) for one chosen supplier without a
     * bid analysis. The reason and its justification travel on the order,
     * so its approver and any later audit see why no bids were sought.
     *
     * @param  array{supplier_id: string, user_id: int, single_source_reason: string, justification: string, expected_date?: ?string, lines: list<array{requisition_line_id: string, uom_id: string, qty_ordered: string, unit_price: string, tax_code_id?: ?string}>}  $data
     */
    public function convertToPurchaseOrder(Requisition $requisition, array $data): PurchaseOrder
    {
        $this->assertStatus($requisition, ['APPROVED']);

        $supplier = Supplier::findOrFail($data['supplier_id']);
        if ($supplier->status !== 'ACTIVE' || ! $supplier->is_active) {
            throw new \DomainException("Supplier {$supplier->name} is {$supplier->status}; no purchase order may be raised.");
        }
        if ($supplier->licence_expiry && $supplier->licence_expiry->isPast()) {
            throw new \DomainException("Supplier {$supplier->name}'s licence expired on {$supplier->licence_expiry->toDateString()}.");
        }

        return DB::transaction(function () use ($requisition, $data, $supplier) {
            $organisationId = (string) Branch::whereKey($requisition->branch_id)->value('organisation_id');
            $po = PurchaseOrder::create([
                'doc_number' => NumberSequence::next($organisationId, 'PO', $requisition->branch_id, 'PO'),
                'supplier_id' => $supplier->id,
                'branch_id' => $requisition->branch_id,
                'requisition_id' => $requisition->id,
                'sourcing_method' => PurchaseOrder::SINGLE_SOURCE,
                'single_source_reason' => $data['single_source_reason'],
                'single_source_justification' => $data['justification'],
                'status' => 'DRAFT',
                'created_by' => $data['user_id'],
                'expected_date' => $data['expected_date'] ?? null,
                'transport_cost' => bcadd((string) ($data['transport_cost'] ?? '0'), '0', 4),
            ]);

            foreach ($data['lines'] as $line) {
                $reqLine = RequisitionLine::where('requisition_id', $requisition->id)->findOrFail($line['requisition_line_id']);
                $product = Product::findOrFail($reqLine->product_id);
                if (! $product->uoms()->where('uom_id', $line['uom_id'])->exists()) {
                    throw new \InvalidArgumentException("{$product->name} cannot be purchased in that unit (Part 5.3).");
                }

                $unitPrice = $line['unit_price'] === null ? null : bcadd((string) $line['unit_price'], '0', 4);

                PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $product->id,
                    'uom_id' => $line['uom_id'],
                    'qty_ordered' => bcadd((string) $line['qty_ordered'], '0', 4),
                    'unit_price' => $unitPrice,
                    'tax_code_id' => $line['tax_code_id'] ?? null,
                ]);
            }

            $requisition->update(['status' => 'CONVERTED']);

            AuditLog::record('REQUISITION_CONVERTED', 'requisition', $requisition->id, [
                'user_id' => $data['user_id'], 'branch_id' => $requisition->branch_id, 'reference' => $requisition->doc_number,
                'after_json' => ['purchase_order' => $po->doc_number, 'single_source_reason' => $data['single_source_reason']],
                'reason' => $data['justification'],
            ]);
            AuditLog::record('PO_CREATED', 'purchase_order', $po->id, ['user_id' => $data['user_id'], 'reference' => $po->doc_number]);

            return $po->fresh(['lines']);
        });
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertStatus(Requisition $requisition, array $expected): void
    {
        if (! in_array($requisition->status, $expected, true)) {
            throw new InvalidRequisitionStatusException("Requisition {$requisition->doc_number} is {$requisition->status}, not ".implode('/', $expected).'.');
        }
    }
}
