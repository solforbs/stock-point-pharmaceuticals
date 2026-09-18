<?php

namespace App\Services\Pricing;

use Illuminate\Support\Facades\DB;

/**
 * Part 4.3 decision: price on WAC (stable for customers), report margin on
 * batch cost (exact for the owner). This is the WAC side — the cost basis
 * every pricing factor and margin floor runs on, per base unit.
 */
class CostBasis
{
    public function weightedAverageCost(string $productId, string $storeId): ?string
    {
        $row = DB::table('stock_balances')
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->where('qty_on_hand', '>', 0)
            ->selectRaw('SUM(qty_on_hand * wac) as total_value, SUM(qty_on_hand) as total_qty')
            ->first();

        $totalQty = (string) ($row->total_qty ?? '0');
        if (bccomp($totalQty, '0', 4) <= 0) {
            return null;
        }

        return bcdiv((string) $row->total_value, $totalQty, 4);
    }
}
