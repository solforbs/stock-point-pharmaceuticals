<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

/**
 * Part 7.3 — the eight quantity states, computable at any time from the
 * ledger-derived balances and the batch register. The POS only ever sees
 * free_to_sell; the reorder engine sees free_to_sell + on_order; valuation
 * sees on_hand.
 */
class InventoryReport
{
    /**
     * @param  list<string>|null  $categoryIds  a category and its sub-categories; null for all
     * @return list<array<string, mixed>>
     */
    public function stockStates(string $organisationId, ?string $productId = null, ?string $storeId = null, ?string $search = null, ?array $categoryIds = null): array
    {
        $today = now()->toDateString();

        $rows = DB::table('stock_balances as b')
            ->join('product_batches as pb', 'pb.id', '=', 'b.batch_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('stores as s', 's.id', '=', 'b.store_id')
            ->leftJoin('product_categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.organisation_id', $organisationId)
            ->when($categoryIds !== null, fn ($q) => $q->whereIn('p.category_id', $categoryIds))
            ->when($productId, fn ($q) => $q->where('b.product_id', $productId))
            ->when($storeId, fn ($q) => $q->where('b.store_id', $storeId))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('p.name', 'like', "%{$search}%")->orWhere('p.code', 'like', "%{$search}%")))
            ->select([
                'b.product_id', 'p.code as product_code', 'p.name as product_name', 'p.reorder_point', 'p.category_id', 'c.name as category_name',
                'b.store_id', 's.code as store_code', 's.name as store_name', 's.branch_id',
                'b.batch_id', 'pb.batch_number', 'pb.expiry_date', 'pb.status', 'b.qty_on_hand', 'b.qty_reserved', 'b.qty_quarantined', 'b.wac',
            ])
            ->orderBy('p.name')->orderBy('s.code')->orderBy('pb.expiry_date')
            ->get();

        $onOrder = $this->onOrderByProductAndBranch($organisationId);
        $inTransit = $this->inTransitByProductAndStore();

        $grouped = [];
        foreach ($rows as $row) {
            $key = $row->product_id.'|'.$row->store_id;
            $g = $grouped[$key] ?? [
                'product_id' => $row->product_id, 'product_code' => $row->product_code, 'product_name' => $row->product_name,
                'category_id' => $row->category_id, 'category_name' => $row->category_name,
                'store_id' => $row->store_id, 'store_code' => $row->store_code, 'store_name' => $row->store_name,
                'on_hand' => '0.0000', 'reserved' => '0.0000', 'free_to_sell' => '0.0000', 'in_transit' => '0.0000',
                'pending_qc' => '0.0000', 'quarantined' => '0.0000', 'expired' => '0.0000', 'recalled' => '0.0000',
                'on_order' => $onOrder[$row->product_id.'|'.$row->branch_id] ?? '0.0000',
                'value_at_cost' => '0.0000', 'nearest_expiry' => null, 'reorder_point' => (string) $row->reorder_point,
                'batches' => [],
            ];

            $qty = (string) $row->qty_on_hand;
            $isExpired = $row->status === 'EXPIRED' || $row->expiry_date < $today;
            $g['on_hand'] = bcadd($g['on_hand'], $qty, 4);
            $g['reserved'] = bcadd($g['reserved'], (string) $row->qty_reserved, 4);
            $g['value_at_cost'] = bcadd($g['value_at_cost'], bcmul($qty, (string) $row->wac, 4), 4);

            if ($isExpired) {
                $g['expired'] = bcadd($g['expired'], $qty, 4);
            } elseif ($row->status === 'RECALLED') {
                $g['recalled'] = bcadd($g['recalled'], $qty, 4);
            } elseif ($row->status === 'QUARANTINED') {
                $g['quarantined'] = bcadd($g['quarantined'], $qty, 4);
            } elseif ($row->status === 'PENDING_QC') {
                $g['pending_qc'] = bcadd($g['pending_qc'], $qty, 4);
            } elseif ($row->status === 'RELEASED') {
                // Units held back on an otherwise released batch (a customer
                // return awaiting a pharmacist, Part 11.1) are quarantined,
                // not free to sell.
                $held = (string) $row->qty_quarantined;
                if (bccomp($held, '0', 4) > 0) {
                    $g['quarantined'] = bcadd($g['quarantined'], $held, 4);
                }
                $free = bcsub(bcsub($qty, (string) $row->qty_reserved, 4), $held, 4);
                $g['free_to_sell'] = bcadd($g['free_to_sell'], bccomp($free, '0', 4) > 0 ? $free : '0.0000', 4);
                if (bccomp($qty, '0', 4) > 0 && ($g['nearest_expiry'] === null || $row->expiry_date < $g['nearest_expiry'])) {
                    $g['nearest_expiry'] = $row->expiry_date;
                }
            }

            $g['in_transit'] = $inTransit[$row->product_id.'|'.$row->store_id] ?? '0.0000';
            $g['batches'][] = [
                'batch_id' => $row->batch_id, 'batch_number' => $row->batch_number, 'expiry_date' => $row->expiry_date,
                'status' => $isExpired && $row->status !== 'EXPIRED' ? 'EXPIRED (by date)' : $row->status,
                'on_hand' => $qty, 'reserved' => (string) $row->qty_reserved, 'quarantined' => (string) $row->qty_quarantined, 'wac' => (string) $row->wac,
            ];
            $grouped[$key] = $g;
        }

        // Stock on its way to a store that holds nothing yet still belongs
        // in that store's "in transit" bucket (Part 7.6).
        foreach ($inTransit as $key => $qty) {
            if (isset($grouped[$key]) || bccomp($qty, '0', 4) <= 0) {
                continue;
            }
            [$transitProductId, $transitStoreId] = explode('|', $key);
            if (($productId && $productId !== $transitProductId) || ($storeId && $storeId !== $transitStoreId)) {
                continue;
            }
            $product = DB::table('products as p')->leftJoin('product_categories as c', 'c.id', '=', 'p.category_id')
                ->where('p.id', $transitProductId)->where('p.organisation_id', $organisationId)
                ->first(['p.code', 'p.name', 'p.reorder_point', 'p.category_id', 'c.name as category_name']);
            $store = DB::table('stores')->where('id', $transitStoreId)->first(['code', 'name', 'branch_id']);
            if (! $product || ! $store || ($categoryIds !== null && ! in_array($product->category_id, $categoryIds, true))) {
                continue;
            }
            $grouped[$key] = [
                'product_id' => $transitProductId, 'product_code' => $product->code, 'product_name' => $product->name,
                'category_id' => $product->category_id, 'category_name' => $product->category_name,
                'store_id' => $transitStoreId, 'store_code' => $store->code, 'store_name' => $store->name,
                'on_hand' => '0.0000', 'reserved' => '0.0000', 'free_to_sell' => '0.0000', 'in_transit' => $qty,
                'pending_qc' => '0.0000', 'quarantined' => '0.0000', 'expired' => '0.0000', 'recalled' => '0.0000',
                'on_order' => $onOrder[$transitProductId.'|'.$store->branch_id] ?? '0.0000',
                'value_at_cost' => '0.0000', 'nearest_expiry' => null, 'reorder_point' => (string) $product->reorder_point,
                'batches' => [],
            ];
        }

        return array_values($grouped);
    }

    /**
     * @return array<string, string> keyed by "product|branch"
     */
    private function onOrderByProductAndBranch(string $organisationId): array
    {
        $rows = DB::table('purchase_order_lines as l')
            ->join('purchase_orders as po', 'po.id', '=', 'l.purchase_order_id')
            ->join('product_uoms as u', fn ($j) => $j->on('u.product_id', '=', 'l.product_id')->on('u.uom_id', '=', 'l.uom_id'))
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('goods_receipt_lines as g', 'g.purchase_order_line_id', '=', 'l.id')
            ->where('p.organisation_id', $organisationId)
            ->whereIn('po.status', ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED'])
            ->groupBy('l.id', 'l.product_id', 'po.branch_id', 'l.qty_ordered', 'u.factor_to_base')
            ->selectRaw('l.product_id, po.branch_id, (l.qty_ordered - COALESCE(SUM(g.qty_accepted), 0)) * u.factor_to_base as outstanding_base')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $key = $row->product_id.'|'.$row->branch_id;
            $out[$key] = bcadd($out[$key] ?? '0.0000', (string) max(0, (float) $row->outstanding_base), 4);
        }

        return $out;
    }

    /**
     * @return array<string, string> keyed by "product|to_store"
     */
    private function inTransitByProductAndStore(): array
    {
        $rows = DB::table('stock_transfer_lines as l')
            ->join('stock_transfers as t', 't.id', '=', 'l.stock_transfer_id')
            ->whereIn('t.status', ['DISPATCHED', 'DISCREPANCY'])
            ->groupBy('l.product_id', 't.to_store_id')
            ->selectRaw('l.product_id, t.to_store_id, SUM(l.qty_dispatched - COALESCE(l.qty_received, 0)) as qty')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->product_id.'|'.$row->to_store_id] = number_format((float) $row->qty, 4, '.', '');
        }

        return $out;
    }
}
