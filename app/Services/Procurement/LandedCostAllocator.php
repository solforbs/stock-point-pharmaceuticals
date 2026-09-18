<?php

namespace App\Services\Procurement;

use App\Models\LandedCost;
use App\Models\LandedCostAllocation;
use Illuminate\Support\Facades\DB;

/**
 * Part 9.5 — allocates freight/clearing/insurance/duty (net of supplier
 * rebate) across a shipment's GRN lines, by value (default), weight, volume
 * or quantity, and folds the result into each line's landed_unit_cost.
 */
class LandedCostAllocator
{
    public function allocate(LandedCost $landedCost): void
    {
        DB::transaction(function () use ($landedCost) {
            $lines = $landedCost->goodsReceipt->lines;
            $totalToAllocate = $landedCost->totalToAllocate();

            $basis = match ($landedCost->allocation_basis) {
                // Weight/volume require product master-data fields this
                // schema does not yet carry — falls back to value, which is
                // also the blueprint's documented default.
                'QUANTITY' => fn ($line) => (string) $line->qty_accepted,
                default => fn ($line) => bcmul((string) $line->qty_accepted, (string) $line->unit_cost, 4),
            };

            $lineBases = $lines->mapWithKeys(fn ($line) => [$line->id => $basis($line)]);
            $totalBasis = $lineBases->reduce(fn ($carry, $v) => bcadd($carry, $v, 4), '0');

            foreach ($lines as $line) {
                $share = bccomp($totalBasis, '0', 4) > 0
                    ? bcdiv($lineBases[$line->id], $totalBasis, 6)
                    : '0';
                $allocated = bcmul($totalToAllocate, $share, 4);

                LandedCostAllocation::create([
                    'landed_cost_id' => $landedCost->id,
                    'goods_receipt_line_id' => $line->id,
                    'allocated_amount' => $allocated,
                ]);

                $extraPerUnit = bccomp((string) $line->qty_accepted, '0', 4) > 0
                    ? bcdiv($allocated, (string) $line->qty_accepted, 4)
                    : '0';
                $landedUnitCost = bcadd((string) $line->unit_cost, $extraPerUnit, 4);

                $line->update(['landed_unit_cost' => $landedUnitCost]);
                // The batch holds cost per base unit; the line holds it per purchase UOM.
                $line->batch?->update(['landed_unit_cost' => $line->fresh()->landedUnitCostBase()]);
            }
        });
    }
}
