<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\ControlledDocument;
use App\Models\DocumentAcknowledgement;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Part 16.4 (V6) — SOPs and controlled documents. Versioned files; staff
 * acknowledge each version (recorded in the audit log) and an inspector can
 * print who acknowledged what. Every signed-in user may read, download and
 * acknowledge ACTIVE documents; document.manage is needed for everything else.
 */
class ControlledDocumentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:DRAFT,ACTIVE,RETIRED'],
            'category' => ['nullable', 'in:SOP,POLICY,FORM,WORK_INSTRUCTION,OTHER'],
            'q' => ['nullable', 'string', 'max:100'],
            'pending_only' => ['nullable', 'boolean'],
        ]);
        $canManage = $request->user()->can('document.manage');
        $userId = $request->user()->id;

        $page = ControlledDocument::where('organisation_id', $this->organisationId($request))
            ->when(! $canManage, fn ($q) => $q->where('status', 'ACTIVE'))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('code', 'like', "%{$v}%")->orWhere('title', 'like', "%{$v}%")))
            ->when($request->boolean('pending_only'), fn ($q) => $q->where('status', 'ACTIVE')->whereDoesntHave('currentVersion.acknowledgements', fn ($a) => $a->where('user_id', $userId)))
            ->with(['currentVersion' => fn ($q) => $q->withCount('acknowledgements'), 'owner:id,name'])
            ->orderBy('code')
            ->paginate($request->integer('per_page', 25));

        $mine = DocumentAcknowledgement::where('user_id', $userId)
            ->whereIn('document_version_id', collect($page->items())->pluck('current_version_id')->filter())
            ->pluck('acknowledged_at', 'document_version_id');

        $page->getCollection()->transform(fn (ControlledDocument $d) => $d->toArray() + [
            'my_acknowledged_at' => $d->current_version_id && isset($mine[$d->current_version_id]) ? $mine[$d->current_version_id]->toIso8601String() : null,
            'acknowledgement_count' => $d->currentVersion->acknowledgements_count ?? 0,
        ]);

        return response()->json($page);
    }

    public function show(Request $request, string $document): JsonResponse
    {
        $doc = $this->findVisible($request, $document);

        return response()->json($doc->load([
            'currentVersion', 'owner:id,name',
            'versions' => fn ($q) => $q->orderByDesc('created_at')->withCount('acknowledgements')->with('uploader:id,name'),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'document.manage');
        $orgId = $this->organisationId($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:controlled_documents,code,NULL,id,organisation_id,'.$orgId],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:SOP,POLICY,FORM,WORK_INSTRUCTION,OTHER'],
            'review_due_date' => ['nullable', 'date'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ] + $this->versionRules());

        $doc = DB::transaction(function () use ($data, $orgId, $request) {
            $doc = ControlledDocument::create([
                'organisation_id' => $orgId,
                'code' => strtoupper($data['code']),
                'title' => $data['title'],
                'category' => $data['category'],
                'review_due_date' => $data['review_due_date'] ?? null,
                'status' => 'DRAFT',
                'owner_user_id' => $data['owner_user_id'] ?? $request->user()->id,
                'created_by' => $request->user()->id,
            ]);
            $version = $this->addVersion($doc, $data, $request->file('file'), $request->user()->id);
            AuditLog::record('DOCUMENT_CREATED', 'controlled_document', $doc->id, [
                'reference' => $doc->code,
                'after_json' => ['title' => $doc->title, 'category' => $doc->category, 'version' => $version->version],
            ]);

            return $doc;
        });

        return response()->json($doc->fresh(['currentVersion', 'owner:id,name']), 201);
    }

    public function update(Request $request, string $document): JsonResponse
    {
        $this->requirePermission($request, 'document.manage');
        $doc = $this->findVisible($request, $document);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'in:SOP,POLICY,FORM,WORK_INSTRUCTION,OTHER'],
            'review_due_date' => ['sometimes', 'nullable', 'date'],
            'owner_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);
        $before = $doc->only(array_keys($data));
        $doc->update($data);
        AuditLog::record('DOCUMENT_UPDATED', 'controlled_document', $doc->id, [
            'reference' => $doc->code, 'before_json' => $before, 'after_json' => $doc->only(array_keys($data)),
        ]);

        return response()->json($doc->fresh(['currentVersion', 'owner:id,name']));
    }

    /** POST /api/documents/{id}/versions — the new file becomes current; acknowledgements start again. */
    public function storeVersion(Request $request, string $document): JsonResponse
    {
        $this->requirePermission($request, 'document.manage');
        $doc = $this->findVisible($request, $document);
        if ($doc->status === 'RETIRED') {
            return $this->error('INVALID_STATE', 'A retired document cannot take a new version; reactivate it first.', 409);
        }
        $data = $request->validate($this->versionRules());
        if ($doc->versions()->where('version', $data['version'])->exists()) {
            return $this->error('INVALID_INPUT', "Version {$data['version']} already exists for {$doc->code}.", 422, ['field' => 'version']);
        }

        $version = DB::transaction(function () use ($doc, $data, $request) {
            $previous = $doc->currentVersion?->version;
            $version = $this->addVersion($doc, $data, $request->file('file'), $request->user()->id);
            AuditLog::record('DOCUMENT_VERSION_ISSUED', 'controlled_document', $doc->id, [
                'reference' => $doc->code,
                'reason' => $data['change_summary'] ?? null,
                'before_json' => ['version' => $previous],
                'after_json' => ['version' => $version->version],
            ]);

            return $version;
        });

        return response()->json($version, 201);
    }

    public function activate(Request $request, string $document): JsonResponse
    {
        $this->requirePermission($request, 'document.manage');

        return $this->transition($request, $this->findVisible($request, $document), ['DRAFT', 'RETIRED'], 'ACTIVE', 'DOCUMENT_ACTIVATED');
    }

    public function retire(Request $request, string $document): JsonResponse
    {
        $this->requirePermission($request, 'document.manage');

        return $this->transition($request, $this->findVisible($request, $document), ['ACTIVE'], 'RETIRED', 'DOCUMENT_RETIRED');
    }

    public function download(Request $request, string $document, string $version): StreamedResponse|JsonResponse
    {
        $doc = $this->findVisible($request, $document);
        $file = $doc->versions()->findOrFail($version);
        if (! Storage::disk('local')->exists($file->file_path)) {
            return $this->error('NOT_FOUND', 'The file for this version is missing from storage.', 404);
        }

        return Storage::disk('local')->download($file->file_path, $file->file_name);
    }

    /** POST /api/documents/{id}/acknowledge — "read and understood" for the current version. */
    public function acknowledge(Request $request, string $document): JsonResponse
    {
        $doc = $this->findVisible($request, $document);
        if ($doc->status !== 'ACTIVE' || ! $doc->current_version_id) {
            return $this->error('INVALID_STATE', 'Only an ACTIVE document can be acknowledged.', 409);
        }
        $userId = $request->user()->id;

        $ack = DocumentAcknowledgement::where('document_version_id', $doc->current_version_id)->where('user_id', $userId)->first();
        if ($ack) {
            return response()->json($ack);
        }

        $ack = DocumentAcknowledgement::create([
            'document_version_id' => $doc->current_version_id,
            'user_id' => $userId,
            'acknowledged_at' => now(),
        ]);
        AuditLog::record('DOCUMENT_ACKNOWLEDGED', 'controlled_document', $doc->id, [
            'reference' => $doc->code,
            'after_json' => ['version_id' => $doc->current_version_id, 'version' => $doc->currentVersion?->version],
        ]);

        return response()->json($ack, 201);
    }

    /**
     * GET /api/documents/{id}/acknowledgements — who has and has not
     * acknowledged a version (current by default), for the inspector.
     */
    public function acknowledgements(Request $request, string $document): JsonResponse
    {
        $user = $request->user();
        if (! $user->can('document.manage') && ! $user->can('audit.view')) {
            $this->requirePermission($request, 'document.manage');
        }
        $doc = $this->findVisible($request, $document);
        $versionId = $request->input('version_id', $doc->current_version_id);
        $version = $versionId ? $doc->versions()->findOrFail($versionId) : null;

        $acks = $version
            ? $version->acknowledgements()->with('user:id,name,username')->orderBy('acknowledged_at')->get()
            : collect();
        $pending = User::where('is_active', true)->whereNotIn('id', $acks->pluck('user_id'))->orderBy('name')->get(['id', 'name', 'username']);

        return response()->json([
            'version' => $version,
            'acknowledged' => $acks->values(),
            'pending' => $pending,
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function versionRules(): array
    {
        return [
            'version' => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z.\-]+$/'],
            'change_summary' => ['nullable', 'string', 'max:2000'],
            'effective_date' => ['required', 'date'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function addVersion(ControlledDocument $doc, array $data, UploadedFile $file, int $userId): DocumentVersion
    {
        $version = DocumentVersion::create([
            'controlled_document_id' => $doc->id,
            'version' => $data['version'],
            'file_path' => (string) $file->store('documents/'.$doc->id, 'local'),
            'file_name' => $file->getClientOriginalName(),
            'change_summary' => $data['change_summary'] ?? null,
            'effective_date' => $data['effective_date'],
            'uploaded_by' => $userId,
        ]);
        $doc->update(['current_version_id' => $version->id]);

        return $version;
    }

    /**
     * @param  list<string>  $from
     */
    private function transition(Request $request, ControlledDocument $doc, array $from, string $to, string $action): JsonResponse
    {
        if (! in_array($doc->status, $from, true)) {
            return $this->error('INVALID_STATE', "A {$doc->status} document cannot become {$to}.", 409);
        }
        if ($to === 'ACTIVE' && ! $doc->current_version_id) {
            return $this->error('INVALID_STATE', 'Upload a version before activating the document.', 409);
        }
        $before = $doc->status;
        $doc->update(['status' => $to]);
        AuditLog::record($action, 'controlled_document', $doc->id, [
            'reference' => $doc->code,
            'user_id' => $request->user()->id,
            'before_json' => ['status' => $before],
            'after_json' => ['status' => $to],
        ]);

        return response()->json($doc->fresh(['currentVersion', 'owner:id,name']));
    }

    /** Non-managers only ever see ACTIVE documents. */
    private function findVisible(Request $request, string $id): ControlledDocument
    {
        return ControlledDocument::where('organisation_id', $this->organisationId($request))
            ->when(! $request->user()->can('document.manage'), fn ($q) => $q->where('status', 'ACTIVE'))
            ->findOrFail($id);
    }
}
