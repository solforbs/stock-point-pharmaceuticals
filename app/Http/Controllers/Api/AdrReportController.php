<?php

namespace App\Http\Controllers\Api;

use App\Models\AdrReport;
use App\Models\AuditLog;
use App\Models\NumberSequence;
use App\Models\ProductBatch;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Part 11.4 — pharmacovigilance / adverse drug reactions. DRAFT → SUBMITTED
 * (reported to PPB PViMS, reference recorded when known) → CLOSED.
 */
class AdrReportController extends ApiController
{
    /** Three non-draft reports against one batch is a signal (Part 11.4). */
    private const BATCH_SIGNAL_THRESHOLD = 3;

    public function index(Request $request): JsonResponse
    {
        $this->requireAnyAdrPermission($request);
        $filters = $request->validate([
            'status' => ['nullable', 'in:DRAFT,SUBMITTED,CLOSED'],
            'product_id' => ['nullable', 'uuid'],
            'seriousness' => ['nullable', 'in:NON_SERIOUS,SERIOUS,LIFE_THREATENING,FATAL'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(
            AdrReport::where('branch_id', $this->branchId($request))
                ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
                ->when($filters['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
                ->when($filters['seriousness'] ?? null, fn ($q, $v) => $q->where('seriousness', $v))
                ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('doc_number', 'like', "%{$v}%")->orWhere('ppb_reference', 'like', "%{$v}%")->orWhere('patient_initials', 'like', "%{$v}%")))
                ->with(['product:id,code,name', 'batch:id,batch_number,expiry_date,status'])
                ->orderByDesc('created_at')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $report): JsonResponse
    {
        $this->requireAnyAdrPermission($request);

        return response()->json($this->withDetail($this->findReport($request, $report)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'adr.report');
        $data = $request->validate($this->rules());
        $this->assertBatchBelongsToProduct($data);

        $branchId = $this->branchId($request);
        $orgId = $this->organisationId($request);

        $report = DB::transaction(function () use ($data, $branchId, $orgId, $request) {
            $report = AdrReport::create($data + [
                'doc_number' => NumberSequence::next($orgId, 'ADR', $branchId, 'ADR'),
                'organisation_id' => $orgId,
                'branch_id' => $branchId,
                'status' => 'DRAFT',
                'created_by' => $request->user()->id,
            ]);
            AuditLog::record('ADR_REPORT_CREATED', 'adr_report', $report->id, [
                'reference' => $report->doc_number,
                'after_json' => ['seriousness' => $report->seriousness, 'product_id' => $report->product_id, 'batch_id' => $report->batch_id],
            ]);

            return $report;
        });

        return response()->json($this->withDetail($report), 201);
    }

    /**
     * PATCH /api/adr-reports/{id} — full edit while DRAFT (adr.report); once
     * SUBMITTED only the PPB reference and investigation notes (adr.manage).
     */
    public function update(Request $request, string $report): JsonResponse
    {
        $adr = $this->findReport($request, $report);

        if ($adr->status === 'CLOSED') {
            return $this->error('INVALID_STATE', 'A closed ADR report cannot be edited.', 409);
        }

        if ($adr->status === 'SUBMITTED') {
            $this->requirePermission($request, 'adr.manage');
            $data = $request->validate([
                'ppb_reference' => ['sometimes', 'nullable', 'string', 'max:100'],
                'investigation_notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
            ]);
            $extra = array_keys($request->except(['ppb_reference', 'investigation_notes']));
            if ($extra !== []) {
                return $this->error('INVALID_STATE', 'A submitted report only accepts the PPB reference and investigation notes.', 409, ['fields' => $extra]);
            }
        } else {
            $this->requirePermission($request, 'adr.report');
            $data = $request->validate(array_map(fn (array $rule) => array_merge(['sometimes'], $rule), $this->rules()));
            $this->assertBatchBelongsToProduct($data + ['product_id' => $adr->product_id]);
        }

        $before = $adr->only(array_keys($data));
        $adr->update($data);
        AuditLog::record('ADR_REPORT_UPDATED', 'adr_report', $adr->id, [
            'reference' => $adr->doc_number,
            'before_json' => $before,
            'after_json' => $adr->only(array_keys($data)),
            'changed_fields' => array_keys($data),
        ]);

        return response()->json($this->withDetail($adr));
    }

    public function submit(Request $request, string $report): JsonResponse
    {
        $this->requirePermission($request, 'adr.report');
        $adr = $this->findReport($request, $report);
        if ($adr->status !== 'DRAFT') {
            return $this->error('INVALID_STATE', "Only a DRAFT report can be submitted (it is {$adr->status}).", 409);
        }
        $data = $request->validate(['ppb_reference' => ['nullable', 'string', 'max:100']]);

        DB::transaction(function () use ($adr, $data, $request) {
            $adr->update([
                'status' => 'SUBMITTED',
                'submitted_at' => now(),
                'submitted_by' => $request->user()->id,
                'ppb_reference' => $data['ppb_reference'] ?? $adr->ppb_reference,
            ]);
            AuditLog::record('ADR_REPORT_SUBMITTED', 'adr_report', $adr->id, [
                'reference' => $adr->doc_number,
                'before_json' => ['status' => 'DRAFT'],
                'after_json' => ['status' => 'SUBMITTED', 'ppb_reference' => $adr->ppb_reference],
            ]);

            if ($adr->isSerious()) {
                AuditLog::record('ADR_SERIOUS_REPORTED', 'adr_report', $adr->id, [
                    'reference' => $adr->doc_number,
                    'reason' => "{$adr->seriousness} adverse reaction reported — Director attention required.",
                    'after_json' => ['attention' => 'DIRECTOR', 'seriousness' => $adr->seriousness, 'outcome' => $adr->outcome, 'product_id' => $adr->product_id, 'batch_id' => $adr->batch_id],
                ]);
            }

            if ($adr->batch_id) {
                $count = AdrReport::where('batch_id', $adr->batch_id)->where('status', '!=', 'DRAFT')->count();
                if ($count === self::BATCH_SIGNAL_THRESHOLD) {
                    AuditLog::record('ADR_BATCH_SIGNAL', 'product_batch', $adr->batch_id, [
                        'reference' => ProductBatch::whereKey($adr->batch_id)->value('batch_number'),
                        'reason' => "{$count} adverse reaction reports against one batch — investigate for a recall.",
                        'after_json' => ['attention' => 'DIRECTOR', 'report_count' => $count],
                    ]);
                }
            }
        });

        return response()->json($this->withDetail($adr));
    }

    public function close(Request $request, string $report): JsonResponse
    {
        $this->requirePermission($request, 'adr.manage');
        $adr = $this->findReport($request, $report);
        if ($adr->status !== 'SUBMITTED') {
            return $this->error('INVALID_STATE', "Only a SUBMITTED report can be closed (it is {$adr->status}).", 409);
        }
        $data = $request->validate([
            'ppb_reference' => ['nullable', 'string', 'max:100'],
            'investigation_notes' => ['nullable', 'string', 'max:4000'],
        ]);
        $ppbReference = $data['ppb_reference'] ?? $adr->ppb_reference;
        if ($adr->isSerious() && ! $ppbReference) {
            return $this->error('PPB_REFERENCE_REQUIRED', 'A serious ADR report cannot be closed until its PPB PViMS reference is recorded.', 422);
        }

        $adr->update([
            'status' => 'CLOSED',
            'closed_at' => now(),
            'closed_by' => $request->user()->id,
            'ppb_reference' => $ppbReference,
            'investigation_notes' => $data['investigation_notes'] ?? $adr->investigation_notes,
        ]);
        AuditLog::record('ADR_REPORT_CLOSED', 'adr_report', $adr->id, [
            'reference' => $adr->doc_number,
            'before_json' => ['status' => 'SUBMITTED'],
            'after_json' => ['status' => 'CLOSED'],
        ]);

        return response()->json($this->withDetail($adr));
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid', TenantRules::exists('products')],
            'batch_id' => ['nullable', 'uuid', TenantRules::exists('product_batches')],
            'customer_id' => ['nullable', 'uuid', TenantRules::exists('customers')],
            'patient_initials' => ['required', 'string', 'max:10'],
            'patient_age' => ['nullable', 'integer', 'between:0,130'],
            'patient_sex' => ['nullable', 'in:M,F,U'],
            'reaction_description' => ['required', 'string', 'min:5', 'max:4000'],
            'onset_date' => ['required', 'date', 'before_or_equal:today'],
            'seriousness' => ['required', 'in:NON_SERIOUS,SERIOUS,LIFE_THREATENING,FATAL'],
            'outcome' => ['required', 'in:RECOVERED,RECOVERING,NOT_RECOVERED,UNKNOWN,FATAL'],
            'action_taken' => ['nullable', 'string', 'max:2000'],
            'reporter_name' => ['required', 'string', 'max:150'],
            'investigation_notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertBatchBelongsToProduct(array $data): void
    {
        if (empty($data['batch_id'])) {
            return;
        }
        if (! ProductBatch::whereKey($data['batch_id'])->where('product_id', $data['product_id'])->exists()) {
            throw new \InvalidArgumentException('The batch does not belong to the reported product.');
        }
    }

    private function requireAnyAdrPermission(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! ($user->can('adr.report') || $user->can('adr.manage'))) {
            $this->requirePermission($request, 'adr.report');
        }
    }

    private function withDetail(AdrReport $report): AdrReport
    {
        return $report->fresh([
            'product:id,code,name', 'batch:id,batch_number,expiry_date,status', 'customer:id,code,name',
            'creator:id,name', 'submitter:id,name', 'closer:id,name',
        ]) ?? $report;
    }

    private function findReport(Request $request, string $id): AdrReport
    {
        return AdrReport::where('branch_id', $this->branchId($request))->findOrFail($id);
    }
}
