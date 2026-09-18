<?php

namespace App\Services\Inventory;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\NumberSequence;
use App\Models\ProductBatch;
use App\Models\StockBalance;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class InvalidTransferStatusException extends \RuntimeException {}

/**
 * Part 7.6 — transfers with IN_TRANSIT.
 *
 * DRAFT → APPROVED → DISPATCHED (ledger OUT at source; the quantity is in
 * transit: it belongs to the organisation but to no store) → RECEIVED
 * (ledger IN at destination) or DISCREPANCY when received ≠ dispatched,
 * which is then resolved by a ledger-true correction. No journal: both
 * stores belong to the same legal entity (Part 12.3).
 */
class StockTransferService
{
    public function __construct(private readonly StockLedgerService $ledger) {}

    /**
     * @param  array{from_store_id: string, to_store_id: string, user_id: int, lines: list<array{product_id: string, batch_id: string, qty_base: string}>}  $data
     */
    public function create(array $data): StockTransfer
    {
        if ($data['from_store_id'] === $data['to_store_id']) {
            throw new \InvalidArgumentException('A transfer needs two different stores.');
        }

        return DB::transaction(function () use ($data) {
            $from = Store::findOrFail($data['from_store_id']);
            $organisationId = (string) Branch::whereKey($from->branch_id)->value('organisation_id');

            $transfer = StockTransfer::create([
                'doc_number' => NumberSequence::next($organisationId, 'TRANSFER', $from->branch_id, 'TRF'),
                'from_store_id' => $from->id,
                'to_store_id' => $data['to_store_id'],
                'status' => 'DRAFT',
                'requested_by' => $data['user_id'],
            ]);

            foreach ($data['lines'] as $line) {
                $batch = ProductBatch::findOrFail($line['batch_id']);
                if ($batch->product_id !== $line['product_id']) {
                    throw new \InvalidArgumentException("Batch {$batch->batch_number} does not belong to the product on that line.");
                }
                if (bccomp((string) $line['qty_base'], '0', 4) <= 0) {
                    throw new \InvalidArgumentException('A transfer line quantity must be positive.');
                }

                StockTransferLine::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $line['product_id'],
                    'batch_id' => $batch->id,
                    'qty_dispatched' => bcadd((string) $line['qty_base'], '0', 4),
                ]);
            }

            AuditLog::record('TRANSFER_CREATED', 'stock_transfer', $transfer->id, [
                'user_id' => $data['user_id'], 'branch_id' => $from->branch_id, 'reference' => $transfer->doc_number,
            ]);

            return $transfer->fresh(['lines']);
        });
    }

    public function approve(StockTransfer $transfer, int $approverId): StockTransfer
    {
        $this->assertStatus($transfer, 'DRAFT');
        $transfer->update(['status' => 'APPROVED', 'approved_by' => $approverId]);

        AuditLog::record('TRANSFER_APPROVED', 'stock_transfer', $transfer->id, ['user_id' => $approverId, 'reference' => $transfer->doc_number]);

        return $transfer->fresh(['lines']);
    }

    /**
     * Stock leaves the source store now. Until it is received it is in the
     * "in transit" bucket of the eight quantity states (Part 7.3).
     */
    public function dispatch(StockTransfer $transfer, int $userId): StockTransfer
    {
        $this->assertStatus($transfer, 'APPROVED');

        return DB::transaction(function () use ($transfer, $userId) {
            $from = Store::findOrFail($transfer->from_store_id);

            foreach ($transfer->lines()->with('batch')->get() as $line) {
                $balance = StockBalance::query()
                    ->where('product_id', $line->product_id)->where('batch_id', $line->batch_id)->where('store_id', $from->id)
                    ->lockForUpdate()->first();

                $movable = $balance ? bcsub((string) $balance->qty_on_hand, (string) $balance->qty_reserved, 4) : '0.0000';
                if (bccomp($movable, (string) $line->qty_dispatched, 4) < 0) {
                    throw new InsufficientStockException((string) $line->qty_dispatched, $movable, bcsub((string) $line->qty_dispatched, $movable, 4));
                }

                $this->ledger->post([
                    'txn_type' => 'TRANSFER_OUT',
                    'product_id' => $line->product_id,
                    'batch_id' => $line->batch_id,
                    'store_id' => $from->id,
                    'qty_base' => bcmul((string) $line->qty_dispatched, '-1', 4),
                    'unit_cost' => (string) $line->batch->landed_unit_cost,
                    'source_doc_type' => 'stock_transfer',
                    'source_doc_id' => $transfer->id,
                    'source_doc_line_id' => $line->id,
                    'user_id' => $userId,
                    'branch_id' => $from->branch_id,
                ]);
            }

            $transfer->update(['status' => 'DISPATCHED', 'dispatched_at' => now()]);

            AuditLog::record('TRANSFER_DISPATCHED', 'stock_transfer', $transfer->id, [
                'user_id' => $userId, 'branch_id' => $from->branch_id, 'reference' => $transfer->doc_number,
            ]);

            return $transfer->fresh(['lines']);
        });
    }

    /**
     * @param  array<string, string>  $received  line id => qty received (lines omitted count as received in full)
     */
    public function receive(StockTransfer $transfer, int $userId, array $received = []): StockTransfer
    {
        $this->assertStatus($transfer, 'DISPATCHED');

        return DB::transaction(function () use ($transfer, $userId, $received) {
            $to = Store::findOrFail($transfer->to_store_id);
            $discrepancy = false;

            foreach ($transfer->lines()->with('batch')->get() as $line) {
                $qty = bcadd((string) ($received[$line->id] ?? $line->qty_dispatched), '0', 4);
                if (bccomp($qty, '0', 4) < 0 || bccomp($qty, (string) $line->qty_dispatched, 4) > 0) {
                    throw new \InvalidArgumentException("Received quantity for {$line->batch->batch_number} must be between 0 and {$line->qty_dispatched}.");
                }

                if (bccomp($qty, '0', 4) > 0) {
                    $this->ledger->post([
                        'txn_type' => 'TRANSFER_IN',
                        'product_id' => $line->product_id,
                        'batch_id' => $line->batch_id,
                        'store_id' => $to->id,
                        'qty_base' => $qty,
                        'unit_cost' => (string) $line->batch->landed_unit_cost,
                        'source_doc_type' => 'stock_transfer',
                        'source_doc_id' => $transfer->id,
                        'source_doc_line_id' => $line->id,
                        'user_id' => $userId,
                        'branch_id' => $to->branch_id,
                    ]);
                }

                $line->update(['qty_received' => $qty]);
                if (bccomp($qty, (string) $line->qty_dispatched, 4) !== 0) {
                    $discrepancy = true;
                }
            }

            $transfer->update(['status' => $discrepancy ? 'DISCREPANCY' : 'RECEIVED', 'received_at' => now()]);

            AuditLog::record($discrepancy ? 'TRANSFER_DISCREPANCY' : 'TRANSFER_RECEIVED', 'stock_transfer', $transfer->id, [
                'user_id' => $userId, 'branch_id' => $to->branch_id, 'reference' => $transfer->doc_number,
            ]);

            return $transfer->fresh(['lines']);
        });
    }

    /**
     * Part 7.6 "variance investigation". The shortfall is still in transit
     * until someone says where it went; every resolution is a ledger row:
     *  - FOUND_AT_SOURCE: it never left; TRANSFER_IN back to the source store.
     *  - FOUND_AT_DESTINATION: late arrival; TRANSFER_IN at the destination.
     *  - LOST: TRANSFER_IN at the destination followed by a reasoned stock
     *    adjustment there, which carries the usual approval threshold.
     */
    public function resolveDiscrepancy(StockTransfer $transfer, int $userId, string $resolution, string $reason, ?string $adjustmentReasonCode = null): StockTransfer
    {
        $this->assertStatus($transfer, 'DISCREPANCY');
        if (! in_array($resolution, ['FOUND_AT_SOURCE', 'FOUND_AT_DESTINATION', 'LOST'], true)) {
            throw new \InvalidArgumentException("Unknown discrepancy resolution {$resolution}.");
        }

        return DB::transaction(function () use ($transfer, $userId, $resolution, $reason, $adjustmentReasonCode) {
            $store = Store::findOrFail($resolution === 'FOUND_AT_SOURCE' ? $transfer->from_store_id : $transfer->to_store_id);
            $adjustmentLines = [];

            foreach ($transfer->lines()->with('batch')->get() as $line) {
                $shortfall = bcsub((string) $line->qty_dispatched, (string) ($line->qty_received ?? '0'), 4);
                if (bccomp($shortfall, '0', 4) <= 0) {
                    continue;
                }

                $this->ledger->post([
                    'txn_type' => 'TRANSFER_IN',
                    'product_id' => $line->product_id,
                    'batch_id' => $line->batch_id,
                    'store_id' => $store->id,
                    'qty_base' => $shortfall,
                    'unit_cost' => (string) $line->batch->landed_unit_cost,
                    'source_doc_type' => 'stock_transfer',
                    'source_doc_id' => $transfer->id,
                    'source_doc_line_id' => $line->id,
                    'user_id' => $userId,
                    'branch_id' => $store->branch_id,
                ]);

                if ($resolution === 'LOST') {
                    $adjustmentLines[] = ['product_id' => $line->product_id, 'batch_id' => $line->batch_id, 'qty_base' => bcmul($shortfall, '-1', 4)];
                }
            }

            if ($adjustmentLines !== []) {
                app(StockAdjustmentService::class)->create([
                    'store_id' => $store->id,
                    'reason_code' => $adjustmentReasonCode ?? 'CORRECTION_OF_ERROR',
                    'notes' => "Transfer {$transfer->doc_number} shortfall: {$reason}",
                    'user_id' => $userId,
                    'lines' => $adjustmentLines,
                ]);
            }

            $transfer->update(['status' => 'RECEIVED']);

            AuditLog::record('TRANSFER_DISCREPANCY_RESOLVED', 'stock_transfer', $transfer->id, [
                'user_id' => $userId, 'branch_id' => $store->branch_id, 'reference' => $transfer->doc_number,
                'reason' => "{$resolution}: {$reason}",
            ]);

            return $transfer->fresh(['lines']);
        });
    }

    private function assertStatus(StockTransfer $transfer, string $expected): void
    {
        if ($transfer->status !== $expected) {
            throw new InvalidTransferStatusException("Transfer {$transfer->doc_number} is {$transfer->status}, not {$expected}.");
        }
    }
}
