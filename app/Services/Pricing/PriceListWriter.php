<?php

namespace App\Services\Pricing;

use App\Models\AuditLog;
use App\Models\PriceList;
use App\Models\ProductPrice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Part 6.2 — writes one effective-dated price-list row. A new price closes
 * the row it replaces the day before rather than overwriting it, so every
 * historical quote can still be explained; a second change on the same day
 * replaces that day's row. Every write is audited as PRICE_CHANGED.
 *
 * Shared by the price-list screen and the goods receipt, which may set new
 * selling prices as stock arrives.
 */
class PriceListWriter
{
    /**
     * @param  array{product_id: string, uom_id: string, factor_type: string, unit_price?: string|float|int|null, factor_value?: string|float|int|null, effective_from?: ?string, effective_to?: ?string}  $data
     * @param  array<string, mixed>  $auditContext  extra fields merged into the audit entry's after_json
     */
    public function write(PriceList $list, array $data, array $auditContext = []): ProductPrice
    {
        $from = $data['effective_from'] ?? now()->toDateString();

        return DB::transaction(function () use ($list, $data, $from, $auditContext) {
            $previous = ProductPrice::where('price_list_id', $list->id)->where('product_id', $data['product_id'])
                ->where('uom_id', $data['uom_id'])
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
                ->whereDate('effective_from', '<', $from)
                ->get();
            foreach ($previous as $old) {
                $old->update(['effective_to' => Carbon::parse($from)->subDay()->toDateString()]);
            }

            $sameDay = ProductPrice::where('price_list_id', $list->id)->where('product_id', $data['product_id'])
                ->where('uom_id', $data['uom_id'])->whereDate('effective_from', $from)->get();
            ProductPrice::whereKey($sameDay->modelKeys())->delete();

            $row = ProductPrice::create([
                'price_list_id' => $list->id,
                'product_id' => $data['product_id'],
                'uom_id' => $data['uom_id'],
                'factor_type' => $data['factor_type'],
                'unit_price' => (string) ($data['unit_price'] ?? '0'),
                'factor_value' => (string) ($data['factor_value'] ?? '0'),
                'effective_from' => $from,
                'effective_to' => $data['effective_to'] ?? null,
            ]);

            AuditLog::record('PRICE_CHANGED', 'product_price', $row->id, [
                'reference' => $list->code,
                'before_json' => $previous->concat($sameDay)->map(fn (ProductPrice $p) => $p->only(['factor_type', 'unit_price', 'factor_value', 'effective_from']))->values()->all(),
                'after_json' => $row->only(['product_id', 'uom_id', 'factor_type', 'unit_price', 'factor_value', 'effective_from', 'effective_to']) + $auditContext,
            ]);

            return $row;
        });
    }
}
