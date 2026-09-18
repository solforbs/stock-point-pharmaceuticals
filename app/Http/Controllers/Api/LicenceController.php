<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Part 16.3 (V6) — licence and certificate register. Supplier licences held
 * on the supplier master are listed alongside as read-only rows, so one
 * screen answers "what expires next". Licences are archived, never deleted.
 */
class LicenceController extends ApiController
{
    private const LICENCE_TYPES = 'PPB_PREMISES,PPB_PHARMACIST,PPB_PHARMTECH,BUSINESS_PERMIT,FIRE,PUBLIC_HEALTH,KRA_TCC,NHIF_SHIF,OTHER';

    public function index(Request $request): JsonResponse
    {
        $this->requireViewPermission($request);
        $filters = $request->validate([
            'holder_type' => ['nullable', 'in:ORGANISATION,BRANCH,EMPLOYEE,SUPPLIER'],
            'status' => ['nullable', 'in:VALID,EXPIRING,EXPIRED'],
            'licence_type' => ['nullable', 'in:'.self::LICENCE_TYPES],
            'q' => ['nullable', 'string', 'max:100'],
            'include_archived' => ['nullable', 'boolean'],
        ]);
        $orgId = $this->organisationId($request);

        $licences = Licence::where('organisation_id', $orgId)
            ->when(! $request->boolean('include_archived'), fn ($q) => $q->where('is_active', true))
            ->get();
        $names = $this->holderNames($licences, $orgId);

        $rows = $licences->map(fn (Licence $l) => $l->toArray() + [
            'holder_name' => $names[$l->holder_type.':'.$l->holder_id] ?? null,
            'source' => 'licence',
            'read_only' => false,
        ]);

        $supplierRows = Supplier::where('organisation_id', $orgId)->where('is_active', true)
            ->where(fn ($q) => $q->whereNotNull('licence_number')->orWhereNotNull('licence_expiry'))
            ->get()
            ->map(fn (Supplier $s) => [
                'id' => 'supplier:'.$s->id,
                'organisation_id' => $orgId,
                'holder_type' => 'SUPPLIER',
                'holder_id' => $s->id,
                'holder_name' => $s->name,
                'licence_type' => 'PPB_PREMISES',
                'licence_number' => $s->licence_number,
                'issued_by' => 'Pharmacy and Poisons Board',
                'issue_date' => null,
                'expiry_date' => $s->licence_expiry?->toDateString(),
                'notes' => null,
                'is_active' => true,
                'status' => $s->licence_expiry ? Licence::statusFor($s->licence_expiry) : 'EXPIRED',
                'days_to_expiry' => $s->licence_expiry ? (int) now()->startOfDay()->diffInDays($s->licence_expiry->copy()->startOfDay(), false) : null,
                'has_document' => false,
                'source' => 'supplier',
                'read_only' => true,
            ]);

        $holderType = $filters['holder_type'] ?? null;
        $status = $filters['status'] ?? null;
        $licenceType = $filters['licence_type'] ?? null;
        $term = mb_strtolower((string) ($filters['q'] ?? ''));

        $all = $rows->concat($supplierRows)
            ->filter(fn (array $r) => (! $holderType || $r['holder_type'] === $holderType)
                && (! $status || $r['status'] === $status)
                && (! $licenceType || $r['licence_type'] === $licenceType)
                && ($term === '' || str_contains(mb_strtolower(implode(' ', [$r['licence_number'], $r['holder_name'], $r['issued_by']])), $term)))
            ->sortBy(fn (array $r) => $r['expiry_date'] ?? '0000-00-00')
            ->values();

        return response()->json([
            'data' => $all,
            'summary' => [
                'valid' => $all->where('status', 'VALID')->count(),
                'expiring' => $all->where('status', 'EXPIRING')->count(),
                'expired' => $all->where('status', 'EXPIRED')->count(),
            ],
        ]);
    }

    /**
     * GET /api/licences/holders?holder_type= — id and name of every possible
     * holder of that type in this organisation, for the licence form.
     */
    public function holders(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'licence.manage');
        $data = $request->validate(['holder_type' => ['required', 'in:BRANCH,EMPLOYEE,SUPPLIER']]);
        $orgId = $this->organisationId($request);

        $query = match ($data['holder_type']) {
            'BRANCH' => Branch::where('organisation_id', $orgId),
            'EMPLOYEE' => Employee::where('organisation_id', $orgId)->where('is_active', true),
            default => Supplier::where('organisation_id', $orgId)->where('is_active', true),
        };

        return response()->json($query->orderBy('name')->get(['id', 'name']));
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'licence.manage');
        $data = $request->validate($this->rules());
        $orgId = $this->organisationId($request);
        $data['holder_id'] = $this->resolveHolder($data['holder_type'], $data['holder_id'] ?? null, $orgId);
        $this->assertDatesInOrder($data['issue_date'] ?? null, $data['expiry_date']);

        $licence = new Licence(collect($data)->except('document')->all() + [
            'organisation_id' => $orgId,
            'is_active' => true,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        if ($request->file('document') instanceof UploadedFile) {
            $this->attachDocument($licence, $request->file('document'));
        }
        $licence->save();

        AuditLog::record('LICENCE_CREATED', 'licence', $licence->id, [
            'reference' => $licence->licence_number,
            'after_json' => $licence->only(['holder_type', 'holder_id', 'licence_type', 'licence_number', 'expiry_date']),
        ]);

        return response()->json($licence->fresh(), 201);
    }

    /**
     * PATCH (JSON) or POST (multipart, to replace the document) — including
     * `is_active=false` to archive; there is no delete.
     */
    public function update(Request $request, string $licence): JsonResponse
    {
        $this->requirePermission($request, 'licence.manage');
        $model = Licence::where('organisation_id', $this->organisationId($request))->findOrFail($licence);
        $data = $request->validate(array_map(fn (array $rule) => array_merge(['sometimes'], $rule), $this->rules()) + [
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (array_key_exists('holder_type', $data) || array_key_exists('holder_id', $data)) {
            $data['holder_id'] = $this->resolveHolder($data['holder_type'] ?? $model->holder_type, $data['holder_id'] ?? $model->holder_id, $model->organisation_id);
        }

        $this->assertDatesInOrder($data['issue_date'] ?? $model->issue_date?->toDateString(), $data['expiry_date'] ?? $model->expiry_date->toDateString());
        $fields = collect($data)->except('document')->all();
        $before = $model->only(array_keys($fields));
        $model->fill($fields + ['updated_by' => $request->user()->id]);
        if ($request->file('document') instanceof UploadedFile) {
            $this->attachDocument($model, $request->file('document'));
        }
        $model->save();

        $archived = array_key_exists('is_active', $data) && ! $data['is_active'] && ($before['is_active'] ?? true);
        AuditLog::record($archived ? 'LICENCE_ARCHIVED' : 'LICENCE_UPDATED', 'licence', $model->id, [
            'reference' => $model->licence_number,
            'before_json' => $before,
            'after_json' => $model->only(array_keys($fields)),
            'changed_fields' => array_keys($data),
        ]);

        return response()->json($model->fresh());
    }

    public function document(Request $request, string $licence): StreamedResponse|JsonResponse
    {
        $this->requireViewPermission($request);
        $model = Licence::where('organisation_id', $this->organisationId($request))->findOrFail($licence);
        if (! $model->document_path || ! Storage::disk('local')->exists($model->document_path)) {
            return $this->error('NOT_FOUND', 'No document is attached to this licence.', 404);
        }

        return Storage::disk('local')->download($model->document_path, $model->document_name ?? basename($model->document_path));
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        return [
            'holder_type' => ['required', 'in:ORGANISATION,BRANCH,EMPLOYEE,SUPPLIER'],
            'holder_id' => ['nullable', 'string', 'max:64'],
            'licence_type' => ['required', 'in:'.self::LICENCE_TYPES],
            'licence_number' => ['required', 'string', 'max:100'],
            'issued_by' => ['nullable', 'string', 'max:150'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /** The holder must exist in this organisation; the organisation itself needs no id. */
    private function resolveHolder(string $type, ?string $holderId, string $orgId): string
    {
        if ($type === 'ORGANISATION') {
            return $orgId;
        }
        if (! $holderId) {
            throw new \InvalidArgumentException("A {$type} licence needs the holder selected.");
        }
        $exists = match ($type) {
            'BRANCH' => Branch::where('organisation_id', $orgId)->whereKey($holderId)->exists(),
            'EMPLOYEE' => Employee::where('organisation_id', $orgId)->whereKey($holderId)->exists(),
            default => Supplier::where('organisation_id', $orgId)->whereKey($holderId)->exists(),
        };
        if (! $exists) {
            throw new \InvalidArgumentException("The selected {$type} holder was not found.");
        }

        return $holderId;
    }

    private function assertDatesInOrder(?string $issueDate, string $expiryDate): void
    {
        if ($issueDate && strtotime($expiryDate) < strtotime($issueDate)) {
            throw ValidationException::withMessages(['expiry_date' => 'The expiry date cannot be before the issue date.']);
        }
    }

    private function attachDocument(Licence $licence, UploadedFile $file): void
    {
        $licence->document_path = $file->store('licences', 'local') ?: null;
        $licence->document_name = $file->getClientOriginalName();
    }

    /**
     * @param  Collection<int, Licence>  $licences
     * @return array<string, string>
     */
    private function holderNames(Collection $licences, string $orgId): array
    {
        $names = ['ORGANISATION:'.$orgId => (string) Organisation::whereKey($orgId)->value('name')];
        $ids = fn (string $type) => $licences->where('holder_type', $type)->pluck('holder_id')->filter()->unique()->all();
        foreach (Branch::whereIn('id', $ids('BRANCH'))->get(['id', 'name']) as $b) {
            $names['BRANCH:'.$b->id] = $b->name;
        }
        foreach (Employee::whereIn('id', $ids('EMPLOYEE'))->get(['id', 'name']) as $e) {
            $names['EMPLOYEE:'.$e->id] = $e->name;
        }
        foreach (Supplier::whereIn('id', $ids('SUPPLIER'))->get(['id', 'name']) as $s) {
            $names['SUPPLIER:'.$s->id] = $s->name;
        }

        return $names;
    }

    private function requireViewPermission(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! ($user->can('licence.view') || $user->can('licence.manage'))) {
            $this->requirePermission($request, 'licence.view');
        }
    }
}
