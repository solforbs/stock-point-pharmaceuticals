<?php

namespace App\Http\Controllers\Api;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Services\Alerts\AlertScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Part 17 — the alert centre: payment deadlines and shelf-life risk for the
 * active branch. Alerts are written only by `alerts:scan`; a user may
 * acknowledge one (it stays listed, marked seen) but never create or delete
 * one, so the list always reflects the books rather than someone's opinion.
 */
class AlertController extends ApiController
{
    /** GET /api/alerts?category=&severity=&include_acknowledged=&include_resolved= */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['nullable', 'in:'.implode(',', Alert::CATEGORIES)],
            'severity' => ['nullable', 'in:INFO,WARNING,CRITICAL'],
            'include_acknowledged' => ['nullable', 'boolean'],
            'include_resolved' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $permissions = $this->visiblePermissions($request);

        $alerts = Alert::where('branch_id', $this->branchId($request))
            ->whereIn('permission', $permissions)
            ->when(! $request->boolean('include_resolved'), fn ($q) => $q->open())
            ->when(! $request->boolean('include_acknowledged'), fn ($q) => $q->whereNull('acknowledged_at'))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['severity'] ?? null, fn ($q, $v) => $q->where('severity', $v))
            ->orderByRaw("FIELD(severity, 'CRITICAL', 'WARNING', 'INFO')")
            ->orderByRaw('due_date IS NULL, due_date')
            ->paginate($request->integer('per_page', 50));

        return response()->json($alerts);
    }

    /**
     * GET /api/alerts/summary — what the bell shows: open, unacknowledged
     * counts by category and severity for the active branch.
     */
    public function summary(Request $request): JsonResponse
    {
        $permissions = $this->visiblePermissions($request);

        // A grouped count is a plain aggregate, not a row of the model.
        $rows = DB::table('alerts')
            ->where('branch_id', $this->branchId($request))
            ->whereIn('permission', $permissions)
            ->whereNull('resolved_at')->whereNull('acknowledged_at')
            ->groupBy('category', 'severity')
            ->select('category', 'severity', DB::raw('COUNT(*) as total'))
            ->get();

        $byCategory = [];
        foreach (Alert::CATEGORIES as $category) {
            $byCategory[$category] = ['total' => 0, 'critical' => 0, 'warning' => 0, 'info' => 0];
        }

        $total = 0;
        $critical = 0;
        foreach ($rows as $row) {
            if (! isset($byCategory[$row->category])) {
                continue;
            }
            $byCategory[$row->category]['total'] += (int) $row->total;
            $byCategory[$row->category][strtolower($row->severity)] = (int) $row->total;
            $total += (int) $row->total;
            if ($row->severity === 'CRITICAL') {
                $critical += (int) $row->total;
            }
        }

        return response()->json([
            'total' => $total,
            'critical' => $critical,
            'by_category' => $byCategory,
            'last_scan_at' => Alert::where('branch_id', $this->branchId($request))->max('last_seen_at'),
        ]);
    }

    /** POST /api/alerts/{alert}/acknowledge */
    public function acknowledge(Request $request, string $alert): JsonResponse
    {
        $alert = Alert::where('branch_id', $this->branchId($request))->findOrFail($alert);
        $this->requirePermission($request, $alert->permission);

        $alert->update(['acknowledged_by' => $request->user()->id, 'acknowledged_at' => now()]);
        AuditLog::record('ALERT_ACKNOWLEDGED', 'alert', $alert->id, ['reference' => $alert->alert_key]);

        return response()->json($alert->fresh());
    }

    /** POST /api/alerts/acknowledge-all — clears the bell without touching what caused it. */
    public function acknowledgeAll(Request $request): JsonResponse
    {
        $data = $request->validate(['category' => ['nullable', 'in:'.implode(',', Alert::CATEGORIES)]]);

        $count = Alert::where('branch_id', $this->branchId($request))
            ->whereIn('permission', $this->visiblePermissions($request))
            ->open()->whereNull('acknowledged_at')
            ->when($data['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->update(['acknowledged_by' => $request->user()->id, 'acknowledged_at' => now()]);

        AuditLog::record('ALERTS_ACKNOWLEDGED', 'branch', $this->branchId($request), [
            'reference' => $data['category'] ?? 'ALL', 'after_json' => ['count' => $count],
        ]);

        return response()->json(['acknowledged' => $count]);
    }

    /** POST /api/alerts/scan — recompute now instead of waiting for the morning run. */
    public function scan(Request $request, AlertScanner $scanner): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');

        $result = $scanner->scan(Branch::findOrFail($this->branchId($request)));
        AuditLog::record('ALERTS_SCANNED', 'branch', $this->branchId($request), ['after_json' => $result]);

        return response()->json($result);
    }

    /**
     * The alert permissions this user holds. An alert carries the permission
     * needed to see it, so a cashier is never shown what the books owe.
     *
     * @return list<string>
     */
    private function visiblePermissions(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(403, 'Not authenticated.');
        }

        $held = array_values(array_filter(
            ['finance.ar.view', 'finance.ap.view', 'stock.view'],
            fn (string $permission) => $user->can($permission)
        ));

        if ($held === []) {
            throw new HttpException(403, "You do not have any of the 'finance.ar.view', 'finance.ap.view' or 'stock.view' permissions.");
        }

        return $held;
    }
}
