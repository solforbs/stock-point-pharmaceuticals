<?php

namespace App\Services\Assistant;

use App\Models\Alert;
use App\Models\CustomerReturn;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Everything the assistant can be asked, and nothing else.
 *
 * The list is deliberately closed and read-only. A command carries the same
 * permission as the screen that shows the same figures, so the assistant can
 * never tell someone something the application would not, and a session that
 * began with an emailed code can never write.
 */
class AssistantCommands
{
    /** Rows returned by any one command. */
    private const LIMIT = 10;

    public function __construct(private readonly string $organisationId, private readonly string $branchId) {}

    /**
     * The commands this user may run, in the order they are offered.
     *
     * @return list<array{command: string, title: string, hint: string}>
     */
    public function availableTo(User $user): array
    {
        $available = [];
        foreach (self::catalogue() as $command => $definition) {
            if ($definition['permission'] === null || $user->can($definition['permission'])) {
                $available[] = ['command' => $command, 'title' => $definition['title'], 'hint' => $definition['hint']];
            }
        }

        return $available;
    }

    /**
     * @return array{command: string, title: string, summary: string, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}|null
     *                                                                                                                                                         Null when there is no such command.
     */
    public function run(string $command, User $user): ?array
    {
        $command = strtolower(trim($command));
        $command = str_starts_with($command, '/') ? $command : '/'.$command;
        $definition = self::catalogue()[$command] ?? null;
        if ($definition === null) {
            return null;
        }
        if ($definition['permission'] !== null && ! $user->can($definition['permission'])) {
            throw new AssistantPermissionException($definition['permission'], $definition['title']);
        }

        $method = $definition['method'];
        $result = $this->{$method}($user);

        return ['command' => $command, 'title' => $definition['title']] + $result;
    }

    /**
     * @return array<string, array{title: string, hint: string, permission: ?string, method: string}>
     */
    public static function catalogue(): array
    {
        return [
            '/sales_today' => ['title' => "Today's sales", 'hint' => 'Counter and wholesale takings so far today.', 'permission' => 'sale.view', 'method' => 'salesToday'],
            '/recent_sales' => ['title' => 'Recent sales', 'hint' => 'The last ten posted sales.', 'permission' => 'sale.view', 'method' => 'recentSales'],
            '/recent_invoices' => ['title' => 'Recent invoices', 'hint' => 'The last ten credit invoices and what is still owed on them.', 'permission' => 'sale.view', 'method' => 'recentInvoices'],
            '/debtors' => ['title' => 'Who owes us', 'hint' => 'The ten largest outstanding customer balances.', 'permission' => 'finance.ar.view', 'method' => 'debtors'],
            '/payments_today' => ['title' => "Today's receipts", 'hint' => 'Money received today, by method.', 'permission' => 'finance.ar.view', 'method' => 'paymentsToday'],
            '/low_stock' => ['title' => 'Low stock', 'hint' => 'Products at or below their reorder point.', 'permission' => 'stock.view', 'method' => 'lowStock'],
            '/expiring' => ['title' => 'Expiring stock', 'hint' => 'Batches expiring within ninety days.', 'permission' => 'stock.view', 'method' => 'expiring'],
            '/recent_purchases' => ['title' => 'Recent purchase orders', 'hint' => 'The last ten orders raised to suppliers.', 'permission' => 'po.create', 'method' => 'recentPurchases'],
            '/approvals' => ['title' => 'Waiting for approval', 'hint' => 'The queues you can clear.', 'permission' => null, 'method' => 'approvals'],
            '/alerts' => ['title' => 'Open alerts', 'hint' => 'What the system is warning about.', 'permission' => null, 'method' => 'alerts'],
            '/whoami' => ['title' => 'This session', 'hint' => 'Who you are signed in as and where.', 'permission' => null, 'method' => 'whoami'],
            '/help' => ['title' => 'What I can answer', 'hint' => 'This list.', 'permission' => null, 'method' => 'help'],
        ];
    }

    /**
     * @return array{summary: string, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}
     */
    private function salesToday(User $user): array
    {
        $today = now()->toDateString();
        $rows = DB::table('sales')
            ->where('branch_id', $this->branchId)
            ->where('status', 'POSTED')
            ->whereDate('posted_at', $today)
            ->selectRaw('sale_mode, COUNT(*) as n, COALESCE(SUM(grand_total), 0) as total')
            ->groupBy('sale_mode')
            ->get();

        $voided = Sale::where('branch_id', $this->branchId)->where('status', 'VOIDED')->whereDate('voided_at', $today)->count();
        $count = (int) $rows->sum('n');
        $total = (float) $rows->sum('total');

        return [
            'summary' => $count === 0
                ? 'No sales have been posted today yet.'
                : $this->money($total).' across '.$count.' '.$this->plural($count, 'sale').($voided > 0 ? ', and '.$voided.' voided.' : '.'),
            'columns' => [['key' => 'mode', 'label' => 'Mode'], ['key' => 'count', 'label' => 'Sales'], ['key' => 'total', 'label' => 'Value']],
            'rows' => $rows->map(fn ($r) => [
                'mode' => ucfirst(strtolower((string) $r->sale_mode)),
                'count' => (int) $r->n,
                'total' => $this->money((float) $r->total),
            ])->all(),
        ];
    }

    /**
     * @return array{summary: string, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}
     */
    private function recentSales(User $user): array
    {
        $sales = Sale::where('branch_id', $this->branchId)
            ->where('status', 'POSTED')
            ->with('customer:id,name')
            ->orderByDesc('posted_at')
            ->limit(self::LIMIT)
            ->get();

        return [
            'summary' => $sales->isEmpty() ? 'No sales have been posted at this branch yet.' : 'The last '.$sales->count().' posted '.$this->plural($sales->count(), 'sale').', newest first.',
            'columns' => [['key' => 'doc_number', 'label' => 'Number'], ['key' => 'when', 'label' => 'When'], ['key' => 'customer', 'label' => 'Customer'], ['key' => 'total', 'label' => 'Total']],
            'rows' => $sales->map(fn (Sale $sale) => [
                'doc_number' => $sale->doc_number,
                'when' => $sale->posted_at?->format('j M H:i') ?? '—',
                'customer' => $sale->customer?->name ?? 'Walk-in',
                'total' => $this->money((float) $sale->grand_total),
            ])->all(),
        ];
    }

    /**
     * @return array{summary: string, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}
     */
    private function recentInvoices(User $user): array
    {
        $invoices = Sale::where('branch_id', $this->branchId)
            ->where('status', 'POSTED')
            ->where('sale_mode', 'WHOLESALE')
            ->with('customer:id,name')
            ->orderByDesc('posted_at')
            ->limit(self::LIMIT)
            ->get();

        $outstanding = $this->outstandingBySale($invoices->pluck('id')->all());
        $owed = array_sum(array_map('floatval', $outstanding));

        return [
            'summary' => $invoices->isEmpty()
                ? 'No invoices have been raised at this branch yet.'
                : 'The last '.$invoices->count().' '.$this->plural($invoices->count(), 'invoice').'. '.($owed > 0 ? $this->money($owed).' of that is still owed.' : 'All of them are settled.'),
            'columns' => [['key' => 'doc_number', 'label' => 'Invoice'], ['key' => 'when', 'label' => 'When'], ['key' => 'customer', 'label' => 'Customer'], ['key' => 'total', 'label' => 'Total'], ['key' => 'balance', 'label' => 'Still owed']],
            'rows' => $invoices->map(fn (Sale $sale) => [
                'doc_number' => $sale->doc_number,
                'when' => $sale->posted_at?->format('j M') ?? '—',
                'customer' => $sale->customer?->name ?? 'Walk-in',
                'total' => $this->money((float) $sale->grand_total),
                'balance' => $this->money((float) ($outstanding[$sale->id] ?? '0')),
            ])->all(),
        ];
    }

    /**
     * What is still owed on each of these sales. The receivable ledger already
     * nets the invoice against its receipts and credit notes, exactly as
     * ReceiptService::outstandingBalance() reads it one sale at a time.
     *
     * @param  list<string>  $saleIds
     * @return array<string, string>
     */
    private function outstandingBySale(array $saleIds): array
    {
        if ($saleIds === []) {
            return [];
        }

        return DB::table('accounts_receivables')
            ->whereIn('sale_id', $saleIds)
            ->selectRaw('sale_id, COALESCE(SUM(amount), 0) as balance')
            ->groupBy('sale_id')
            ->pluck('balance', 'sale_id')
            ->map(fn ($balance) => bccomp((string) $balance, '0', 4) > 0 ? (string) $balance : '0.0000')
            ->all();
    }

    /**
     * @return array{summary: string, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}
     */
    private function debtors(User $user): array
    {
        $rows = DB::table('customer_credits as cc')
            ->join('customers as c', 'c.id', '=', 'cc.customer_id')
            ->where('c.organisation_id', $this->organisationId)
            ->where('cc.current_balance', '>', 0)
            ->orderByDesc('cc.current_balance')
            ->limit(self::LIMIT)
            ->get(['c.name', 'c.code', 'c.payment_terms_days', 'cc.current_balance', 'cc.credit_limit', 'cc.on_hold']);

        $total = DB::table('customer_credits as cc')
            ->join('customers as c', 'c.id', '=', 'cc.customer_id')
            ->where('c.organisation_id', $this->organisationId)
            ->where('cc.current_balance', '>', 0)
            ->sum('cc.current_balance');

        return [
            'summary' => $rows->isEmpty()
                ? 'No customer owes anything at the moment.'
                : $this->money((float) $total).' is owed in total. The largest '.$this->plural($rows->count(), 'balance').':',
            'columns' => [['key' => 'customer', 'label' => 'Customer'], ['key' => 'owed', 'label' => 'Owed'], ['key' => 'limit', 'label' => 'Limit'], ['key' => 'terms', 'label' => 'Terms']],
            'rows' => $rows->map(fn ($r) => [
                'customer' => $r->name.($r->on_hold ? ' (on hold)' : ''),
                'owed' => $this->money((float) $r->current_balance),
                'limit' => (float) $r->credit_limit > 0 ? $this->money((float) $r->credit_limit) : 'None set',
                'terms' => $r->payment_terms_days ? $r->payment_terms_days.' days' : 'Cash',
            ])->all(),
        ];
    }

    /**
     * @return array{summary: string, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}
     */
    private function paymentsToday(User $user): array
    {
        $rows = DB::table('payments')
            ->where('branch_id', $this->branchId)
            ->where('status', '!=', 'VOIDED')
            ->whereDate('received_at', now()->toDateString())
            ->selectRaw('method, COUNT(*) as n, COALESCE(SUM(amount), 0) as total')
            ->groupBy('method')
            ->orderByDesc('total')
            ->get();

        $total = (float) $rows->sum('total');

        return [
            'summary' => $rows->isEmpty() ? 'No receipts have been recorded today.' : $this->money($total).' received today.',
            'columns' => [['key' => 'method', 'label' => 'Method'], ['key' => 'count', 'label' => 'Receipts'], ['key' => 'total', 'label' => 'Value']],
            'rows' => $rows->map(fn ($r) => [
                'method' => ucfirst(strtolower(str_replace('_', ' ', (string) $r->method))),
                'count' => (int) $r->n,
                'total' => $this->money((float) $r->total),
            ])->all(),
        ];
    }

    /**
     * @return array{summary: string, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}
     */
    private function lowStock(User $user): array
    {
        $available = '(SELECT COALESCE(SUM(b.qty_on_hand - b.qty_reserved), 0) FROM stock_balances b WHERE b.product_id = p.id AND b.store_id IN (SELECT id FROM stores WHERE branch_id = ?))';
        $rows = DB::table('products as p')
            ->where('p.organisation_id', $this->organisationId)
            ->where('p.is_active', true)
            ->where('p.reorder_point', '>', 0)
            ->selectRaw("p.name, p.code, p.reorder_point, {$available} as available", [$this->branchId])
            ->whereRaw("{$available} < p.reorder_point", [$this->branchId])
            ->orderByRaw("{$available} / p.reorder_point", [$this->branchId])
            ->limit(self::LIMIT)
            ->get();

        return [
            'summary' => $rows->isEmpty()
                ? 'Nothing is below its reorder point at this branch.'
                : $rows->count().' '.$this->plural($rows->count(), 'product').' at or below the reorder point'.($rows->count() === self::LIMIT ? ' (the ten most urgent)' : '').':',
            'columns' => [['key' => 'product', 'label' => 'Product'], ['key' => 'available', 'label' => 'Available'], ['key' => 'reorder_point', 'label' => 'Reorder at']],
            'rows' => $rows->map(fn ($r) => [
                'product' => $r->name,
                'available' => $this->qty((float) $r->available),
                'reorder_point' => $this->qty((float) $r->reorder_point),
            ])->all(),
        ];
    }

    /**
     * @return array{summary: string, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}
     */
    private function expiring(User $user): array
    {
        $rows = DB::table('stock_balances as b')
            ->join('product_batches as pb', 'pb.id', '=', 'b.batch_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->whereIn('b.store_id', $this->storeIds())
            ->where('b.qty_on_hand', '>', 0)
            ->whereNotNull('pb.expiry_date')
            ->whereDate('pb.expiry_date', '<=', now()->addDays(90)->toDateString())
            ->selectRaw('p.name, pb.batch_number, pb.expiry_date, SUM(b.qty_on_hand) as qty')
            ->groupBy('p.name', 'pb.batch_number', 'pb.expiry_date')
            ->orderBy('pb.expiry_date')
            ->limit(self::LIMIT)
            ->get();

        return [
            'summary' => $rows->isEmpty()
                ? 'Nothing in this branch expires within ninety days.'
                : $rows->count().' '.$this->plural($rows->count(), 'batch', 'batches').' expiring within ninety days, soonest first:',
            'columns' => [['key' => 'product', 'label' => 'Product'], ['key' => 'batch', 'label' => 'Batch'], ['key' => 'expires', 'label' => 'Expires'], ['key' => 'qty', 'label' => 'Quantity']],
            'rows' => $rows->map(fn ($r) => [
                'product' => $r->name,
                'batch' => $r->batch_number,
                'expires' => date('j M Y', strtotime((string) $r->expiry_date)),
                'qty' => $this->qty((float) $r->qty),
            ])->all(),
        ];
    }

    /**
     * @return array{summary: string, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}
     */
    private function recentPurchases(User $user): array
    {
        // A purchase order has no stored total: it is its lines, as the
        // Purchase Orders screen computes it.
        $orders = PurchaseOrder::where('branch_id', $this->branchId)
            ->with('supplier:id,name')
            ->addSelect(['ordered_value' => DB::table('purchase_order_lines')
                ->whereColumn('purchase_order_lines.purchase_order_id', 'purchase_orders.id')
                ->selectRaw('COALESCE(SUM(qty_ordered * unit_price), 0)')])
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get();

        return [
            'summary' => $orders->isEmpty() ? 'No purchase orders have been raised at this branch yet.' : 'The last '.$orders->count().' purchase '.$this->plural($orders->count(), 'order').':',
            'columns' => [['key' => 'doc_number', 'label' => 'Order'], ['key' => 'supplier', 'label' => 'Supplier'], ['key' => 'status', 'label' => 'Status'], ['key' => 'total', 'label' => 'Value']],
            'rows' => $orders->map(fn (PurchaseOrder $po) => [
                'doc_number' => $po->doc_number,
                'supplier' => $po->supplier?->name ?? '—',
                'status' => ucfirst(strtolower(str_replace('_', ' ', (string) $po->status))),
                'total' => $this->money((float) $po->ordered_value),
            ])->all(),
        ];
    }

    /**
     * @return array{summary: string, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}
     */
    private function approvals(User $user): array
    {
        $storeIds = $this->storeIds();
        $queues = [
            'Requisitions' => ['requisition.approve', fn () => Requisition::where('branch_id', $this->branchId)->where('status', 'PENDING_APPROVAL')->count()],
            'Purchase orders' => ['po.approve', fn () => PurchaseOrder::where('branch_id', $this->branchId)->where('status', 'PENDING_APPROVAL')->count()],
            'Stock adjustments' => ['stock.adjust.approve', fn () => StockAdjustment::whereIn('store_id', $storeIds)->where('approval_status', 'PENDING')->count()],
            'Transfers' => ['stock.transfer.approve', fn () => StockTransfer::whereIn('from_store_id', $storeIds)->where('status', 'DRAFT')->count()],
            'Stock counts' => ['stock.count.post', fn () => StockCount::whereIn('store_id', $storeIds)->where('status', 'REVIEW')->count()],
            'Customer returns' => ['return.post', fn () => CustomerReturn::where('branch_id', $this->branchId)->where('status', 'DRAFT')->count()],
        ];

        $rows = [];
        $waiting = 0;
        foreach ($queues as $label => [$permission, $count]) {
            if (! $user->can($permission)) {
                continue;
            }
            $n = $count();
            $waiting += $n;
            $rows[] = ['queue' => $label, 'waiting' => $n];
        }

        if ($rows === []) {
            return ['summary' => 'You do not approve anything, so there is nothing waiting for you.', 'columns' => [], 'rows' => []];
        }

        return [
            'summary' => $waiting === 0 ? 'Nothing is waiting for your approval.' : $waiting.' '.$this->plural($waiting, 'item').' waiting for you:',
            'columns' => [['key' => 'queue', 'label' => 'Queue'], ['key' => 'waiting', 'label' => 'Waiting']],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{summary: string, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}
     */
    private function alerts(User $user): array
    {
        $visible = array_values(array_filter(
            ['finance.ar.view', 'finance.ap.view', 'stock.view', 'licence.view'],
            fn (string $permission) => $user->can($permission)
        ));

        if ($visible === []) {
            return ['summary' => 'Alerts are not part of what your role sees.', 'columns' => [], 'rows' => []];
        }

        $alerts = Alert::where('branch_id', $this->branchId)
            ->whereIn('permission', $visible)
            ->open()
            ->whereNull('acknowledged_at')
            ->orderByRaw("FIELD(severity, 'CRITICAL', 'WARNING', 'INFO')")
            ->orderByRaw('due_date IS NULL, due_date')
            ->limit(self::LIMIT)
            ->get();

        return [
            'summary' => $alerts->isEmpty() ? 'There are no open alerts for you.' : $alerts->count().' open '.$this->plural($alerts->count(), 'alert').', most serious first:',
            'columns' => [['key' => 'severity', 'label' => 'Severity'], ['key' => 'title', 'label' => 'Alert'], ['key' => 'due', 'label' => 'Due']],
            'rows' => $alerts->map(fn (Alert $alert) => [
                'severity' => ucfirst(strtolower($alert->severity)),
                'title' => $alert->title,
                'due' => $alert->due_date?->format('j M Y') ?? '—',
            ])->all(),
        ];
    }

    /**
     * @return array{summary: string, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}
     */
    private function whoami(User $user): array
    {
        $branch = DB::table('branches')->where('id', $this->branchId)->first(['name', 'code']);
        $organisation = DB::table('organisations')->where('id', $this->organisationId)->value('name');

        return [
            'summary' => 'You are signed in as '.$user->name.' at '.($branch->name ?? 'your branch').', '.$organisation.'.',
            'columns' => [['key' => 'field', 'label' => 'Detail'], ['key' => 'value', 'label' => '']],
            'rows' => [
                ['field' => 'Name', 'value' => $user->name],
                ['field' => 'Email', 'value' => $user->email],
                ['field' => 'Institution', 'value' => (string) $organisation],
                ['field' => 'Branch', 'value' => ($branch->name ?? '—').' ('.($branch->code ?? '—').')'],
                ['field' => 'Roles', 'value' => $user->getRoleNames()->implode(', ') ?: 'None'],
            ],
        ];
    }

    /**
     * @return array{summary: string, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}
     */
    private function help(User $user): array
    {
        $available = $this->availableTo($user);

        return [
            'summary' => 'Type any of these. Your role decides what is on the list.',
            'columns' => [['key' => 'command', 'label' => 'Command'], ['key' => 'hint', 'label' => 'Answers']],
            'rows' => array_map(fn (array $c) => ['command' => $c['command'], 'hint' => $c['hint']], $available),
        ];
    }

    /**
     * @return list<string>
     */
    private function storeIds(): array
    {
        return Store::where('branch_id', $this->branchId)->pluck('id')->all();
    }

    private function money(float $amount): string
    {
        return 'KES '.number_format($amount, 2);
    }

    private function qty(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 2, '.', ','), '0'), '.');
    }

    private function plural(int $count, string $singular, ?string $plural = null): string
    {
        return $count === 1 ? $singular : ($plural ?? $singular.'s');
    }
}
