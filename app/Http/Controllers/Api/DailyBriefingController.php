<?php

namespace App\Http\Controllers\Api;

use App\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The reminder a user sees when they first open the system each day: how
 * sales are going, and the debts, supplier payments, expiring stock and
 * licence renewals that need someone today. It reads what the alert scan
 * and the sales ledger already hold; it never writes anything.
 */
class DailyBriefingController extends ApiController
{
    /** How many items of each kind the reminder lists before "and N more". */
    private const ITEMS_PER_CATEGORY = 3;

    /** GET /api/reminders/today */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $this->branchId($request);

        $permissions = array_values(array_filter(
            ['finance.ar.view', 'finance.ap.view', 'stock.view', 'licence.view'],
            fn (string $permission) => $user->can($permission)
        ));

        $categories = [];
        if ($permissions !== []) {
            $open = Alert::where('branch_id', $branchId)->open()->whereNull('acknowledged_at')->whereIn('permission', $permissions)
                ->orderByRaw("FIELD(severity, 'CRITICAL', 'WARNING', 'INFO')")
                ->orderByRaw('due_date IS NULL, due_date')
                ->get(['id', 'category', 'severity', 'title', 'detail', 'link', 'due_date', 'amount']);

            foreach ($open->groupBy('category') as $category => $alerts) {
                $categories[] = [
                    'category' => $category,
                    'total' => $alerts->count(),
                    'critical' => $alerts->where('severity', 'CRITICAL')->count(),
                    'amount' => $alerts->whereNotNull('amount')->isNotEmpty()
                        ? number_format((float) $alerts->sum('amount'), 4, '.', '')
                        : null,
                    'items' => $alerts->take(self::ITEMS_PER_CATEGORY)->values(),
                ];
            }
        }

        return response()->json([
            'name' => $user->name,
            'date' => now()->toDateString(),
            'sales' => $user->can('sale.view') ? [
                'today' => $this->salesOn($branchId, now()),
                'yesterday' => $this->salesOn($branchId, now()->subDay()),
            ] : null,
            'categories' => $categories,
        ]);
    }

    /**
     * @return array{count: int, total: string}
     */
    private function salesOn(string $branchId, Carbon $day): array
    {
        $row = DB::table('sales')->where('branch_id', $branchId)->where('status', 'POSTED')
            ->whereBetween('posted_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(grand_total), 0) as total')->first();

        return ['count' => (int) $row->count, 'total' => number_format((float) $row->total, 4, '.', '')];
    }
}
