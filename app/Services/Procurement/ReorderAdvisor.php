<?php

namespace App\Services\Procurement;

use App\Models\Product;
use App\Services\Inventory\InventoryReport;
use Illuminate\Support\Facades\DB;

/**
 * Part 22.1 / 0.1 — the preserved v5 reorder arithmetic, honestly labelled:
 * deterministic and auditable, not "AI".
 *
 *   order_qty   = max(round(reorder_point × coverage_factor − free_to_sell − on_order), min_order_qty)
 *   required_by = today + lead_time_days + 2
 *
 * grouped by the supplier who last delivered the product. Only products
 * whose free-to-sell plus on-order has fallen to the reorder point appear.
 */
class ReorderAdvisor
{
    public const COVERAGE_FACTOR = '3';

    public const MIN_ORDER_QTY = '10';

    public function __construct(private readonly InventoryReport $report) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function suggestions(string $organisationId, string $branchId): array
    {
        $states = collect($this->report->stockStates($organisationId))
            ->filter(fn ($row) => DB::table('stores')->where('id', $row['store_id'])->value('branch_id') === $branchId);

        /** @var array<string, array{free_to_sell: string, on_order: string, nearest_expiry: ?string}> $byProduct */
        $byProduct = [];
        foreach ($states as $row) {
            $current = $byProduct[$row['product_id']] ?? ['free_to_sell' => '0.0000', 'on_order' => (string) $row['on_order'], 'nearest_expiry' => null];
            $current['free_to_sell'] = bcadd($current['free_to_sell'], (string) $row['free_to_sell'], 4);
            if ($row['nearest_expiry'] && ($current['nearest_expiry'] === null || $row['nearest_expiry'] < $current['nearest_expiry'])) {
                $current['nearest_expiry'] = (string) $row['nearest_expiry'];
            }
            $byProduct[$row['product_id']] = $current;
        }

        $products = Product::query()
            ->where('organisation_id', $organisationId)->where('is_active', true)->where('reorder_point', '>', 0)
            ->orderBy('name')->get();

        /** @var list<array<string, mixed>> $out */
        $out = [];
        foreach ($products as $product) {
            $free = $byProduct[$product->id]['free_to_sell'] ?? '0.0000';
            $onOrder = $byProduct[$product->id]['on_order'] ?? '0.0000';
            $coverage = bcadd($free, $onOrder, 4);
            $rop = (string) $product->reorder_point;

            if (bccomp($coverage, $rop, 4) > 0) {
                continue;
            }

            $raw = bcsub(bcsub(bcmul($rop, self::COVERAGE_FACTOR, 4), $free, 4), $onOrder, 4);
            $rounded = (string) round((float) $raw);
            $orderQty = bccomp($rounded, self::MIN_ORDER_QTY, 0) > 0 ? $rounded : self::MIN_ORDER_QTY;

            $lastBatch = DB::table('product_batches')->where('product_id', $product->id)->whereNotNull('supplier_id')->orderByDesc('created_at')->first(['supplier_id']);
            $supplier = $lastBatch ? DB::table('suppliers')->where('id', $lastBatch->supplier_id)->first(['id', 'code', 'name', 'lead_time_days']) : null;
            $leadTime = (int) ($product->lead_time_days ?: ($supplier->lead_time_days ?? 0));

            $out[] = [
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'base_uom' => DB::table('units_of_measure')->where('id', $product->base_uom_id)->value('code'),
                'reorder_point' => $rop,
                'free_to_sell' => $free,
                'on_order' => $onOrder,
                'coverage' => $coverage,
                'suggested_qty_base' => bcadd($orderQty, '0', 4),
                'lead_time_days' => $leadTime,
                'required_by' => now()->addDays($leadTime + 2)->toDateString(),
                'nearest_expiry' => $byProduct[$product->id]['nearest_expiry'] ?? null,
                'supplier_id' => $supplier->id ?? null,
                'supplier_code' => $supplier->code ?? null,
                'supplier_name' => $supplier->name ?? null,
                'formula' => "max(round({$rop} × ".self::COVERAGE_FACTOR." − {$free} − {$onOrder}), ".self::MIN_ORDER_QTY.')',
            ];
        }

        usort($out, fn ($a, $b) => strcmp((string) $a['supplier_name'], (string) $b['supplier_name']) ?: strcmp($a['product_name'], $b['product_name']));

        return $out;
    }
}
