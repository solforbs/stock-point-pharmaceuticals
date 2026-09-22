<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\Organisation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clears an institution's demonstration or training transactions: every
 * document, stock movement, payment and journal recorded since a given
 * moment. Master data (products, prices, customers, suppliers, users,
 * settings) and everything recorded before that moment stay exactly as they
 * were. Stock levels, customer balances and document numbers are put back
 * to where they stood.
 *
 * This is the one place transactions are ever deleted, so it is reserved
 * for platform administrators, runs in a single database transaction (a
 * failure changes nothing) and leaves an audit entry with the counts.
 * Changes made after the moment to documents created before it (a
 * dispatch against an older order, say) are not unwound.
 */
class TransactionPurgeService
{
    /**
     * Document tables in deletion order: a table always comes before the
     * tables its rows restrict (delivery notes before sales orders, every
     * batch allocation before the batches themselves). Children that
     * cascade from a parent (sale lines, GRN lines…) are not listed.
     *
     * @var list<array{table: string, label: string, time: string, scope: 'branch'|'organisation'|'store'|'from_store'}>
     */
    private const DOCUMENTS = [
        ['table' => 'delivery_notes', 'label' => 'Delivery notes', 'time' => 'created_at', 'scope' => 'branch'],
        ['table' => 'picking_lists', 'label' => 'Pick lists', 'time' => 'created_at', 'scope' => 'branch'],
        ['table' => 'sales_orders', 'label' => 'Sales orders', 'time' => 'created_at', 'scope' => 'branch'],
        ['table' => 'customer_returns', 'label' => 'Customer returns', 'time' => 'created_at', 'scope' => 'branch'],
        ['table' => 'sales', 'label' => 'Sales and invoices', 'time' => 'posted_at', 'scope' => 'branch'],
        ['table' => 'offline_sales', 'label' => 'Offline sales', 'time' => 'created_at', 'scope' => 'branch'],
        ['table' => 'quotations', 'label' => 'Quotations', 'time' => 'created_at', 'scope' => 'branch'],
        ['table' => 'payments', 'label' => 'Customer payments', 'time' => 'created_at', 'scope' => 'branch'],
        ['table' => 'accounts_receivables', 'label' => 'Receivable entries', 'time' => 'created_at', 'scope' => 'organisation'],
        ['table' => 'supplier_payments', 'label' => 'Supplier payments', 'time' => 'created_at', 'scope' => 'organisation'],
        ['table' => 'accounts_payables', 'label' => 'Payable entries', 'time' => 'created_at', 'scope' => 'organisation'],
        ['table' => 'supplier_invoices', 'label' => 'Supplier invoices', 'time' => 'created_at', 'scope' => 'branch'],
        ['table' => 'supplier_returns', 'label' => 'Supplier returns', 'time' => 'created_at', 'scope' => 'branch'],
        ['table' => 'goods_receipts', 'label' => 'Goods receipts', 'time' => 'created_at', 'scope' => 'branch'],
        ['table' => 'purchase_orders', 'label' => 'Purchase orders', 'time' => 'created_at', 'scope' => 'branch'],
        ['table' => 'requisitions', 'label' => 'Requisitions', 'time' => 'created_at', 'scope' => 'branch'],
        ['table' => 'stock_transfers', 'label' => 'Stock transfers', 'time' => 'created_at', 'scope' => 'from_store'],
        ['table' => 'stock_adjustments', 'label' => 'Stock adjustments', 'time' => 'created_at', 'scope' => 'store'],
        ['table' => 'stock_counts', 'label' => 'Stock counts', 'time' => 'created_at', 'scope' => 'store'],
        ['table' => 'waste_disposals', 'label' => 'Waste disposals', 'time' => 'created_at', 'scope' => 'branch'],
        ['table' => 'recalls', 'label' => 'Recalls', 'time' => 'created_at', 'scope' => 'organisation'],
        ['table' => 'stock_reservations', 'label' => 'Stock reservations', 'time' => 'created_at', 'scope' => 'organisation'],
        ['table' => 'stock_ledgers', 'label' => 'Stock movements', 'time' => 'created_at', 'scope' => 'organisation'],
        ['table' => 'product_batches', 'label' => 'Batches received', 'time' => 'created_at', 'scope' => 'organisation'],
        ['table' => 'journal_entries', 'label' => 'Journal entries', 'time' => 'posted_at', 'scope' => 'organisation'],
        ['table' => 'alerts', 'label' => 'Alerts', 'time' => 'created_at', 'scope' => 'organisation'],
    ];

    /**
     * How many of each document would go.
     *
     * @return list<array{table: string, label: string, count: int}>
     */
    public function preview(Organisation $organisation, Carbon $since): array
    {
        $scope = $this->scope($organisation);
        $counts = [];
        foreach (self::DOCUMENTS as $document) {
            $counts[] = ['table' => $document['table'], 'label' => $document['label'], 'count' => $this->query($document, $scope, $since)->count()];
        }

        return $counts;
    }

    /**
     * @return array{since: string, deleted: array<string, int>, stock_rows_restored: int, customers_rebalanced: int, sequences_rewound: int}
     */
    public function purge(Organisation $organisation, Carbon $since, ?string $reason = null): array
    {
        $scope = $this->scope($organisation);

        return DB::transaction(function () use ($organisation, $since, $scope, $reason) {
            $touchedStock = $this->query(['table' => 'stock_ledgers', 'time' => 'created_at', 'scope' => 'organisation'], $scope, $since)
                ->select('batch_id', 'store_id')->distinct()->get();
            $touchedCustomers = $this->touchedCustomers($scope, $since);
            $deletedNumbers = $this->deletedDocumentNumbers($scope, $since);

            // Allocations made since the moment against payments that stay.
            DB::table('payment_allocations')->whereIn('payment_id', DB::table('payments')->whereIn('branch_id', $scope['branches'])->select('id'))
                ->where('created_at', '>=', $since)->delete();
            DB::table('landed_costs')->whereIn('goods_receipt_id', DB::table('goods_receipts')->whereIn('branch_id', $scope['branches'])->select('id'))
                ->where('created_at', '>=', $since)->delete();
            DB::table('price_quote_logs')->whereIn('branch_id', $scope['branches'])->where('created_at', '>=', $since)->delete();

            $deleted = [];
            foreach (self::DOCUMENTS as $document) {
                $deleted[$document['table']] = $this->query($document, $scope, $since)->delete();
            }

            $stockRows = $this->restoreStockBalances($touchedStock);
            $customers = $this->rebalanceCustomers($touchedCustomers);
            $sequences = $this->rewindSequences($organisation, $deletedNumbers);

            $summary = [
                'since' => $since->toIso8601String(),
                'deleted' => array_filter($deleted),
                'stock_rows_restored' => $stockRows,
                'customers_rebalanced' => $customers,
                'sequences_rewound' => $sequences,
            ];

            AuditLog::record('TRANSACTIONS_PURGED', 'organisation', (string) $organisation->id, [
                'organisation_id' => $organisation->id,
                'reference' => 'Since '.$since->toDateTimeString(),
                'after_json' => $summary,
                'reason' => $reason,
            ]);

            return $summary;
        });
    }

    /**
     * @return array{organisation: string, branches: list<string>, stores: list<string>}
     */
    private function scope(Organisation $organisation): array
    {
        $branches = DB::table('branches')->where('organisation_id', $organisation->id)->pluck('id')->map(fn ($id) => (string) $id)->all();

        return [
            'organisation' => (string) $organisation->id,
            'branches' => $branches,
            'stores' => DB::table('stores')->whereIn('branch_id', $branches)->pluck('id')->map(fn ($id) => (string) $id)->all(),
        ];
    }

    /**
     * @param  array{table: string, time: string, scope: string}  $document
     * @param  array{organisation: string, branches: list<string>, stores: list<string>}  $scope
     */
    private function query(array $document, array $scope, Carbon $since): Builder
    {
        $query = DB::table($document['table'])->where($document['time'], '>=', $since);

        return match ($document['scope']) {
            'branch' => $query->whereIn('branch_id', $scope['branches']),
            'store' => $query->whereIn('store_id', $scope['stores']),
            'from_store' => $query->whereIn('from_store_id', $scope['stores']),
            default => $query->where('organisation_id', $scope['organisation']),
        };
    }

    /**
     * Customers whose running balance or unallocated receipts change.
     *
     * @param  array{organisation: string, branches: list<string>, stores: list<string>}  $scope
     * @return list<string>
     */
    private function touchedCustomers(array $scope, Carbon $since): array
    {
        return DB::table('accounts_receivables')->where('organisation_id', $scope['organisation'])->where('created_at', '>=', $since)->pluck('customer_id')
            ->merge(DB::table('payments')->whereIn('branch_id', $scope['branches'])->where('created_at', '>=', $since)->pluck('customer_id'))
            ->merge(DB::table('payment_allocations as a')->join('payments as p', 'p.id', '=', 'a.payment_id')
                ->whereIn('p.branch_id', $scope['branches'])->where('a.created_at', '>=', $since)->pluck('p.customer_id'))
            ->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
    }

    /**
     * Every document number that is about to disappear.
     *
     * @param  array{organisation: string, branches: list<string>, stores: list<string>}  $scope
     * @return list<string>
     */
    private function deletedDocumentNumbers(array $scope, Carbon $since): array
    {
        $numbers = [];
        foreach (self::DOCUMENTS as $document) {
            if (! Schema::hasColumn($document['table'], 'doc_number')) {
                continue;
            }
            array_push($numbers, ...$this->query($document, $scope, $since)->pluck('doc_number')->filter()->map(fn ($n) => (string) $n)->all());
        }

        return $numbers;
    }

    /**
     * Recomputes each touched batch-in-store from the stock movements that
     * remain. A batch-in-store left with no movements at all is removed.
     *
     * @param  Collection<int, object{batch_id: string, store_id: string}>  $touched
     */
    private function restoreStockBalances(Collection $touched): int
    {
        $restored = 0;
        foreach ($touched as $pair) {
            $balance = DB::table('stock_balances')->where('batch_id', $pair->batch_id)->where('store_id', $pair->store_id);
            if (! DB::table('product_batches')->where('id', $pair->batch_id)->exists()) {
                continue;
            }

            $movements = DB::table('stock_ledgers')->where('batch_id', $pair->batch_id)->where('store_id', $pair->store_id);
            if (! (clone $movements)->exists()) {
                $restored += (clone $balance)->delete();

                continue;
            }

            $onHand = (string) (clone $movements)->sum('qty_base');
            $receipts = (clone $movements)->where('qty_base', '>', 0)->selectRaw('SUM(qty_base) as qty, SUM(qty_base * unit_cost) as value')->first();
            $wac = $receipts && (float) $receipts->qty > 0 ? bcdiv((string) $receipts->value, (string) $receipts->qty, 4) : null;
            $reserved = (string) DB::table('stock_reservations')->where('batch_id', $pair->batch_id)->where('store_id', $pair->store_id)->where('status', 'ACTIVE')->sum('qty_base');

            $current = (clone $balance)->first();
            $quarantined = $current ? (string) $current->qty_quarantined : '0';
            if (bccomp($quarantined, $onHand, 4) > 0) {
                $quarantined = bccomp($onHand, '0', 4) > 0 ? $onHand : '0';
            }

            $values = array_filter([
                'qty_on_hand' => $onHand,
                'qty_reserved' => $reserved,
                'qty_quarantined' => $quarantined,
                'wac' => $wac,
                'updated_at' => now(),
            ], fn ($value) => $value !== null);

            $restored += (clone $balance)->update($values);
        }

        return $restored;
    }

    /**
     * A customer's balance is the running balance on their last remaining
     * receivable entry; unallocated receipts are what remains of their
     * payments after allocations.
     *
     * @param  list<string>  $customerIds
     */
    private function rebalanceCustomers(array $customerIds): int
    {
        foreach ($customerIds as $customerId) {
            $last = DB::table('accounts_receivables')->where('customer_id', $customerId)->orderByDesc('created_at')->orderByDesc('id')->value('balance_after');

            $paid = (string) DB::table('payments')->where('customer_id', $customerId)->where('status', '!=', 'REVERSED')->sum('amount');
            $allocated = (string) DB::table('payment_allocations as a')->join('payments as p', 'p.id', '=', 'a.payment_id')
                ->where('p.customer_id', $customerId)->where('p.status', '!=', 'REVERSED')->sum('a.amount');
            $unallocated = bcsub($paid, $allocated, 4);

            DB::table('customer_credits')->where('customer_id', $customerId)->update([
                'current_balance' => $last ?? '0',
                'unallocated_receipts' => bccomp($unallocated, '0', 4) > 0 ? $unallocated : '0',
                'updated_at' => now(),
            ]);
        }

        return count($customerIds);
    }

    /**
     * Winds each numbering sequence back so the next real document follows
     * the last one that remains (PO-2026-000001, not PO-2026-000004). Only
     * when the deleted numbers are the sequence's latest, and only where a
     * prefix identifies a single sequence.
     *
     * @param  list<string>  $numbers
     */
    private function rewindSequences(Organisation $organisation, array $numbers): int
    {
        $byPrefixYear = [];
        foreach ($numbers as $number) {
            if (preg_match('/^(.+)-(\d{4})-(\d+)$/', $number, $m) === 1) {
                $byPrefixYear[$m[1].'|'.$m[2]][] = (int) $m[3];
            }
        }

        $rewound = 0;
        foreach ($byPrefixYear as $key => $deleted) {
            [$prefix, $year] = explode('|', $key);
            $sequences = DB::table('number_sequences')->where('organisation_id', $organisation->id)->where('prefix', $prefix)->where('fiscal_year', (int) $year)->get();
            if ($sequences->count() !== 1) {
                continue;
            }

            $sequence = $sequences->first();
            if ((int) $sequence->current_value !== max($deleted)) {
                continue;
            }

            DB::table('number_sequences')->where('id', $sequence->id)->update(['current_value' => min($deleted) - 1, 'updated_at' => now()]);
            $rewound++;
        }

        return $rewound;
    }
}
