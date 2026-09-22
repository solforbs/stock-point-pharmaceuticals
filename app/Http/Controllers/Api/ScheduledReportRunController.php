<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\ScheduledReportRun;
use App\Services\Reports\ReportRunner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Part 20.3 — the Report inbox. Every scheduled-report run is kept with its
 * CSV so someone can open it in the app, proof-read it and mark it verified
 * or flagged. A person only sees runs of reports they could open on screen.
 */
class ScheduledReportRunController extends ApiController
{
    /** Rows shown in the in-app preview; the download has them all. */
    private const PREVIEW_ROWS = 200;

    /** GET /api/scheduled-report-runs — newest first, with a count per review status. */
    public function index(Request $request, ReportRunner $runner): JsonResponse
    {
        $this->requireAnyPermission($request, ['report.review', 'report.schedule']);
        $filters = $request->validate([
            'review_status' => ['nullable', Rule::in(ScheduledReportRun::REVIEW_STATUSES)],
            'status' => ['nullable', Rule::in(ScheduledReportRun::STATUSES)],
            'scheduled_report_id' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $runs = $this->visible($request, $runner)
            ->with(['reviewer:id,name', 'requester:id,name'])
            ->when($filters['review_status'] ?? null, fn ($q, $v) => $q->where('review_status', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['scheduled_report_id'] ?? null, fn ($q, $v) => $q->where('scheduled_report_id', $v))
            ->orderByDesc('generated_at')
            ->paginate($filters['per_page'] ?? 25);

        $counts = $this->visible($request, $runner)
            ->where('status', 'SUCCESS')
            ->selectRaw('review_status, COUNT(*) AS n')
            ->groupBy('review_status')
            ->pluck('n', 'review_status');

        return response()->json($runs->toArray() + [
            'counts' => collect(ScheduledReportRun::REVIEW_STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)]),
        ]);
    }

    /** GET /api/scheduled-report-runs/{id} — the run with a preview of its CSV. */
    public function show(Request $request, string $run, ReportRunner $runner): JsonResponse
    {
        $this->requireAnyPermission($request, ['report.review', 'report.schedule']);
        $model = $this->find($request, $runner, $run);

        return response()->json($model->load(['reviewer:id,name', 'requester:id,name'])->toArray() + [
            'has_file' => $model->hasFile() && Storage::disk('local')->exists((string) $model->csv_path),
            'preview' => $this->preview($model),
        ]);
    }

    /** GET /api/scheduled-report-runs/{id}/download — the archived CSV, exactly as mailed. */
    public function download(Request $request, string $run, ReportRunner $runner): StreamedResponse|JsonResponse
    {
        $this->requireAnyPermission($request, ['report.review', 'report.schedule']);
        $model = $this->find($request, $runner, $run);
        if (! $model->hasFile() || ! Storage::disk('local')->exists((string) $model->csv_path)) {
            return $this->error('NOT_FOUND', 'This run has no saved file.', 404);
        }

        AuditLog::record('SCHEDULED_REPORT_RUN_DOWNLOADED', 'scheduled_report_run', $model->id, ['reference' => $model->report_key]);

        return Storage::disk('local')->download((string) $model->csv_path, (string) $model->csv_filename, ['Content-Type' => 'text/csv']);
    }

    /** POST /api/scheduled-report-runs/{id}/review — mark a run verified or flagged. */
    public function review(Request $request, string $run, ReportRunner $runner): JsonResponse
    {
        $this->requirePermission($request, 'report.review');
        $model = $this->find($request, $runner, $run);
        $data = $request->validate([
            'review_status' => ['required', Rule::in(['VERIFIED', 'FLAGGED'])],
            'notes' => ['nullable', 'string', 'max:2000', 'required_if:review_status,FLAGGED'],
        ], [
            'notes.required_if' => 'Say what is wrong with the report.',
        ]);
        if (! $model->hasFile()) {
            return $this->error('NOTHING_TO_REVIEW', 'This run failed before a report was produced, so there is nothing to review.', 422);
        }

        $before = $model->only(['review_status', 'reviewed_by', 'review_notes']);
        $model->forceFill([
            'review_status' => $data['review_status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['notes'] ?? null,
        ])->save();

        AuditLog::record('SCHEDULED_REPORT_RUN_REVIEWED', 'scheduled_report_run', $model->id, [
            'reference' => $model->report_key,
            'before_json' => $before,
            'after_json' => $model->only(['review_status', 'reviewed_by', 'review_notes']),
        ]);

        return response()->json($model->load(['reviewer:id,name', 'requester:id,name'])->toArray());
    }

    /**
     * Runs in the active branch whose report the signed-in user may open.
     *
     * @return Builder<ScheduledReportRun>
     */
    private function visible(Request $request, ReportRunner $runner): Builder
    {
        $keys = collect($runner->catalogueFor($request->user()))->pluck('key')->all();

        return ScheduledReportRun::where('branch_id', $this->branchId($request))->whereIn('report_key', $keys);
    }

    private function find(Request $request, ReportRunner $runner, string $id): ScheduledReportRun
    {
        return $this->visible($request, $runner)->findOrFail($id);
    }

    /**
     * The first rows of the archived CSV, keyed by the report's columns.
     *
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>, truncated: bool}|null
     */
    private function preview(ScheduledReportRun $run): ?array
    {
        if (! $run->hasFile()) {
            return null;
        }
        $stream = Storage::disk('local')->readStream((string) $run->csv_path);
        if ($stream === null) {
            return null;
        }

        $header = fgetcsv($stream, escape: '\\') ?: [];
        $columns = $run->columns_json ?? [];
        if (count($columns) !== count($header)) {
            // A file without matching column types still previews, as text.
            $columns = array_map(fn ($label, $i) => ['key' => "c{$i}", 'label' => (string) $label, 'type' => 'text'], $header, array_keys($header));
        }

        $rows = [];
        $truncated = false;
        while (($line = fgetcsv($stream, escape: '\\')) !== false) {
            if ($line === [null]) {
                continue;
            }
            if (count($rows) >= self::PREVIEW_ROWS) {
                $truncated = true;
                break;
            }
            $row = [];
            foreach ($columns as $i => $column) {
                $value = $line[$i] ?? null;
                $row[$column['key']] = ($column['type'] ?? '') === 'bool' && $value !== null && $value !== '' ? $value === 'yes' : $value;
            }
            $rows[] = $row;
        }
        fclose($stream);

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $run->totals_json ?? [], 'truncated' => $truncated];
    }
}
