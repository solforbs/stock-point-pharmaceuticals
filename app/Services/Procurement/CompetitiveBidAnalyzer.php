<?php

namespace App\Services\Procurement;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Rfq;
use App\Models\RfqLine;
use App\Models\RfqSupplier;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Illuminate\Support\Carbon;

/**
 * Competitive bid analysis (CBA): every supplier's quote side by side, a
 * weighted score per supplier per line, and a recommendation with the plain
 * reasons behind it.
 *
 * Scores are out of 100 per criterion:
 *  - price: the cheapest unit price scores 100, others in proportion;
 *  - lead time: the fastest scores 100, others (fastest + 1) / (theirs + 1);
 *  - payment terms: the longest credit scores 100, others in proportion;
 *  - supplier record: from history — invoices that went to three-way-match
 *    exception, goods rejected on receipt, deliveries late against the PO,
 *    and the state of the supplier's licence.
 *
 * A supplier that is suspended or whose licence has expired is never
 * recommended (no purchase order may be raised to them). Among the rest a
 * valid quote beats an expired one, and a supplier who can supply the full
 * quantity beats one who cannot, before the score decides.
 */
class CompetitiveBidAnalyzer
{
    /** Splitting must save more than this share of the cost to beat a single order. */
    public const SPLIT_SAVING_THRESHOLD_PCT = 2.0;

    /** Shelf life offered below this many months is flagged. */
    public const SHORT_SHELF_LIFE_MONTHS = 12;

    /** A licence expiring within this many days is flagged. */
    public const LICENCE_WARNING_DAYS = 60;

    /** Score used for a part of the record with no history yet. */
    private const NO_HISTORY_SCORE = 70.0;

    /**
     * @return array<string, mixed>
     */
    public function analyse(Rfq $rfq): array
    {
        $rfq->loadMissing([
            'lines.product:id,code,name,strength', 'lines.uom:id,code',
            'suppliers.supplier', 'suppliers.quoteLines',
        ]);

        $weights = $rfq->weights();
        $today = Carbon::today();

        $suppliers = [];
        foreach ($rfq->suppliers as $invite) {
            $suppliers[$invite->supplier_id] = $this->supplierSummary($rfq, $invite, $today);
        }

        $lines = [];
        foreach ($rfq->lines as $line) {
            $lines[] = $this->analyseLine($rfq, $line, $suppliers, $weights);
        }

        foreach ($suppliers as $supplierId => $summary) {
            $total = '0.0000';
            $quoted = 0;
            foreach ($lines as $line) {
                foreach ($line['quotes'] as $quote) {
                    if ($quote['supplier_id'] === $supplierId) {
                        $total = bcadd($total, $quote['line_total'], 4);
                        $quoted++;
                    }
                }
            }
            $suppliers[$supplierId]['lines_quoted'] = $quoted;
            $suppliers[$supplierId]['total'] = $total;
            $suppliers[$supplierId]['total_with_delivery'] = $quoted > 0 ? bcadd($total, $summary['delivery_charge'], 4) : '0.0000';
        }

        $overall = $this->overall($lines, $suppliers);

        return [
            'rfq' => [
                'id' => (string) $rfq->id,
                'doc_number' => $rfq->doc_number,
                'title' => $rfq->title,
                'status' => $rfq->status,
                'needed_by' => $rfq->needed_by?->toDateString(),
            ],
            'weights' => $weights,
            'suppliers' => array_values($suppliers),
            'lines' => $lines,
            'overall' => $overall,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierSummary(Rfq $rfq, RfqSupplier $invite, Carbon $today): array
    {
        $supplier = $invite->supplier;
        $record = $this->supplierRecord($supplier, $rfq, $today);

        $risks = $record['risks'];
        $quoteValid = true;
        if ($invite->quote_status === 'RECEIVED' && $invite->valid_until && $invite->valid_until->lt($today)) {
            $quoteValid = false;
            $risks[] = ['code' => 'QUOTE_EXPIRED', 'message' => 'Quote validity passed on '.$invite->valid_until->format('j M Y').'; confirm the prices still hold.'];
        }

        return [
            'supplier_id' => (string) $supplier->id,
            'rfq_supplier_id' => (string) $invite->id,
            'code' => $supplier->code,
            'name' => $supplier->name,
            'quote_status' => $invite->quote_status,
            'quote_reference' => $invite->quote_reference,
            'valid_until' => $invite->valid_until?->toDateString(),
            'quote_valid' => $quoteValid,
            'payment_terms_days' => $invite->payment_terms_days,
            'delivery_charge' => bcadd((string) ($invite->delivery_charge ?? '0'), '0', 4),
            'eligible' => $record['eligible'],
            'record' => $record,
            'risks' => $risks,
        ];
    }

    /**
     * The supplier's track record, from this institution's own history.
     *
     * @return array<string, mixed>
     */
    public function supplierRecord(Supplier $supplier, ?Rfq $rfq = null, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $risks = [];

        $invoices = SupplierInvoice::where('supplier_id', $supplier->id)->whereIn('match_status', ['MATCHED', 'EXCEPTION'])
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN match_status = 'EXCEPTION' THEN 1 ELSE 0 END) as exceptions")
            ->first();
        $invoiceTotal = (int) ($invoices->total ?? 0);
        $invoiceExceptions = (int) ($invoices->exceptions ?? 0);

        $receiptIds = GoodsReceipt::where('supplier_id', $supplier->id)->where('status', 'POSTED')->pluck('id');
        $deliveries = $receiptIds->count();
        $quantities = GoodsReceiptLine::whereIn('goods_receipt_id', $receiptIds)
            ->selectRaw('COALESCE(SUM(qty_delivered), 0) as delivered, COALESCE(SUM(qty_rejected), 0) as rejected')
            ->first();
        $delivered = (float) ($quantities->delivered ?? 0);
        $rejected = (float) ($quantities->rejected ?? 0);

        $timed = GoodsReceipt::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'goods_receipts.purchase_order_id')
            ->where('goods_receipts.supplier_id', $supplier->id)->where('goods_receipts.status', 'POSTED')
            ->whereNotNull('purchase_orders.expected_date')
            ->get(['goods_receipts.received_at', 'goods_receipts.created_at', 'purchase_orders.expected_date']);
        $onTime = $timed->filter(function ($row) {
            $arrived = Carbon::parse($row->received_at ?? $row->created_at)->startOfDay();

            return $arrived->lte(Carbon::parse($row->expected_date)->startOfDay());
        })->count();

        $eligible = true;
        $licenceStatus = 'VALID';
        $expiry = $supplier->licence_expiry;
        if ($expiry === null) {
            $licenceStatus = 'MISSING';
            $risks[] = ['code' => 'LICENCE_MISSING', 'message' => 'No licence expiry date on file for '.$supplier->name.'.'];
        } elseif ($expiry->lt($today)) {
            $licenceStatus = 'EXPIRED';
            $eligible = false;
            $risks[] = ['code' => 'LICENCE_EXPIRED', 'message' => $supplier->name."'s licence expired on ".$expiry->format('j M Y').'; no order can be placed.'];
        } elseif ($expiry->lte($today->copy()->addDays(self::LICENCE_WARNING_DAYS)) || ($rfq?->needed_by && $expiry->lt($rfq->needed_by))) {
            $licenceStatus = 'EXPIRING';
            $risks[] = ['code' => 'LICENCE_EXPIRING', 'message' => $supplier->name."'s licence expires on ".$expiry->format('j M Y').'.'];
        }

        if ($supplier->status !== 'ACTIVE' || ! $supplier->is_active) {
            $eligible = false;
            $label = $supplier->status !== 'ACTIVE' ? strtolower((string) $supplier->status) : 'inactive';
            $risks[] = ['code' => 'SUPPLIER_BLOCKED', 'message' => $supplier->name.' is '.$label.'; no order can be placed.'];
        }

        $invoiceScore = $invoiceTotal > 0 ? 100 * (1 - $invoiceExceptions / $invoiceTotal) : null;
        $qualityScore = $delivered > 0 ? 100 * (1 - $rejected / $delivered) : null;
        $onTimeScore = $timed->count() > 0 ? 100 * $onTime / $timed->count() : null;
        $licenceScore = match ($licenceStatus) {
            'VALID' => 100.0,
            'EXPIRING' => 70.0,
            'MISSING' => 50.0,
            default => 0.0,
        };

        $score = 0.4 * ($invoiceScore ?? self::NO_HISTORY_SCORE)
            + 0.2 * ($qualityScore ?? self::NO_HISTORY_SCORE)
            + 0.2 * ($onTimeScore ?? self::NO_HISTORY_SCORE)
            + 0.2 * $licenceScore;
        if (! $eligible) {
            $score = 0.0;
        }

        return [
            'score' => round($score, 1),
            'eligible' => $eligible,
            'status' => $supplier->status,
            'licence_status' => $licenceStatus,
            'licence_expiry' => $expiry?->toDateString(),
            'invoices_matched' => $invoiceTotal,
            'invoices_exception' => $invoiceExceptions,
            'deliveries' => $deliveries,
            'deliveries_timed' => $timed->count(),
            'deliveries_on_time' => $onTime,
            'rejected_share_pct' => $delivered > 0 ? round(100 * $rejected / $delivered, 1) : null,
            'summary' => $this->recordSummary($licenceStatus, $expiry, $invoiceTotal, $invoiceExceptions, $deliveries, $timed->count(), $onTime, $delivered, $rejected),
            'risks' => $risks,
        ];
    }

    private function recordSummary(string $licenceStatus, ?Carbon $expiry, int $invoices, int $exceptions, int $deliveries, int $timed, int $onTime, float $delivered, float $rejected): string
    {
        $parts = [match ($licenceStatus) {
            'VALID', 'EXPIRING' => 'licence valid to '.$expiry?->format('j M Y'),
            'EXPIRED' => 'licence expired on '.$expiry?->format('j M Y'),
            default => 'no licence date on file',
        }];

        if ($deliveries === 0 && $invoices === 0) {
            $parts[] = 'no purchase history yet';

            return implode('; ', $parts);
        }
        if ($invoices > 0) {
            $parts[] = $exceptions === 0
                ? "all {$invoices} past invoice".($invoices === 1 ? '' : 's').' matched'
                : "{$exceptions} of {$invoices} past invoice".($invoices === 1 ? '' : 's').' went to exception';
        }
        if ($timed > 0) {
            $parts[] = "on time for {$onTime} of {$timed} deliver".($timed === 1 ? 'y' : 'ies');
        } elseif ($deliveries > 0) {
            $parts[] = "{$deliveries} past deliver".($deliveries === 1 ? 'y' : 'ies');
        }
        if ($delivered > 0 && $rejected > 0) {
            $parts[] = round(100 * $rejected / $delivered, 1).'% of goods rejected on receipt';
        }

        return implode('; ', $parts);
    }

    /**
     * @param  array<string, array<string, mixed>>  $suppliers
     * @param  array{price: float, lead_time: float, payment_terms: float, supplier_record: float}  $weights
     * @return array<string, mixed>
     */
    private function analyseLine(Rfq $rfq, RfqLine $line, array $suppliers, array $weights): array
    {
        $raw = [];
        foreach ($rfq->suppliers as $invite) {
            if ($invite->quote_status !== 'RECEIVED') {
                continue;
            }
            $quoteLine = $invite->quoteLines->firstWhere('rfq_line_id', $line->id);
            if (! $quoteLine) {
                continue;
            }
            $raw[] = [$invite, $quoteLine];
        }

        $prices = array_map(fn ($pair) => (float) $pair[1]->unit_price, $raw);
        $leads = array_map(fn ($pair) => (int) $pair[1]->lead_time_days, $raw);
        $terms = array_map(fn ($pair) => (int) ($pair[0]->payment_terms_days ?? 0), $raw);
        $minPrice = $prices ? min($prices) : 0.0;
        $minLead = $leads ? min($leads) : 0;
        $maxTerms = $terms ? max($terms) : 0;
        $weightSum = array_sum($weights) ?: 1.0;

        $quotes = [];
        foreach ($raw as [$invite, $quoteLine]) {
            $summary = $suppliers[$invite->supplier_id];
            $price = (float) $quoteLine->unit_price;
            $available = $quoteLine->qty_available !== null ? (string) $quoteLine->qty_available : null;
            $fullQty = $available === null || bccomp($available, (string) $line->qty, 4) >= 0;
            $qtyToOrder = $fullQty ? bcadd((string) $line->qty, '0', 4) : bcadd($available, '0', 4);

            $scores = [
                'price' => $price > 0 ? round(100 * $minPrice / $price, 1) : 100.0,
                'lead_time' => round(100 * ($minLead + 1) / ((int) $quoteLine->lead_time_days + 1), 1),
                'payment_terms' => $maxTerms > 0 ? round(100 * (int) ($invite->payment_terms_days ?? 0) / $maxTerms, 1) : 100.0,
                'supplier_record' => $summary['record']['score'],
            ];
            $scores['total'] = round((
                $weights['price'] * $scores['price']
                + $weights['lead_time'] * $scores['lead_time']
                + $weights['payment_terms'] * $scores['payment_terms']
                + $weights['supplier_record'] * $scores['supplier_record']
            ) / $weightSum, 1);

            $risks = [];
            if (! $fullQty) {
                $risks[] = ['code' => 'PARTIAL_QUANTITY', 'message' => 'Can supply only '.$this->qty($available).' of '.$this->qty((string) $line->qty).'.'];
            }
            if ($quoteLine->shelf_life_months !== null && $quoteLine->shelf_life_months < self::SHORT_SHELF_LIFE_MONTHS) {
                $risks[] = ['code' => 'SHORT_SHELF_LIFE', 'message' => 'Only '.$quoteLine->shelf_life_months.' months of shelf life offered.'];
            }

            $quotes[] = [
                'supplier_id' => (string) $invite->supplier_id,
                'supplier_name' => $summary['name'],
                'quote_line_id' => (string) $quoteLine->id,
                'unit_price' => bcadd((string) $quoteLine->unit_price, '0', 4),
                'qty_available' => $available,
                'qty_to_order' => $qtyToOrder,
                'full_quantity' => $fullQty,
                'line_total' => bcmul($qtyToOrder, (string) $quoteLine->unit_price, 4),
                'lead_time_days' => (int) $quoteLine->lead_time_days,
                'shelf_life_months' => $quoteLine->shelf_life_months,
                'payment_terms_days' => $invite->payment_terms_days,
                'notes' => $quoteLine->notes,
                'is_lowest' => $price === $minPrice,
                'eligible' => $summary['eligible'],
                'quote_valid' => $summary['quote_valid'],
                'scores' => $scores,
                'risks' => array_merge($summary['risks'], $risks),
            ];
        }

        $ranked = $this->rank($quotes);
        $recommended = $ranked[0] ?? null;
        $reasons = $recommended ? $this->lineReasons($recommended, $ranked, $quotes, $suppliers) : [];

        return [
            'rfq_line_id' => (string) $line->id,
            'product' => ['id' => (string) $line->product_id, 'code' => $line->product?->code, 'name' => $line->product?->name, 'strength' => $line->product?->strength],
            'uom' => ['id' => (string) $line->uom_id, 'code' => $line->uom?->code],
            'qty' => bcadd((string) $line->qty, '0', 4),
            'notes' => $line->notes,
            'lowest_unit_price' => $quotes ? bcadd((string) $minPrice, '0', 4) : null,
            'quotes' => $quotes,
            'recommendation' => $recommended ? [
                'supplier_id' => $recommended['supplier_id'],
                'supplier_name' => $recommended['supplier_name'],
                'unit_price' => $recommended['unit_price'],
                'qty_to_order' => $recommended['qty_to_order'],
                'line_total' => $recommended['line_total'],
                'score' => $recommended['scores']['total'],
                'reasons' => $reasons,
                'summary' => $this->sentence($reasons),
                'risks' => $recommended['risks'],
            ] : null,
            'no_recommendation_reason' => $recommended ? null : ($quotes === []
                ? 'No supplier has quoted this line yet.'
                : 'Every supplier who quoted this line is blocked or unlicensed.'),
        ];
    }

    /**
     * Eligible quotes only, best first: a valid quote before an expired one,
     * the full quantity before a part, then the higher score, then the
     * lower price.
     *
     * @param  list<array<string, mixed>>  $quotes
     * @return list<array<string, mixed>>
     */
    private function rank(array $quotes): array
    {
        $eligible = array_values(array_filter($quotes, fn ($q) => $q['eligible']));
        usort($eligible, fn ($a, $b) => [$b['quote_valid'], $b['full_quantity'], $b['scores']['total'], (float) $b['unit_price'] * -1]
            <=> [$a['quote_valid'], $a['full_quantity'], $a['scores']['total'], (float) $a['unit_price'] * -1]);

        return $eligible;
    }

    /**
     * Why this supplier: against the cheapest quote when it is not the
     * cheapest, otherwise against the runner-up.
     *
     * @param  array<string, mixed>  $chosen
     * @param  list<array<string, mixed>>  $ranked
     * @param  list<array<string, mixed>>  $quotes
     * @param  array<string, array<string, mixed>>  $suppliers
     * @return list<string>
     */
    private function lineReasons(array $chosen, array $ranked, array $quotes, array $suppliers): array
    {
        if (count($quotes) === 1) {
            return array_values(array_filter([
                'only quote received for this line',
                $this->leadPhrase($chosen, null),
                $suppliers[$chosen['supplier_id']]['record']['summary'],
            ]));
        }

        $others = array_values(array_filter($quotes, fn ($q) => $q['supplier_id'] !== $chosen['supplier_id']));
        usort($others, fn ($a, $b) => bccomp($a['unit_price'], $b['unit_price'], 4));
        $cheapestOther = $others[0];

        $reasons = [];
        $compareWith = null;
        $priceDiff = bcsub($cheapestOther['unit_price'], $chosen['unit_price'], 4);

        if (bccomp($priceDiff, '0', 4) > 0) {
            $reasons[] = 'cheapest by KES '.$this->money($priceDiff).' a unit ('.$this->pct($priceDiff, $chosen['unit_price']).')';
            $compareWith = $ranked[1] ?? $cheapestOther;
        } elseif (bccomp($priceDiff, '0', 4) === 0) {
            $reasons[] = 'same lowest price as '.$cheapestOther['supplier_name'];
            $compareWith = $cheapestOther;
        } else {
            $dearer = bcmul($priceDiff, '-1', 4);
            $reasons[] = 'KES '.$this->money($dearer).' a unit ('.$this->pct($dearer, $cheapestOther['unit_price']).') dearer than '.$cheapestOther['supplier_name'];
            $reasons[] = $this->whyNot($cheapestOther, $chosen, $suppliers);
            $compareWith = $cheapestOther;
        }

        $reasons[] = $this->leadPhrase($chosen, $compareWith);
        $chosenTerms = (int) ($chosen['payment_terms_days'] ?? 0);
        $otherTerms = (int) ($compareWith['payment_terms_days'] ?? 0);
        if ($chosenTerms !== $otherTerms) {
            $reasons[] = "{$chosenTerms} days to pay against {$otherTerms}";
        }
        if ($chosen['shelf_life_months'] !== null) {
            $reasons[] = $chosen['shelf_life_months'].' months of shelf life offered';
        }
        $reasons[] = $suppliers[$chosen['supplier_id']]['record']['summary'];

        return array_values(array_filter($reasons));
    }

    /**
     * Why a cheaper quote was passed over.
     *
     * @param  array<string, mixed>  $cheaper
     * @param  array<string, mixed>  $chosen
     * @param  array<string, array<string, mixed>>  $suppliers
     */
    private function whyNot(array $cheaper, array $chosen, array $suppliers): string
    {
        $name = $cheaper['supplier_name'];
        $record = $suppliers[$cheaper['supplier_id']]['record'];

        if (! $cheaper['eligible']) {
            return $record['licence_status'] === 'EXPIRED'
                ? "{$name}'s licence expired on ".Carbon::parse($record['licence_expiry'])->format('j M Y')
                : "{$name} is ".strtolower((string) $record['status']).' and cannot be ordered from';
        }
        if (! $cheaper['quote_valid']) {
            return "{$name}'s quote expired on ".Carbon::parse($suppliers[$cheaper['supplier_id']]['valid_until'])->format('j M Y');
        }
        if (! $cheaper['full_quantity']) {
            return "{$name} can supply only ".$this->qty($cheaper['qty_available']);
        }

        return 'but scores higher overall ('.$chosen['scores']['total'].' against '.$cheaper['scores']['total'].')';
    }

    /**
     * @param  array<string, mixed>  $chosen
     * @param  array<string, mixed>|null  $other
     */
    private function leadPhrase(array $chosen, ?array $other): ?string
    {
        $days = $chosen['lead_time_days'];
        $unit = $days === 1 ? 'day' : 'days';
        if ($other === null) {
            return "delivers in {$days} {$unit}";
        }
        if ($days < $other['lead_time_days']) {
            return "delivers in {$days} {$unit} against {$other['lead_time_days']}";
        }
        if ($days > $other['lead_time_days']) {
            return "takes {$days} {$unit} to deliver against {$other['lead_time_days']}";
        }

        return null;
    }

    /**
     * Single supplier or split award, whichever serves better once delivery
     * charges are counted. One order wins unless splitting saves more than
     * SPLIT_SAVING_THRESHOLD_PCT.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, array<string, mixed>>  $suppliers
     * @return array<string, mixed>
     */
    private function overall(array $lines, array $suppliers): array
    {
        $plan = [];
        foreach ($lines as $line) {
            if ($line['recommendation']) {
                $plan[$line['rfq_line_id']] = $line['recommendation']['supplier_id'];
            }
        }

        $risks = [];
        $awaiting = array_values(array_filter($suppliers, fn ($s) => $s['quote_status'] === 'AWAITING'));
        if ($awaiting !== []) {
            $risks[] = ['code' => 'QUOTES_AWAITED', 'message' => count($awaiting).' invited supplier'.(count($awaiting) === 1 ? ' has' : 's have').' not quoted yet: '.implode(', ', array_column($awaiting, 'name')).'.'];
        }

        if ($plan === []) {
            return ['mode' => 'NONE', 'supplier_id' => null, 'supplier_name' => null, 'plan' => (object) [], 'total' => '0.0000',
                'reasons' => ['No usable quotes yet.'], 'summary' => 'No usable quotes yet.', 'risks' => $risks, 'unawarded_lines' => count($lines)];
        }

        $splitTotal = $this->planTotal($plan, $lines, $suppliers);
        $planSuppliers = array_values(array_unique($plan));
        $coveredLines = array_keys($plan);

        // Suppliers who could take every coverable line on their own, in full and on a valid quote.
        $singles = [];
        foreach ($suppliers as $supplierId => $supplier) {
            if (! $supplier['eligible'] || ! $supplier['quote_valid']) {
                continue;
            }
            $single = [];
            $scoreValue = 0.0;
            $value = 0.0;
            foreach ($lines as $line) {
                if (! in_array($line['rfq_line_id'], $coveredLines, true)) {
                    continue;
                }
                $quote = collect($line['quotes'])->firstWhere('supplier_id', $supplierId);
                if (! $quote || ! $quote['full_quantity']) {
                    continue 2;
                }
                $single[$line['rfq_line_id']] = $supplierId;
                $scoreValue += $quote['scores']['total'] * (float) $quote['line_total'];
                $value += (float) $quote['line_total'];
            }
            $singles[] = [
                'supplier_id' => $supplierId,
                'name' => $supplier['name'],
                'plan' => $single,
                'total' => $this->planTotal($single, $lines, $suppliers),
                'score' => $value > 0 ? round($scoreValue / $value, 1) : 0.0,
            ];
        }
        usort($singles, fn ($a, $b) => bccomp($a['total'], $b['total'], 4) ?: $b['score'] <=> $a['score']);
        $bestSingle = $singles[0] ?? null;

        $unawarded = count($lines) - count($plan);
        $tail = $unawarded > 0 ? ["{$unawarded} line".($unawarded === 1 ? ' has' : 's have').' no usable quote and stay unawarded'] : [];

        if (count($planSuppliers) === 1) {
            $name = $suppliers[$planSuppliers[0]]['name'];
            $reasons = array_merge(["{$name} is the best choice on every line", 'one order and one delivery for KES '.$this->money($splitTotal).' including delivery'], $tail);

            return $this->overallResult('SINGLE', $planSuppliers[0], $name, $plan, $splitTotal, $reasons, $risks, $suppliers, $unawarded);
        }

        if ($bestSingle) {
            $saving = bcsub($bestSingle['total'], $splitTotal, 4);
            $threshold = bcmul($splitTotal, (string) (self::SPLIT_SAVING_THRESHOLD_PCT / 100), 4);
            if (bccomp($saving, $threshold, 4) <= 0) {
                $reasons = bccomp($saving, '0', 4) <= 0
                    ? ['one order from '.$bestSingle['name'].' costs KES '.$this->money(bcmul($saving, '-1', 4)).' less than splitting, once delivery charges are counted']
                    : ['splitting would save only KES '.$this->money($saving).' ('.$this->pct($saving, $bestSingle['total']).'); one order from '.$bestSingle['name'].' is simpler and means one delivery'];
                $reasons[] = 'total KES '.$this->money($bestSingle['total']).' including delivery';

                return $this->overallResult('SINGLE', $bestSingle['supplier_id'], $bestSingle['name'], $bestSingle['plan'], $bestSingle['total'], array_merge($reasons, $tail), $risks, $suppliers, $unawarded);
            }

            $reasons = [
                'splitting across '.count($planSuppliers).' suppliers saves KES '.$this->money($saving).' ('.$this->pct($saving, $bestSingle['total']).') against the best single supplier, '.$bestSingle['name'].' (KES '.$this->money($bestSingle['total']).')',
                'total KES '.$this->money($splitTotal).' including each supplier\'s delivery charge',
            ];
        } else {
            $reasons = [
                'no single supplier quoted every line in full on a valid quote',
                'total KES '.$this->money($splitTotal).' including each supplier\'s delivery charge',
            ];
        }

        return $this->overallResult('SPLIT', null, null, $plan, $splitTotal, array_merge($reasons, $tail), $risks, $suppliers, $unawarded);
    }

    /**
     * @param  array<string, string>  $plan
     * @param  list<string>|array<int, string>  $reasons
     * @param  list<array{code: string, message: string}>  $risks
     * @param  array<string, array<string, mixed>>  $suppliers
     * @return array<string, mixed>
     */
    private function overallResult(string $mode, ?string $supplierId, ?string $supplierName, array $plan, string $total, array $reasons, array $risks, array $suppliers, int $unawarded): array
    {
        foreach (array_unique($plan) as $planSupplier) {
            foreach ($suppliers[$planSupplier]['risks'] as $risk) {
                $risks[] = $risk;
            }
        }

        return [
            'mode' => $mode,
            'supplier_id' => $supplierId,
            'supplier_name' => $supplierName,
            'plan' => (object) $plan,
            'total' => $total,
            'reasons' => array_values($reasons),
            'summary' => $this->sentence(array_values($reasons)),
            'risks' => array_values(array_unique($risks, SORT_REGULAR)),
            'unawarded_lines' => $unawarded,
        ];
    }

    /**
     * Goods plus one delivery charge per supplier used.
     *
     * @param  array<string, string>  $plan  rfq_line_id => supplier_id
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, array<string, mixed>>  $suppliers
     */
    private function planTotal(array $plan, array $lines, array $suppliers): string
    {
        $total = '0.0000';
        foreach ($lines as $line) {
            $supplierId = $plan[$line['rfq_line_id']] ?? null;
            if ($supplierId === null) {
                continue;
            }
            $quote = collect($line['quotes'])->firstWhere('supplier_id', $supplierId);
            $total = bcadd($total, (string) ($quote['line_total'] ?? '0'), 4);
        }
        foreach (array_unique($plan) as $supplierId) {
            $total = bcadd($total, $suppliers[$supplierId]['delivery_charge'], 4);
        }

        return $total;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function sentence(array $reasons): string
    {
        return ucfirst(implode('; ', $reasons)).'.';
    }

    private function money(string $value): string
    {
        return number_format((float) $value, 2);
    }

    private function qty(?string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
    }

    private function pct(string $part, string $whole): string
    {
        if (bccomp($whole, '0', 4) === 0) {
            return '0%';
        }

        return rtrim(rtrim(number_format(100 * (float) $part / (float) $whole, 1), '0'), '.').'%';
    }
}
