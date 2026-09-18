<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Store;
use App\Services\Pricing\Money;
use Illuminate\Support\Facades\DB;

/**
 * @property-read string $batchId
 */
final class FefoAllocation
{
    public function __construct(
        public readonly string $batchId,
        public readonly string $qty,
        public readonly string $unitCost,
    ) {}
}

/**
 * Part 7.4 — First-Expired, First-Out batch allocation. Must run inside the
 * same database transaction as the checkout/GRN/whatever posts the ledger
 * rows, since it takes row locks that need to hold until the ledger write.
 */
class FefoAllocator
{
    /**
     * @return list<FefoAllocation>
     *
     * @throws InsufficientStockException
     */
    public function allocate(
        Product $product,
        Store $store,
        string $qtyNeededBase,
        bool $packIntegrityRequired = false,
        ?int $saleUomFactor = null,
        int $minShelfLifeDays = 90,
    ): array {
        $cutoff = now()->addDays($minShelfLifeDays)->toDateString();

        $candidates = DB::table('stock_balances as sb')
            ->join('product_batches as pb', 'pb.id', '=', 'sb.batch_id')
            ->where('sb.product_id', $product->id)
            ->where('sb.store_id', $store->id)
            ->where('pb.status', 'RELEASED')
            ->where('pb.expiry_date', '>', $cutoff)
            ->orderBy('pb.expiry_date')
            ->orderBy('pb.created_at')
            ->orderBy('pb.id')
            // Part 9.5 / 4.3 — COGS is taken at the batch's LANDED cost, so
            // freight and clearing are never silently excluded from margin.
            ->select('sb.id as balance_id', 'pb.id as batch_id', 'pb.landed_unit_cost as unit_cost')
            ->get();

        $allocations = [];
        $remaining = $qtyNeededBase;

        foreach ($candidates as $candidate) {
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }

            /** @var StockBalance $balance */
            $balance = StockBalance::query()->whereKey($candidate->balance_id)->lockForUpdate()->firstOrFail();
            $freeToSell = $balance->freeToSell();

            if (bccomp($freeToSell, '0', 4) <= 0) {
                continue;
            }

            $take = bccomp($freeToSell, $remaining, 4) < 0 ? $freeToSell : $remaining;

            if ($packIntegrityRequired && $saleUomFactor) {
                $units = bcdiv($take, (string) $saleUomFactor, 0); // floor
                $take = bcmul($units, (string) $saleUomFactor, 4);
            }

            if (bccomp($take, '0', 4) > 0) {
                $allocations[] = new FefoAllocation((string) $candidate->batch_id, $take, (string) $candidate->unit_cost);
                $remaining = bcsub($remaining, $take, 4);
            }
        }

        if (bccomp($remaining, '0', 4) > 0) {
            $available = bcsub($qtyNeededBase, $remaining, 4);
            throw new InsufficientStockException($qtyNeededBase, $available, $remaining);
        }

        return $allocations;
    }

    /**
     * Part 7.4 FEFO override — an authorised user picks a specific batch
     * (e.g. to match a customer's earlier delivery). The batch must still be
     * sellable; the override itself is audited by the caller.
     *
     * @return list<FefoAllocation>
     *
     * @throws InsufficientStockException
     */
    public function allocateFromBatch(Product $product, Store $store, string $batchId, string $qtyNeededBase): array
    {
        $candidate = DB::table('stock_balances as sb')
            ->join('product_batches as pb', 'pb.id', '=', 'sb.batch_id')
            ->where('sb.product_id', $product->id)
            ->where('sb.store_id', $store->id)
            ->where('pb.id', $batchId)
            ->select('sb.id as balance_id', 'pb.id as batch_id', 'pb.landed_unit_cost as unit_cost', 'pb.status', 'pb.batch_number')
            ->first();

        if (! $candidate) {
            throw new InsufficientStockException($qtyNeededBase, '0.0000', $qtyNeededBase);
        }
        if ($candidate->status !== 'RELEASED') {
            throw new BatchNotSellableException($candidate->batch_number, $candidate->status);
        }

        /** @var StockBalance $balance */
        $balance = StockBalance::query()->whereKey($candidate->balance_id)->lockForUpdate()->firstOrFail();
        $freeToSell = $balance->freeToSell();

        if (bccomp($freeToSell, $qtyNeededBase, 4) < 0) {
            throw new InsufficientStockException($qtyNeededBase, Money::max($freeToSell, '0.0000'), bcsub($qtyNeededBase, Money::max($freeToSell, '0.0000'), 4));
        }

        return [new FefoAllocation((string) $candidate->batch_id, $qtyNeededBase, (string) $candidate->unit_cost)];
    }
}

class BatchNotSellableException extends \RuntimeException
{
    public function __construct(public readonly string $batchNumber, public readonly string $status)
    {
        parent::__construct("Batch {$batchNumber} is {$status}, not RELEASED — it cannot be sold.");
    }
}
