<?php

namespace App\Services\Inventory;

use App\Models\Branch;
use App\Models\StockBalance;
use App\Models\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Part 7.1 — ledger-first inventory. stock_ledgers is the single source of
 * truth; stock_balances is a derived, reconcilable cache. Direct mutation of
 * stock_balances outside this service is architecturally prohibited — every
 * write goes through post(), which writes the ledger row first and then
 * updates the cache from it, inside one transaction.
 */
class StockLedgerService
{
    /**
     * @param  array{
     *     organisation_id?: ?string, txn_type: string, product_id: string, batch_id: string, store_id: string,
     *     location_id?: ?string, qty_base: string, unit_cost?: string,
     *     source_doc_type: string, source_doc_id: string, source_doc_line_id?: ?string,
     *     reverses_ledger_id?: ?string, user_id: int, branch_id: string, txn_datetime?: \DateTimeInterface,
     * }  $data
     */
    public function post(array $data): StockLedger
    {
        return DB::transaction(function () use ($data) {
            $unitCost = $data['unit_cost'] ?? '0';
            $qtyBase = $data['qty_base'];

            $ledger = StockLedger::create(array_merge($data, [
                'organisation_id' => $data['organisation_id'] ?? Branch::whereKey($data['branch_id'])->value('organisation_id'),
                'unit_cost' => $unitCost,
                'total_cost' => bcmul($qtyBase, $unitCost, 4),
                'txn_datetime' => $data['txn_datetime'] ?? now(),
                'created_at' => now(),
            ]));

            $this->applyToBalance($ledger);

            return $ledger;
        });
    }

    /**
     * Posts a compensating row that reverses an existing ledger entry exactly
     * (Part 7.2's SALE_VOID / correction rule) — the original row is never
     * touched.
     */
    public function reverse(StockLedger $original, string $txnType, array $overrides = []): StockLedger
    {
        return $this->post(array_merge([
            'txn_type' => $txnType,
            'product_id' => $original->product_id,
            'batch_id' => $original->batch_id,
            'store_id' => $original->store_id,
            'location_id' => $original->location_id,
            'qty_base' => bcmul($original->qty_base, '-1', 4),
            'unit_cost' => (string) $original->unit_cost,
            'source_doc_type' => $original->source_doc_type,
            'source_doc_id' => $original->source_doc_id,
            'reverses_ledger_id' => $original->id,
            'branch_id' => $original->branch_id,
        ], $overrides));
    }

    private function applyToBalance(StockLedger $ledger): void
    {
        $balance = StockBalance::query()
            ->where('product_id', $ledger->product_id)
            ->where('batch_id', $ledger->batch_id)
            ->where('store_id', $ledger->store_id)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $balance = new StockBalance([
                'product_id' => $ledger->product_id,
                'batch_id' => $ledger->batch_id,
                'store_id' => $ledger->store_id,
                'qty_on_hand' => '0',
                'qty_reserved' => '0',
                'qty_quarantined' => '0',
                'wac' => '0',
            ]);
        }

        $oldQty = (string) $balance->qty_on_hand;
        $newQty = bcadd($oldQty, (string) $ledger->qty_base, 4);

        // WAC only moves on incoming stock; outgoing stock draws down
        // quantity at the existing average without changing it.
        if (bccomp($ledger->qty_base, '0', 4) > 0 && bccomp((string) $ledger->unit_cost, '0', 4) > 0) {
            $oldValue = bcmul($oldQty, (string) $balance->wac, 4);
            $incomingValue = bcmul((string) $ledger->qty_base, (string) $ledger->unit_cost, 4);
            $balance->wac = bccomp($newQty, '0', 4) > 0
                ? bcdiv(bcadd($oldValue, $incomingValue, 4), $newQty, 4)
                : $balance->wac;
        }

        $balance->qty_on_hand = $newQty;
        $balance->last_movement_at = $ledger->txn_datetime;
        $balance->save();
    }
}
