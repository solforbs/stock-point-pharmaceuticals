<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Models\Branch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Part 17 — the standing-alert engine. It recomputes, for one branch, every
 * payment deadline and shelf-life risk that is true today, then makes the
 * alerts table say exactly that: new conditions are inserted, conditions
 * that still hold are refreshed (and may escalate), and conditions that
 * have gone away are resolved. Nothing here writes to the books.
 */
class AlertScanner
{
    /** A payable or receivable inside this many days is "due soon". */
    public const DUE_SOON_DAYS = 7;

    /** Shelf life at which stock becomes a warning, then merely worth watching. */
    public const EXPIRY_WARNING_DAYS = 30;

    public const EXPIRY_WATCH_DAYS = 90;

    /**
     * @return array{opened: int, refreshed: int, resolved: int}
     */
    public function scan(Branch $branch, ?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? now())->startOfDay();

        $candidates = [
            ...$this->receivables($branch, $asOf),
            ...$this->payables($branch, $asOf),
            ...$this->expiringStock($branch, $asOf),
        ];

        $opened = 0;
        $refreshed = 0;
        $keys = [];

        DB::transaction(function () use ($branch, $candidates, &$opened, &$refreshed, &$keys) {
            foreach ($candidates as $candidate) {
                $keys[] = $candidate['alert_key'];
                $existing = Alert::where('branch_id', $branch->id)->where('alert_key', $candidate['alert_key'])->first();

                if (! $existing) {
                    Alert::create($candidate + [
                        'organisation_id' => $branch->organisation_id,
                        'branch_id' => $branch->id,
                        'first_seen_at' => now(),
                        'last_seen_at' => now(),
                    ]);
                    $opened++;

                    continue;
                }

                // An alert that was acknowledged and has since got worse —
                // due soon becoming overdue — is raised again deliberately.
                $escalated = $this->rank($candidate['severity']) > $this->rank($existing->severity);
                $existing->update($candidate + [
                    'last_seen_at' => now(),
                    'resolved_at' => null,
                ] + ($escalated ? ['acknowledged_by' => null, 'acknowledged_at' => null] : []));
                $refreshed++;
            }
        });

        $resolved = Alert::where('branch_id', $branch->id)->open()
            ->when($keys !== [], fn ($q) => $q->whereNotIn('alert_key', $keys))
            ->update(['resolved_at' => now()]);

        return ['opened' => $opened, 'refreshed' => $refreshed, 'resolved' => $resolved];
    }

    /**
     * Customer invoices still owing, dated by the customer's payment terms.
     *
     * @return list<array<string, mixed>>
     */
    private function receivables(Branch $branch, Carbon $asOf): array
    {
        $rows = DB::table('sales as s')
            ->join('customers as c', 'c.id', '=', 's.customer_id')
            ->join('accounts_receivables as ar', 'ar.sale_id', '=', 's.id')
            ->where('s.branch_id', $branch->id)
            ->where('s.status', 'POSTED')
            ->groupBy('s.id', 's.doc_number', 's.posted_at', 'c.id', 'c.code', 'c.name', 'c.payment_terms_days')
            ->havingRaw('SUM(ar.amount) > 0')
            ->select([
                's.id as sale_id', 's.doc_number', 's.posted_at',
                'c.id as customer_id', 'c.code as customer_code', 'c.name as customer_name', 'c.payment_terms_days',
                DB::raw('SUM(ar.amount) as outstanding'),
            ])
            ->get();

        $alerts = [];
        foreach ($rows as $row) {
            $due = Carbon::parse($row->posted_at)->startOfDay()->addDays((int) ($row->payment_terms_days ?? 0));
            $days = (int) $asOf->diffInDays($due, false);
            if ($days > self::DUE_SOON_DAYS) {
                continue;
            }

            $overdue = $days < 0;
            $amount = number_format((float) $row->outstanding, 4, '.', '');

            $alerts[] = [
                'alert_key' => 'receivable:'.$row->sale_id,
                'category' => 'RECEIVABLE',
                'type' => $overdue ? 'INVOICE_OVERDUE' : 'INVOICE_DUE_SOON',
                'severity' => $overdue ? 'CRITICAL' : 'WARNING',
                'title' => $overdue
                    ? "Invoice {$row->doc_number} is {$this->plural(abs($days), 'day')} overdue"
                    : "Invoice {$row->doc_number} falls due in {$this->plural($days, 'day')}",
                'detail' => "{$row->customer_code} · {$row->customer_name} owes {$this->money($amount)} on invoice {$row->doc_number}.",
                'entity_type' => 'sale',
                'entity_id' => (string) $row->sale_id,
                'due_date' => $due->toDateString(),
                'amount' => $amount,
                'link' => '/customers/credit-control?customer_id='.$row->customer_id,
                'permission' => 'finance.ar.view',
            ];
        }

        return $alerts;
    }

    /**
     * Supplier invoices still owing. The invoice's own due date wins; where
     * it has none the supplier's terms decide.
     *
     * @return list<array<string, mixed>>
     */
    private function payables(Branch $branch, Carbon $asOf): array
    {
        $rows = DB::table('supplier_invoices as si')
            ->join('suppliers as sup', 'sup.id', '=', 'si.supplier_id')
            ->leftJoin('accounts_payables as ap', 'ap.supplier_invoice_id', '=', 'si.id')
            ->where('si.branch_id', $branch->id)
            ->groupBy('si.id', 'si.doc_number', 'si.invoice_number', 'si.invoice_date', 'si.due_date', 'sup.id', 'sup.code', 'sup.name', 'sup.payment_terms_days')
            ->havingRaw('SUM(COALESCE(ap.amount, 0)) > 0')
            ->select([
                'si.id as invoice_id', 'si.doc_number', 'si.invoice_number', 'si.invoice_date', 'si.due_date',
                'sup.id as supplier_id', 'sup.code as supplier_code', 'sup.name as supplier_name', 'sup.payment_terms_days',
                DB::raw('SUM(COALESCE(ap.amount, 0)) as outstanding'),
            ])
            ->get();

        $alerts = [];
        foreach ($rows as $row) {
            $due = $row->due_date
                ? Carbon::parse($row->due_date)->startOfDay()
                : Carbon::parse($row->invoice_date)->startOfDay()->addDays((int) ($row->payment_terms_days ?? 0));
            $days = (int) $asOf->diffInDays($due, false);
            if ($days > self::DUE_SOON_DAYS) {
                continue;
            }

            $overdue = $days < 0;
            $amount = number_format((float) $row->outstanding, 4, '.', '');

            $alerts[] = [
                'alert_key' => 'payable:'.$row->invoice_id,
                'category' => 'PAYABLE',
                'type' => $overdue ? 'SUPPLIER_INVOICE_OVERDUE' : 'SUPPLIER_INVOICE_DUE_SOON',
                'severity' => $overdue ? 'CRITICAL' : 'WARNING',
                'title' => $overdue
                    ? "Supplier invoice {$row->invoice_number} is {$this->plural(abs($days), 'day')} overdue"
                    : "Supplier invoice {$row->invoice_number} falls due in {$this->plural($days, 'day')}",
                'detail' => "{$this->money($amount)} owed to {$row->supplier_code} · {$row->supplier_name}.",
                'entity_type' => 'supplier_invoice',
                'entity_id' => (string) $row->invoice_id,
                'due_date' => $due->toDateString(),
                'amount' => $amount,
                'link' => '/buy/supplier-invoices',
                'permission' => 'finance.ap.view',
            ];
        }

        return $alerts;
    }

    /**
     * Stock on hand running out of shelf life, one alert per batch and store.
     *
     * @return list<array<string, mixed>>
     */
    private function expiringStock(Branch $branch, Carbon $asOf): array
    {
        $rows = DB::table('stock_balances as sb')
            ->join('product_batches as pb', 'pb.id', '=', 'sb.batch_id')
            ->join('products as p', 'p.id', '=', 'sb.product_id')
            ->join('stores as st', 'st.id', '=', 'sb.store_id')
            ->where('st.branch_id', $branch->id)
            ->where('sb.qty_on_hand', '>', 0)
            ->whereDate('pb.expiry_date', '<=', $asOf->copy()->addDays(self::EXPIRY_WATCH_DAYS)->toDateString())
            ->whereNotIn('pb.status', ['DISPOSED', 'RETURNED_TO_SUPPLIER', 'REJECTED'])
            ->select([
                'pb.id as batch_id', 'pb.batch_number', 'pb.expiry_date', 'pb.status',
                'p.code as product_code', 'p.name as product_name',
                'st.id as store_id', 'st.code as store_code',
                'sb.qty_on_hand',
            ])
            ->get();

        $alerts = [];
        foreach ($rows as $row) {
            $expiry = Carbon::parse($row->expiry_date)->startOfDay();
            $days = (int) $asOf->diffInDays($expiry, false);
            $qty = rtrim(rtrim(number_format((float) $row->qty_on_hand, 4, '.', ''), '0'), '.');

            [$type, $severity, $title] = match (true) {
                $days < 0 => ['BATCH_EXPIRED', 'CRITICAL', "Expired stock on hand: {$row->product_name}"],
                $days <= self::EXPIRY_WARNING_DAYS => ['BATCH_EXPIRING', 'WARNING', "{$row->product_name} expires in {$this->plural($days, 'day')}"],
                default => ['BATCH_EXPIRING', 'INFO', "{$row->product_name} expires in {$this->plural($days, 'day')}"],
            };

            $alerts[] = [
                'alert_key' => "expiry:{$row->batch_id}:{$row->store_id}",
                'category' => 'EXPIRY',
                'type' => $type,
                'severity' => $severity,
                'title' => $title,
                'detail' => "Batch {$row->batch_number} · {$qty} on hand in {$row->store_code} · expires {$expiry->toDateString()} · {$row->product_code}.",
                'entity_type' => 'product_batch',
                'entity_id' => (string) $row->batch_id,
                'due_date' => $expiry->toDateString(),
                'amount' => null,
                'link' => '/inventory/batches?batch='.$row->batch_id,
                'permission' => 'stock.view',
            ];
        }

        return $alerts;
    }

    private function rank(string $severity): int
    {
        return match ($severity) {
            'CRITICAL' => 3,
            'WARNING' => 2,
            default => 1,
        };
    }

    private function plural(int $count, string $noun): string
    {
        return $count.' '.$noun.($count === 1 ? '' : 's');
    }

    private function money(string $amount): string
    {
        return 'KES '.number_format((float) $amount, 2);
    }
}
