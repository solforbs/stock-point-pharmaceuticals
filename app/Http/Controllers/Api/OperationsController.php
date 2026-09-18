<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Services\Admin\BackupFailedException;
use App\Services\Admin\BackupService;
use App\Services\Admin\SystemHealthService;
use App\Services\Tax\EtimsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Part 17 — operations: system health, database backups and the sync
 * centre. Health and backups are administrator-only (admin.settings); the
 * sync centre is also open to anyone who can see sales, because a branch
 * supervisor needs to know whether the tills are reaching the server.
 */
class OperationsController extends ApiController
{
    /** GET /api/admin/system-health */
    public function systemHealth(Request $request, SystemHealthService $health): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');

        return response()->json($health->report($this->organisationId($request)) + [
            'failed_jobs' => $health->recentFailures(),
        ]);
    }

    /** POST /api/admin/system-health/retry-failed-jobs {ids?} — pushes failed jobs back onto their queue. */
    public function retryFailedJobs(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $data = $request->validate([
            'ids' => ['nullable', 'array', 'max:100'],
            'ids.*' => ['string', 'max:64'],
        ]);

        $ids = array_values(array_unique($data['ids'] ?? []));
        $before = DB::table('failed_jobs')->count();
        Artisan::call('queue:retry', ['id' => $ids === [] ? ['all'] : $ids]);
        $retried = $before - DB::table('failed_jobs')->count();

        AuditLog::record('FAILED_JOBS_RETRIED', 'failed_job', $ids === [] ? 'all' : implode(',', $ids), [
            'after_json' => ['requested' => $ids === [] ? 'all' : $ids, 'retried' => $retried],
        ]);

        return response()->json(['retried' => $retried, 'remaining_failed' => DB::table('failed_jobs')->count()]);
    }

    /** POST /api/admin/system-health/forget-failed-job/{id} — discards one failed job for good. */
    public function forgetFailedJob(Request $request, string $id): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');

        $job = DB::table('failed_jobs')->where('uuid', $id)->first();
        if (! $job) {
            return $this->error('FAILED_JOB_NOT_FOUND', 'That failed job no longer exists; it may already have been retried or forgotten.', 404);
        }

        Artisan::call('queue:forget', ['id' => $id]);
        AuditLog::record('FAILED_JOB_FORGOTTEN', 'failed_job', $id, [
            'before_json' => ['queue' => $job->queue, 'failed_at' => $job->failed_at, 'job' => json_decode((string) $job->payload, true)['displayName'] ?? null],
        ]);

        return response()->json(['forgotten' => $id]);
    }

    /** GET /api/admin/backups */
    public function backups(Request $request, BackupService $backups): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');

        return response()->json([
            'data' => $backups->list(),
            'retention_days' => (int) config('backup.retention_days'),
            'mysqldump_available' => $backups->binary() !== null,
            'schedule' => 'Daily at 02:00 (backup:run), when the scheduler cron is installed.',
        ]);
    }

    /** POST /api/admin/backups — takes a dump now, synchronously. */
    public function createBackup(Request $request, BackupService $backups): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $data = $request->validate(['kind' => ['nullable', 'in:database,full']]);

        try {
            $file = ($data['kind'] ?? 'database') === 'full' ? $backups->createFull() : $backups->create();
        } catch (BackupFailedException $e) {
            return $this->error('BACKUP_FAILED', $e->getMessage(), 422);
        }

        AuditLog::record('BACKUP_CREATED', 'backup', $file['name'], ['reference' => $file['name'], 'after_json' => $file]);

        return response()->json($file, 201);
    }

    /** GET /api/admin/backups/{name}/download — the name must be one the listing returns. */
    public function downloadBackup(Request $request, BackupService $backups, string $name): BinaryFileResponse
    {
        $this->requirePermission($request, 'admin.settings');

        $path = preg_match(BackupService::NAME_PATTERN, $name) === 1 ? $backups->pathFor($name) : null;
        if ($path === null) {
            throw new HttpException(404, 'No backup with that name exists.');
        }

        AuditLog::record('BACKUP_DOWNLOADED', 'backup', $name, ['reference' => $name]);

        return response()->download($path, $name, [
            'Content-Type' => str_ends_with($name, '.zip') ? 'application/zip' : 'application/gzip',
        ]);
    }

    /**
     * GET /api/admin/sync-status — Part 17.5. There is no offline queue yet,
     * so this reports what is real: the session, what each terminal posted in
     * the last 24 hours, and the eTIMS transmission queue. Idempotent replays
     * are answered from the original document and are not logged separately.
     */
    public function syncStatus(Request $request, EtimsService $etims): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->can('admin.settings') && ! $user->can('sale.view'))) {
            throw new HttpException(403, "You do not have the 'admin.settings' or 'sale.view' permission.");
        }

        $branchId = $this->branchId($request);
        $since = now()->subDay();
        $branch = Branch::find($branchId);

        // A sale is stamped with posted_at, not created_at (it is never a draft).
        $terminals = DB::table('sales')->where('branch_id', $branchId)->where('posted_at', '>=', $since)
            ->groupBy('terminal_id')
            ->selectRaw('terminal_id, COUNT(*) as sales, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as voided, MAX(posted_at) as last_posted_at', ['VOIDED'])
            ->orderByDesc('last_posted_at')->get()
            ->map(fn (object $row) => [
                'terminal_id' => $row->terminal_id,
                'sales' => (int) $row->sales,
                'voided' => (int) $row->voided,
                'last_posted_at' => $row->last_posted_at ? Carbon::parse($row->last_posted_at)->toIso8601String() : null,
            ])->values();

        $count = fn (string $table, string $column = 'created_at') => DB::table($table)->where('branch_id', $branchId)->where($column, '>=', $since)->count();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'session' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'username' => $user->username ?? null,
                'branch' => $branch?->only(['id', 'code', 'name']),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'auth' => $request->bearerToken() !== null ? 'token' : 'session',
            ],
            'offline_queue' => [
                'available' => false,
                'pending' => 0,
                'note' => 'Offline selling is not built yet: every sale posts straight to the server, so nothing waits on a device.',
            ],
            'replays' => [
                'recorded' => false,
                'note' => 'A retried request with the same Idempotency-Key returns the original document (X-Idempotent-Replay: true) and is not logged separately.',
            ],
            'activity_24h' => [
                'since' => $since->toIso8601String(),
                'sales' => $count('sales', 'posted_at'),
                'quotations' => $count('quotations'),
                'sales_orders' => $count('sales_orders'),
                'payments' => $count('payments'),
                'terminals' => $terminals,
            ],
            'etims' => $etims->queueSummary($branchId),
        ]);
    }
}
