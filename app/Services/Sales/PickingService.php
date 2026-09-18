<?php

namespace App\Services\Sales;

use App\Models\AuditLog;
use App\Models\NumberSequence;
use App\Models\PickingList;
use App\Models\PickingListLine;
use App\Models\ProductBatch;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;

class InvalidPickingListStatusException extends \RuntimeException {}

/**
 * Part 6.9 / V6 order-management flow, stage 3 — the physical-execution
 * document. Batch allocation was already decided at order-confirm time
 * (FEFO-committed stock_reservations); picking confirms the physical
 * reality against that plan and records a shortfall if the shelf doesn't
 * match the reservation.
 */
class PickingService
{
    public function generate(SalesOrder $order): PickingList
    {
        if ($order->status !== 'CONFIRMED') {
            throw new InvalidPickingListStatusException("Sales order {$order->doc_number} is {$order->status}, not CONFIRMED.");
        }

        return DB::transaction(function () use ($order) {
            $pickingList = PickingList::create([
                'organisation_id' => $order->organisation_id,
                'branch_id' => $order->branch_id,
                'store_id' => $order->store_id,
                'sales_order_id' => $order->id,
                'doc_number' => NumberSequence::next($order->organisation_id, 'PICK', $order->branch_id, 'PK'),
                'status' => 'PENDING',
            ]);

            $lineIds = $order->lines()->pluck('id');

            $reservations = StockReservation::where('source_doc_type', 'sales_order_line')
                ->whereIn('source_doc_id', $lineIds)
                ->where('status', 'ACTIVE')
                ->orderBy('created_at')
                ->get();

            foreach ($reservations as $reservation) {
                PickingListLine::create([
                    'picking_list_id' => $pickingList->id,
                    'sales_order_line_id' => $reservation->source_doc_id,
                    'product_id' => $reservation->product_id,
                    'batch_id' => $reservation->batch_id,
                    'store_id' => $reservation->store_id,
                    'qty_to_pick_base' => $reservation->qty_base,
                    'unit_cost' => ProductBatch::whereKey($reservation->batch_id)->value('landed_unit_cost') ?? '0',
                    'status' => 'PENDING',
                ]);
            }

            $order->update(['status' => 'IN_PROGRESS']);

            return $pickingList->fresh(['lines']);
        });
    }

    public function start(PickingList $pickingList, int $pickerId): PickingList
    {
        if ($pickingList->status !== 'PENDING') {
            throw new InvalidPickingListStatusException("Picking list {$pickingList->doc_number} is {$pickingList->status}, not PENDING.");
        }

        $pickingList->update(['status' => 'IN_PROGRESS', 'assigned_picker_id' => $pickerId, 'started_at' => now()]);

        return $pickingList->fresh();
    }

    /**
     * Records the physically picked quantity for one line. Any shortfall
     * against the reservation is released back to free stock immediately —
     * it does not sit locked waiting for a backorder that doesn't exist yet.
     */
    public function completeLine(PickingListLine $line, string $qtyPicked): PickingListLine
    {
        if ($line->status !== 'PENDING') {
            throw new InvalidPickingListStatusException('This picking line has already been picked.');
        }

        if (bccomp($qtyPicked, (string) $line->qty_to_pick_base, 4) > 0) {
            throw new \InvalidArgumentException('Picked quantity cannot exceed the quantity reserved for this line.');
        }

        return DB::transaction(function () use ($line, $qtyPicked) {
            $shortfall = bcsub((string) $line->qty_to_pick_base, $qtyPicked, 4);

            if (bccomp($shortfall, '0', 4) > 0) {
                $this->releaseReservationShortfall($line, $shortfall);
            }

            $line->update([
                'qty_picked_base' => $qtyPicked,
                'status' => bccomp($shortfall, '0', 4) > 0 ? 'SHORT' : 'PICKED',
                'picked_at' => now(),
            ]);

            $salesOrderLine = $line->salesOrderLine;
            $salesOrderLine->update([
                'qty_picked_base' => bcadd((string) $salesOrderLine->qty_picked_base, $qtyPicked, 4),
            ]);

            return $line->fresh();
        });
    }

    public function complete(PickingList $pickingList): PickingList
    {
        $pending = $pickingList->lines()->where('status', 'PENDING')->exists();
        if ($pending) {
            throw new InvalidPickingListStatusException("Picking list {$pickingList->doc_number} still has unpicked lines.");
        }

        $pickingList->update(['status' => 'COMPLETED', 'completed_at' => now()]);

        AuditLog::record('PICKING_LIST_COMPLETED', 'picking_list', $pickingList->id, [
            'reference' => $pickingList->doc_number,
        ]);

        return $pickingList->fresh();
    }

    private function releaseReservationShortfall(PickingListLine $line, string $shortfall): void
    {
        $reservation = StockReservation::where('source_doc_type', 'sales_order_line')
            ->where('source_doc_id', $line->sales_order_line_id)
            ->where('batch_id', $line->batch_id)
            ->where('status', 'ACTIVE')
            ->first();

        $balance = StockBalance::query()
            ->where('product_id', $line->product_id)->where('batch_id', $line->batch_id)->where('store_id', $line->store_id)
            ->lockForUpdate()->firstOrFail();

        $balance->qty_reserved = bcsub((string) $balance->qty_reserved, $shortfall, 4);
        $balance->save();

        if ($reservation) {
            $remaining = bcsub((string) $reservation->qty_base, $shortfall, 4);
            if (bccomp($remaining, '0', 4) <= 0) {
                $reservation->update(['status' => 'RELEASED', 'qty_base' => '0']);
            } else {
                $reservation->update(['qty_base' => $remaining]);
            }
        }
    }
}
