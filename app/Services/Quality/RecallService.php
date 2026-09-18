<?php

namespace App\Services\Quality;

use App\Models\AuditLog;
use App\Models\NumberSequence;
use App\Models\ProductBatch;
use App\Models\Recall;
use App\Models\RecallBatch;
use App\Models\RecallCustomer;
use App\Models\StockBalance;
use App\Models\StockReservation;
use App\Services\Inventory\BatchQualityService;
use Illuminate\Support\Facades\DB;

class InvalidRecallStatusException extends \RuntimeException {}

/**
 * Part 8.4 — the score-10 capability.
 *
 * INITIATED → SCOPED (affected stock and customers computed from the batch
 * register and sale_line_batch_allocation) → BLOCKED (batches RECALLED,
 * reservations released, POS refuses them org-wide) → NOTIFIED → RECOVERING
 * (customer returns logged against the recall) → RECONCILED (sold vs
 * recovered vs outstanding) → DISPOSITIONED (return to supplier / destroy)
 * → CLOSED with effectiveness = recovered ÷ distributed × 100.
 */
class RecallService
{
    public function __construct(private readonly BatchQualityService $quality) {}

    /**
     * @param  array{organisation_id: string, source: string, external_reference?: ?string, reason: string, user_id: int, batch_ids: list<string>}  $data
     */
    public function initiate(array $data): Recall
    {
        return DB::transaction(function () use ($data) {
            $recall = Recall::create([
                'organisation_id' => $data['organisation_id'],
                'doc_number' => NumberSequence::next($data['organisation_id'], 'RECALL', null, 'RCL'),
                'status' => 'INITIATED',
                'source' => $data['source'],
                'external_reference' => $data['external_reference'] ?? null,
                'reason' => $data['reason'],
                'initiated_by' => $data['user_id'],
                'initiated_at' => now(),
            ]);

            foreach (array_unique($data['batch_ids']) as $batchId) {
                $batch = ProductBatch::where('organisation_id', $data['organisation_id'])->findOrFail($batchId);
                RecallBatch::create(['recall_id' => $recall->id, 'product_id' => $batch->product_id, 'batch_id' => $batch->id, 'status_before' => $batch->status]);
            }

            AuditLog::record('RECALL_INITIATED', 'recall', $recall->id, [
                'user_id' => $data['user_id'], 'reference' => $recall->doc_number, 'reason' => $data['reason'],
            ]);

            return $this->scope($recall, $data['user_id']);
        });
    }

    /**
     * Question 1: where is the stock? Question 2: who received it? Both are
     * answered from posted data, never estimated.
     */
    public function scope(Recall $recall, int $userId): Recall
    {
        $this->assertStatus($recall, ['INITIATED', 'SCOPED']);

        return DB::transaction(function () use ($recall) {
            $recall->customers()->delete();
            $customers = [];

            foreach ($recall->batches as $rb) {
                $onHand = (string) StockBalance::where('batch_id', $rb->batch_id)->sum('qty_on_hand');
                $inTransit = (string) DB::table('stock_transfer_lines as l')
                    ->join('stock_transfers as t', 't.id', '=', 'l.stock_transfer_id')
                    ->where('l.batch_id', $rb->batch_id)->whereIn('t.status', ['DISPATCHED', 'DISCREPANCY'])
                    ->selectRaw('COALESCE(SUM(l.qty_dispatched - COALESCE(l.qty_received, 0)), 0) as qty')->value('qty');

                $distributed = DB::table('sale_line_batch_allocations as a')
                    ->join('sale_lines as l', 'l.id', '=', 'a.sale_line_id')
                    ->join('sales as s', 's.id', '=', 'l.sale_id')
                    ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
                    ->where('a.batch_id', $rb->batch_id)->where('s.status', 'POSTED')
                    ->groupBy('s.customer_id', 'c.name', 'c.phone', 'c.email')
                    ->selectRaw('s.customer_id, c.name, c.phone, c.email, SUM(a.qty_base) as qty')
                    ->get();

                $total = '0.0000';
                foreach ($distributed as $row) {
                    $qty = number_format((float) $row->qty, 4, '.', '');
                    $total = bcadd($total, $qty, 4);
                    $key = $row->customer_id ?? 'WALK_IN';
                    $customers[$key] ??= ['customer_id' => $row->customer_id, 'name' => $row->name ?? 'Walk-in customers', 'contact' => trim(($row->phone ?? '').' '.($row->email ?? '')) ?: null, 'qty' => '0.0000'];
                    $customers[$key]['qty'] = bcadd($customers[$key]['qty'], $qty, 4);
                }

                $rb->update([
                    'on_hand_at_scope' => number_format((float) $onHand, 4, '.', ''),
                    'in_transit_at_scope' => number_format((float) $inTransit, 4, '.', ''),
                    'distributed_qty' => $total,
                ]);
            }

            foreach ($customers as $c) {
                RecallCustomer::create([
                    'recall_id' => $recall->id, 'customer_id' => $c['customer_id'], 'customer_name_snapshot' => $c['name'],
                    'contact_snapshot' => $c['contact'], 'qty_distributed' => $c['qty'],
                ]);
            }

            $recall->update(['status' => 'SCOPED']);

            return $recall->fresh(['batches', 'customers']);
        });
    }

    /** Stock is frozen immediately: batches become RECALLED and open reservations on them are released. */
    public function block(Recall $recall, int $userId): Recall
    {
        $this->assertStatus($recall, ['SCOPED']);

        return DB::transaction(function () use ($recall, $userId) {
            foreach ($recall->batches as $rb) {
                $batch = ProductBatch::findOrFail($rb->batch_id);
                $rb->update(['status_before' => $batch->status]);

                if ($batch->status === 'PENDING_QC') {
                    $batch = $this->quality->quarantine($batch, $userId, "Recall {$recall->doc_number}");
                }
                if (in_array($batch->status, ['RELEASED', 'QUARANTINED'], true)) {
                    $this->quality->transition($batch, 'RECALLED', $userId, "Recall {$recall->doc_number}: {$recall->reason}");
                }

                foreach (StockReservation::where('batch_id', $rb->batch_id)->where('status', 'ACTIVE')->lockForUpdate()->get() as $reservation) {
                    $balance = StockBalance::where('product_id', $reservation->product_id)->where('batch_id', $reservation->batch_id)->where('store_id', $reservation->store_id)->lockForUpdate()->first();
                    if ($balance) {
                        $balance->update(['qty_reserved' => bcsub((string) $balance->qty_reserved, (string) $reservation->qty_base, 4)]);
                    }
                    $reservation->update(['status' => 'RELEASED']);
                }
            }

            $recall->update(['status' => 'BLOCKED', 'blocked_at' => now()]);
            AuditLog::record('RECALL_BLOCKED', 'recall', $recall->id, ['user_id' => $userId, 'reference' => $recall->doc_number]);

            return $recall->fresh(['batches', 'customers']);
        });
    }

    /**
     * Marks every affected customer as notified and returns the contact
     * list the SPA prints as letters. The reference is the batch of letters
     * or the SMS campaign id, whatever the business used.
     */
    public function notify(Recall $recall, int $userId, ?string $reference = null): Recall
    {
        $this->assertStatus($recall, ['BLOCKED', 'NOTIFIED']);
        $recall->customers()->whereNull('notified_at')->update(['notified_at' => now(), 'notification_reference' => $reference]);
        $recall->update(['status' => 'NOTIFIED']);
        AuditLog::record('RECALL_NOTIFIED', 'recall', $recall->id, ['user_id' => $userId, 'reference' => $recall->doc_number, 'after_json' => ['notification_reference' => $reference]]);

        return $recall->fresh(['batches', 'customers']);
    }

    /** Called by the customer-return posting when the return cites this recall. */
    public function recordRecovery(Recall $recall, string $batchId, ?string $customerId, string $qty): void
    {
        $this->assertStatus($recall, ['BLOCKED', 'NOTIFIED', 'RECOVERING']);

        $rb = RecallBatch::where('recall_id', $recall->id)->where('batch_id', $batchId)->first();
        if (! $rb) {
            throw new \DomainException("Batch is not within the scope of recall {$recall->doc_number}.");
        }
        $rb->update(['recovered_qty' => bcadd((string) $rb->recovered_qty, $qty, 4)]);

        $rc = RecallCustomer::where('recall_id', $recall->id)->where('customer_id', $customerId)->first();
        if ($rc) {
            $rc->update(['qty_recovered' => bcadd((string) $rc->qty_recovered, $qty, 4)]);
        }

        $recall->update(['status' => 'RECOVERING']);
    }

    /** Called by waste disposal / supplier return postings that cite this recall. */
    public function recordDisposal(Recall $recall, string $batchId, string $qty): void
    {
        $rb = RecallBatch::where('recall_id', $recall->id)->where('batch_id', $batchId)->first();
        if (! $rb) {
            throw new \DomainException("Batch is not within the scope of recall {$recall->doc_number}.");
        }
        $rb->update(['disposed_qty' => bcadd((string) $rb->disposed_qty, $qty, 4)]);
    }

    public function reconcile(Recall $recall, int $userId): Recall
    {
        $this->assertStatus($recall, ['BLOCKED', 'NOTIFIED', 'RECOVERING', 'RECONCILED']);
        $recall->update(['status' => 'RECONCILED', 'effectiveness_pct' => $this->effectiveness($recall)]);
        AuditLog::record('RECALL_RECONCILED', 'recall', $recall->id, ['user_id' => $userId, 'reference' => $recall->doc_number, 'after_json' => ['effectiveness_pct' => $recall->effectiveness_pct]]);

        return $recall->fresh(['batches', 'customers']);
    }

    public function disposition(Recall $recall, int $userId, string $disposition): Recall
    {
        $this->assertStatus($recall, ['RECONCILED', 'DISPOSITIONED']);
        if (! in_array($disposition, ['RETURN_TO_SUPPLIER', 'DESTROY'], true)) {
            throw new \InvalidArgumentException("Unknown recall disposition {$disposition}.");
        }
        $recall->update(['status' => 'DISPOSITIONED', 'disposition' => $disposition]);
        AuditLog::record('RECALL_DISPOSITIONED', 'recall', $recall->id, ['user_id' => $userId, 'reference' => $recall->doc_number, 'after_json' => ['disposition' => $disposition]]);

        return $recall->fresh(['batches', 'customers']);
    }

    public function close(Recall $recall, int $userId): Recall
    {
        $this->assertStatus($recall, ['DISPOSITIONED']);
        $recall->update(['status' => 'CLOSED', 'closed_at' => now(), 'closed_by' => $userId, 'effectiveness_pct' => $this->effectiveness($recall)]);
        AuditLog::record('RECALL_CLOSED', 'recall', $recall->id, ['user_id' => $userId, 'reference' => $recall->doc_number, 'after_json' => ['effectiveness_pct' => $recall->effectiveness_pct]]);

        return $recall->fresh(['batches', 'customers']);
    }

    /**
     * The traceability answer, live: stock by store per batch, in transit,
     * customers, recovered and outstanding quantities.
     *
     * @return array<string, mixed>
     */
    public function trace(Recall $recall): array
    {
        $batches = [];
        foreach ($recall->batches()->with(['batch:id,batch_number,expiry_date,status', 'product:id,code,name'])->get() as $rb) {
            $stock = StockBalance::where('batch_id', $rb->batch_id)->with('store:id,code,name')->get()
                ->map(fn (StockBalance $b) => ['store_id' => $b->store_id, 'store_code' => $b->store?->code, 'on_hand' => (string) $b->qty_on_hand]);
            $batches[] = $rb->toArray() + [
                'stock_by_store' => $stock,
                'on_hand_now' => number_format((float) $stock->sum(fn ($s) => (float) $s['on_hand']), 4, '.', ''),
                'outstanding_qty' => bcsub((string) $rb->distributed_qty, (string) $rb->recovered_qty, 4),
            ];
        }

        return [
            'recall' => $recall->only(['id', 'doc_number', 'status', 'source', 'external_reference', 'reason', 'disposition', 'effectiveness_pct', 'initiated_at', 'blocked_at', 'closed_at']),
            'batches' => $batches,
            'customers' => $recall->customers()->orderByDesc('qty_distributed')->get(),
            'distributed_qty' => $this->sum($recall, 'distributed_qty'),
            'recovered_qty' => $this->sum($recall, 'recovered_qty'),
            'disposed_qty' => $this->sum($recall, 'disposed_qty'),
            'effectiveness_pct' => $this->effectiveness($recall),
        ];
    }

    private function effectiveness(Recall $recall): ?string
    {
        $distributed = $this->sum($recall, 'distributed_qty');
        if (bccomp($distributed, '0', 4) <= 0) {
            return null;
        }

        return number_format((float) bcdiv(bcmul($this->sum($recall, 'recovered_qty'), '100', 6), $distributed, 6), 2, '.', '');
    }

    private function sum(Recall $recall, string $column): string
    {
        return number_format((float) RecallBatch::where('recall_id', $recall->id)->sum($column), 4, '.', '');
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertStatus(Recall $recall, array $expected): void
    {
        if (! in_array($recall->status, $expected, true)) {
            throw new InvalidRecallStatusException("Recall {$recall->doc_number} is {$recall->status}, not ".implode('/', $expected).'.');
        }
    }
}
