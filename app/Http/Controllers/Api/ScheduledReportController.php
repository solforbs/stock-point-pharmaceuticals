<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\ScheduledReport;
use App\Services\Reports\ReportCatalogue;
use App\Services\Reports\ReportRunner;
use App\Services\Reports\ScheduledReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Part 20.3 — catalogue reports mailed on a schedule. A schedule runs with
 * its creator's permissions in the branch it was created in, so it can
 * never deliver a report its owner could not open on screen.
 */
class ScheduledReportController extends ApiController
{
    /** Report filters a schedule may fix; from/to come from the frequency. */
    private const FILTER_RULES = [
        'customer_id' => ['nullable', 'uuid'],
        'product_id' => ['nullable', 'uuid'],
        'store_id' => ['nullable', 'uuid'],
        'category_id' => ['nullable', 'uuid'],
        'threshold_pct' => ['nullable', 'numeric', 'min:0'],
        'threshold' => ['nullable', 'numeric', 'min:0'],
        'dead_days' => ['nullable', 'integer', 'min:1'],
        'open_hour' => ['nullable', 'integer', 'between:0,23'],
        'close_hour' => ['nullable', 'integer', 'between:1,24'],
    ];

    private const AUDITED = ['report_key', 'filters_json', 'frequency', 'run_at', 'weekday', 'month_day', 'recipients', 'is_active'];

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'report.schedule');
        $catalogue = ReportCatalogue::all();

        $rows = ScheduledReport::where('branch_id', $this->branchId($request))
            ->with('creator:id,name')
            ->orderByDesc('is_active')->orderBy('next_run_at')
            ->get()
            ->map(fn (ScheduledReport $s) => $s->toArray() + ['report_title' => $catalogue[$s->report_key]['title'] ?? $s->report_key]);

        return response()->json(['data' => $rows]);
    }

    public function show(Request $request, string $schedule): JsonResponse
    {
        $this->requirePermission($request, 'report.schedule');

        return response()->json($this->present($this->find($request, $schedule)));
    }

    public function store(Request $request, ReportRunner $runner): JsonResponse
    {
        $this->requirePermission($request, 'report.schedule');
        $data = $this->validated($request, null);
        if ($refusal = $this->refuseUnrunnable($request, $runner, $data['report_key'])) {
            return $refusal;
        }

        $schedule = new ScheduledReport($data + [
            'organisation_id' => $this->organisationId($request),
            'branch_id' => $this->branchId($request),
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);
        $schedule->next_run_at = $schedule->is_active ? $schedule->nextRunAfter(now()) : null;
        $schedule->save();

        AuditLog::record('SCHEDULED_REPORT_CREATED', 'scheduled_report', $schedule->id, ['reference' => $schedule->report_key, 'after_json' => $schedule->only(self::AUDITED)]);

        return response()->json($this->present($schedule), 201);
    }

    public function update(Request $request, string $schedule, ReportRunner $runner): JsonResponse
    {
        $this->requirePermission($request, 'report.schedule');
        $schedule = $this->find($request, $schedule);
        $data = $this->validated($request, $schedule);
        if ($refusal = $this->refuseUnrunnable($request, $runner, $data['report_key'] ?? $schedule->report_key)) {
            return $refusal;
        }

        $before = $schedule->only(self::AUDITED);
        $schedule->fill($data);
        if ($schedule->frequency !== 'WEEKLY') {
            $schedule->weekday = null;
        }
        if ($schedule->frequency !== 'MONTHLY') {
            $schedule->month_day = null;
        }
        $schedule->next_run_at = $schedule->is_active ? $schedule->nextRunAfter(now()) : null;
        $schedule->save();

        AuditLog::record('SCHEDULED_REPORT_UPDATED', 'scheduled_report', $schedule->id, [
            'reference' => $schedule->report_key, 'before_json' => $before, 'after_json' => $schedule->only(self::AUDITED), 'changed_fields' => array_keys($data),
        ]);

        return response()->json($this->present($schedule));
    }

    public function destroy(Request $request, string $schedule): JsonResponse
    {
        $this->requirePermission($request, 'report.schedule');
        $schedule = $this->find($request, $schedule);

        AuditLog::record('SCHEDULED_REPORT_DELETED', 'scheduled_report', $schedule->id, ['reference' => $schedule->report_key, 'before_json' => $schedule->only(self::AUDITED)]);
        $schedule->delete();

        return response()->json(['deleted' => true]);
    }

    /** POST /api/scheduled-reports/{id}/run-now — runs, archives and mails synchronously; the regular schedule is unchanged. */
    public function runNow(Request $request, string $schedule, ScheduledReportService $service): JsonResponse
    {
        $this->requirePermission($request, 'report.schedule');
        $schedule = $this->find($request, $schedule);

        $result = $service->run($schedule, triggeredBy: $request->user());

        return response()->json($result + ['schedule' => $this->present($schedule->fresh() ?? $schedule)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ScheduledReport $existing): array
    {
        $required = $existing ? 'sometimes' : 'required';
        $frequency = $request->input('frequency', $existing?->frequency);

        $data = $request->validate([
            'report_key' => [$required, 'string', Rule::in(array_keys(ReportCatalogue::all()))],
            'filters' => ['nullable', 'array'],
            'frequency' => [$required, 'in:DAILY,WEEKLY,MONTHLY'],
            'run_at' => [$required, 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'weekday' => [$frequency === 'WEEKLY' && ($request->has('frequency') || ! $existing) ? 'required' : 'nullable', 'integer', 'between:1,7'],
            'month_day' => [$frequency === 'MONTHLY' && ($request->has('frequency') || ! $existing) ? 'required' : 'nullable', 'integer', 'between:1,28'],
            'recipients' => [$required, 'array', 'min:1', 'max:20'],
            'recipients.*' => ['required', 'email:rfc', 'distinct'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('filters', $data)) {
            $key = $data['report_key'] ?? $existing?->report_key;
            $allowed = ReportCatalogue::find((string) $key)['filters'] ?? [];
            $rules = array_intersect_key(self::FILTER_RULES, array_flip($allowed));
            $filters = validator($data['filters'] ?? [], $rules)->validate();
            $data['filters_json'] = array_filter($filters, fn ($v) => $v !== null && $v !== '') ?: null;
            unset($data['filters']);
        } elseif ($existing && isset($data['report_key']) && $data['report_key'] !== $existing->report_key) {
            // Another report's filters would not apply to this one.
            $data['filters_json'] = null;
        }
        if (isset($data['recipients'])) {
            $data['recipients'] = array_values(array_map(fn ($e) => strtolower(trim((string) $e)), $data['recipients']));
        }

        return $data;
    }

    /**
     * A schedule may only name a report its creator can run right now.
     */
    private function refuseUnrunnable(Request $request, ReportRunner $runner, string $key): ?JsonResponse
    {
        $allowed = collect($runner->catalogueFor($request->user()))->pluck('key');
        if (! $allowed->contains($key)) {
            $needs = ReportCatalogue::find($key)['permissions'] ?? [];

            return $this->error('REPORT_FORBIDDEN', "You cannot schedule {$key}: it needs ".implode(' and ', $needs).'.', 403, ['required_permissions' => $needs]);
        }

        return null;
    }

    private function find(Request $request, string $id): ScheduledReport
    {
        return ScheduledReport::where('branch_id', $this->branchId($request))->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ScheduledReport $schedule): array
    {
        return $schedule->load('creator:id,name')->toArray() + ['report_title' => ReportCatalogue::find($schedule->report_key)['title'] ?? $schedule->report_key];
    }
}
