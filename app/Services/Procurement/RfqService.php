<?php

namespace App\Services\Procurement;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\NumberSequence;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Rfq;
use App\Models\RfqLine;
use App\Models\RfqQuoteLine;
use App\Models\RfqSupplier;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class InvalidRfqStatusException extends \RuntimeException {}

class AwardJustificationRequiredException extends \RuntimeException {}

/**
 * Request for quotation → supplier quotes → competitive bid analysis →
 * award. DRAFT → SENT → CLOSED → AWARDED, or CANCELLED at any point before
 * the award. The award raises one DRAFT purchase order per awarded
 * supplier; each still needs its own approval.
 */
class RfqService
{
    /** An award that departs from the recommendation must say why, in at least this many characters. */
    public const MIN_JUSTIFICATION_LENGTH = 10;

    public function __construct(private CompetitiveBidAnalyzer $analyzer) {}

    /**
     * @param  array{branch_id: string, user_id: int, title: string, needed_by?: ?string, notes?: ?string, weights?: array<string, numeric>, lines: list<array{product_id: string, uom_id: string, qty: numeric-string, notes?: ?string}>, supplier_ids: list<string>}  $data
     */
    public function create(array $data): Rfq
    {
        return DB::transaction(function () use ($data) {
            $organisationId = (string) Branch::whereKey($data['branch_id'])->value('organisation_id');

            $rfq = Rfq::create([
                'doc_number' => NumberSequence::next($organisationId, 'RFQ', $data['branch_id'], 'RFQ'),
                'branch_id' => $data['branch_id'],
                'title' => $data['title'],
                'needed_by' => $data['needed_by'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'DRAFT',
                'created_by' => $data['user_id'],
            ] + $this->weightColumns($data['weights'] ?? []));

            $this->replaceLines($rfq, $data['lines']);
            $this->inviteSuppliers($rfq, $data['supplier_ids']);

            AuditLog::record('RFQ_CREATED', 'rfq', $rfq->id, ['user_id' => $data['user_id'], 'branch_id' => $rfq->branch_id, 'reference' => $rfq->doc_number]);

            return $rfq;
        });
    }

    /**
     * Header and weights change until the award. Lines and the supplier
     * list are replaced while DRAFT; once sent, suppliers can only be added.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Rfq $rfq, array $data, int $userId): Rfq
    {
        $this->assertStatus($rfq, ['DRAFT', 'SENT', 'CLOSED']);

        return DB::transaction(function () use ($rfq, $data, $userId) {
            $rfq->update(collect($data)->only(['title', 'needed_by', 'notes'])->all() + $this->weightColumns($data['weights'] ?? []));

            if (array_key_exists('lines', $data)) {
                $this->assertStatus($rfq, ['DRAFT']);
                $this->replaceLines($rfq, $data['lines']);
            }

            if (array_key_exists('supplier_ids', $data)) {
                if ($rfq->status === 'DRAFT') {
                    $rfq->suppliers()->whereNotIn('supplier_id', $data['supplier_ids'])->delete();
                } else {
                    $this->assertStatus($rfq, ['SENT']);
                }
                $this->inviteSuppliers($rfq, $data['supplier_ids']);
            }

            AuditLog::record('RFQ_UPDATED', 'rfq', $rfq->id, ['user_id' => $userId, 'reference' => $rfq->doc_number, 'after_json' => ['changed_fields' => array_keys($data)]]);

            return $rfq->fresh();
        });
    }

    public function send(Rfq $rfq, int $userId): Rfq
    {
        $this->assertStatus($rfq, ['DRAFT']);
        if ($rfq->lines()->count() === 0 || $rfq->suppliers()->count() === 0) {
            throw new \DomainException('Add at least one line and one supplier before sending the request.');
        }

        $rfq->update(['status' => 'SENT', 'sent_at' => now()]);
        AuditLog::record('RFQ_SENT', 'rfq', $rfq->id, ['user_id' => $userId, 'reference' => $rfq->doc_number]);

        return $rfq->fresh();
    }

    /**
     * Records (or replaces) one supplier's quote. A line left out was not
     * quoted. A declined request is recorded with no lines.
     *
     * @param  array{declined?: bool, quote_reference?: ?string, quote_date?: ?string, valid_until?: ?string, payment_terms_days?: ?int, delivery_charge?: numeric|null, notes?: ?string, lines?: list<array{rfq_line_id: string, unit_price: numeric, qty_available?: numeric|null, lead_time_days: int, shelf_life_months?: ?int, notes?: ?string}>}  $data
     */
    public function recordQuote(Rfq $rfq, string $supplierId, array $data, int $userId): RfqSupplier
    {
        $this->assertStatus($rfq, ['SENT', 'CLOSED']);

        $invite = $rfq->suppliers()->where('supplier_id', $supplierId)->first();
        if (! $invite) {
            throw new \DomainException('That supplier was not invited to quote on this request.');
        }

        $declined = (bool) ($data['declined'] ?? false);
        $lines = $declined ? [] : ($data['lines'] ?? []);
        if (! $declined && $lines === []) {
            throw new \DomainException('Enter a price for at least one line, or record that the supplier declined.');
        }

        $lineIds = $rfq->lines()->pluck('id')->map(fn ($id) => (string) $id)->all();

        return DB::transaction(function () use ($rfq, $invite, $data, $declined, $lines, $lineIds, $userId) {
            $invite->update([
                'quote_status' => $declined ? 'DECLINED' : 'RECEIVED',
                'quote_reference' => $data['quote_reference'] ?? null,
                'quote_date' => $data['quote_date'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'payment_terms_days' => $data['payment_terms_days'] ?? null,
                'delivery_charge' => bcadd((string) ($data['delivery_charge'] ?? '0'), '0', 4),
                'notes' => $data['notes'] ?? null,
                'entered_by' => $userId,
                'received_at' => now(),
            ]);

            $invite->quoteLines()->delete();
            $seen = [];
            foreach ($lines as $line) {
                if (! in_array((string) $line['rfq_line_id'], $lineIds, true)) {
                    throw new \InvalidArgumentException('A quoted line does not belong to this request.');
                }
                if (isset($seen[$line['rfq_line_id']])) {
                    throw new \InvalidArgumentException('Each line can be quoted only once per supplier.');
                }
                $seen[$line['rfq_line_id']] = true;

                RfqQuoteLine::create([
                    'rfq_supplier_id' => $invite->id,
                    'rfq_line_id' => $line['rfq_line_id'],
                    'unit_price' => bcadd((string) $line['unit_price'], '0', 4),
                    'qty_available' => isset($line['qty_available']) && $line['qty_available'] !== '' ? bcadd((string) $line['qty_available'], '0', 4) : null,
                    'lead_time_days' => (int) $line['lead_time_days'],
                    'shelf_life_months' => $line['shelf_life_months'] ?? null,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            AuditLog::record($declined ? 'RFQ_QUOTE_DECLINED' : 'RFQ_QUOTE_RECORDED', 'rfq', $rfq->id, [
                'user_id' => $userId, 'reference' => $rfq->doc_number,
                'after_json' => ['supplier_id' => $invite->supplier_id, 'lines_quoted' => count($lines)],
            ]);

            return $invite->fresh(['quoteLines', 'supplier']);
        });
    }

    public function close(Rfq $rfq, int $userId): Rfq
    {
        $this->assertStatus($rfq, ['SENT']);
        if (! $rfq->suppliers()->where('quote_status', 'RECEIVED')->exists()) {
            throw new \DomainException('No quotes have been recorded yet; there is nothing to analyse.');
        }

        $rfq->update(['status' => 'CLOSED', 'closed_at' => now()]);
        AuditLog::record('RFQ_CLOSED', 'rfq', $rfq->id, ['user_id' => $userId, 'reference' => $rfq->doc_number]);

        return $rfq->fresh();
    }

    public function cancel(Rfq $rfq, int $userId, string $reason): Rfq
    {
        $this->assertStatus($rfq, ['DRAFT', 'SENT', 'CLOSED']);

        $rfq->update(['status' => 'CANCELLED', 'cancel_reason' => $reason]);
        AuditLog::record('RFQ_CANCELLED', 'rfq', $rfq->id, ['user_id' => $userId, 'reference' => $rfq->doc_number, 'reason' => $reason]);

        return $rfq->fresh();
    }

    /**
     * Awards the request. With no choices the recommendation is accepted
     * as it stands; choices that depart from it need a justification. One
     * DRAFT purchase order is raised per awarded supplier, at the quoted
     * prices, for the quantity each can supply.
     *
     * @param  array<string, string|null>|null  $choices  rfq_line_id => supplier_id (null leaves the line unawarded)
     * @return array{rfq: Rfq, purchase_orders: list<PurchaseOrder>}
     */
    public function award(Rfq $rfq, ?array $choices, ?string $justification, int $userId): array
    {
        $this->assertStatus($rfq, ['SENT', 'CLOSED']);

        $analysis = $this->analyzer->analyse($rfq);
        $recommended = (array) $analysis['overall']['plan'];
        $lines = collect($analysis['lines'])->keyBy('rfq_line_id');

        $choices = $choices === null
            ? $recommended
            : array_map(fn ($supplierId) => $supplierId === '' ? null : $supplierId, $choices);
        foreach (array_keys($choices) as $lineId) {
            if (! $lines->has($lineId)) {
                throw new \InvalidArgumentException('An awarded line does not belong to this request.');
            }
        }

        $followed = true;
        foreach ($lines->keys() as $lineId) {
            if (($choices[$lineId] ?? null) !== ($recommended[$lineId] ?? null)) {
                $followed = false;
            }
        }
        $justification = trim((string) $justification);
        if (! $followed && mb_strlen($justification) < self::MIN_JUSTIFICATION_LENGTH) {
            throw new AwardJustificationRequiredException('This award departs from the recommendation. Say why (at least '.self::MIN_JUSTIFICATION_LENGTH.' characters).');
        }

        $bySupplier = [];
        foreach ($choices as $lineId => $supplierId) {
            if ($supplierId === null) {
                continue;
            }
            $quote = collect($lines[$lineId]['quotes'])->firstWhere('supplier_id', $supplierId);
            if (! $quote) {
                throw new \DomainException('That supplier did not quote '.($lines[$lineId]['product']['name'] ?? 'this line').'.');
            }
            if (! $quote['eligible']) {
                $supplier = Supplier::find($supplierId);
                throw new \DomainException($supplier && $supplier->isLicenceExpired()
                    ? "{$supplier->name}'s licence expired on {$supplier->licence_expiry->format('j M Y')}; no order can be placed."
                    : ($supplier?->name ?? 'That supplier').' is not active; no order can be placed.');
            }
            $bySupplier[$supplierId][$lineId] = $quote;
        }
        if ($bySupplier === []) {
            throw new \DomainException('Award at least one line.');
        }

        return DB::transaction(function () use ($rfq, $analysis, $recommended, $choices, $bySupplier, $followed, $justification, $userId) {
            $organisationId = (string) Branch::whereKey($rfq->branch_id)->value('organisation_id');
            $rfqLines = $rfq->lines()->get()->keyBy('id');

            $orders = [];
            foreach ($bySupplier as $supplierId => $quotes) {
                $leadDays = max(array_column($quotes, 'lead_time_days'));
                $po = PurchaseOrder::create([
                    'doc_number' => NumberSequence::next($organisationId, 'PO', $rfq->branch_id, 'PO'),
                    'supplier_id' => $supplierId,
                    'branch_id' => $rfq->branch_id,
                    'rfq_id' => $rfq->id,
                    'status' => 'DRAFT',
                    'created_by' => $userId,
                    'expected_date' => now()->addDays($leadDays)->toDateString(),
                ]);

                foreach ($quotes as $lineId => $quote) {
                    $rfqLine = $rfqLines[$lineId];
                    PurchaseOrderLine::create([
                        'purchase_order_id' => $po->id,
                        'product_id' => $rfqLine->product_id,
                        'uom_id' => $rfqLine->uom_id,
                        'qty_ordered' => $quote['qty_to_order'],
                        'unit_price' => $quote['unit_price'],
                        'tax_code_id' => Product::whereKey($rfqLine->product_id)->value('tax_code_id'),
                    ]);
                }

                AuditLog::record('PO_CREATED', 'purchase_order', $po->id, ['user_id' => $userId, 'reference' => $po->doc_number, 'after_json' => ['rfq' => $rfq->doc_number]]);
                $orders[] = $po->fresh(['lines', 'supplier:id,code,name']);
            }

            foreach ($rfqLines as $lineId => $rfqLine) {
                $rfqLine->update([
                    'awarded_supplier_id' => $choices[$lineId] ?? null,
                    'recommended_supplier_id' => $recommended[$lineId] ?? null,
                ]);
            }

            $rfq->update([
                'status' => 'AWARDED',
                'award_mode' => count($bySupplier) === 1 ? 'SINGLE' : 'SPLIT',
                'award_followed_recommendation' => $followed,
                'award_justification' => $justification !== '' ? $justification : null,
                'awarded_by' => $userId,
                'awarded_at' => now(),
                'closed_at' => $rfq->closed_at ?? now(),
                'analysis_snapshot' => $analysis,
            ]);

            AuditLog::record('RFQ_AWARDED', 'rfq', $rfq->id, [
                'user_id' => $userId, 'reference' => $rfq->doc_number,
                'reason' => $followed ? null : $justification,
                'after_json' => [
                    'followed_recommendation' => $followed,
                    'purchase_orders' => array_map(fn (PurchaseOrder $po) => $po->doc_number, $orders),
                ],
            ]);

            return ['rfq' => $rfq->fresh(), 'purchase_orders' => $orders];
        });
    }

    /**
     * @param  list<array{product_id: string, uom_id: string, qty: numeric-string, notes?: ?string}>  $lines
     */
    private function replaceLines(Rfq $rfq, array $lines): void
    {
        if ($lines === []) {
            throw new \InvalidArgumentException('A request for quotation needs at least one line.');
        }

        $rfq->lines()->delete();
        foreach (array_values($lines) as $index => $line) {
            $product = Product::findOrFail($line['product_id']);
            if (! $product->uoms()->where('uom_id', $line['uom_id'])->where('is_purchase', true)->exists()) {
                throw new \InvalidArgumentException("{$product->name} cannot be bought in that unit; choose one of its purchase units.");
            }
            if (bccomp((string) $line['qty'], '0', 4) <= 0) {
                throw new \InvalidArgumentException('Every line needs a quantity above zero.');
            }

            RfqLine::create([
                'rfq_id' => $rfq->id,
                'product_id' => $product->id,
                'uom_id' => $line['uom_id'],
                'qty' => bcadd((string) $line['qty'], '0', 4),
                'notes' => $line['notes'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  list<string>  $supplierIds
     */
    private function inviteSuppliers(Rfq $rfq, array $supplierIds): void
    {
        if ($supplierIds === []) {
            throw new \InvalidArgumentException('Invite at least one supplier.');
        }

        foreach (array_unique($supplierIds) as $supplierId) {
            RfqSupplier::firstOrCreate(['rfq_id' => $rfq->id, 'supplier_id' => $supplierId]);
        }
    }

    /**
     * @param  array<string, numeric>  $weights
     * @return array<string, string>
     */
    private function weightColumns(array $weights): array
    {
        $columns = [];
        foreach (['price', 'lead_time', 'payment_terms', 'supplier_record'] as $key) {
            if (array_key_exists($key, $weights) && $weights[$key] !== null) {
                $columns['weight_'.$key] = (string) $weights[$key];
            }
        }
        if ($columns !== [] && count($columns) < 4) {
            throw new \InvalidArgumentException('Give all four weights together.');
        }
        if ($columns !== [] && abs(array_sum(array_map('floatval', $columns)) - 100) > 0.01) {
            throw new \InvalidArgumentException('The four weights must add up to 100%.');
        }

        return $columns;
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertStatus(Rfq $rfq, array $expected): void
    {
        if (! in_array($rfq->status, $expected, true)) {
            throw new InvalidRfqStatusException("Request {$rfq->doc_number} is {$rfq->status}, not ".implode('/', $expected).'.');
        }
    }
}
