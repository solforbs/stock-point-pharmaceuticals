<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Licence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A customer's legal documents — certificate of incorporation, Pharmacy &
 * Poisons Board licence, county permit and the like — with their numbers,
 * dates and the scanned copy. They are CUSTOMER licences in the licence
 * register, so expiries also show in Quality & Compliance and raise the
 * usual expiry alerts. Annual documents are renewed by adding the new year's
 * copy; old ones are archived, never deleted.
 */
class CustomerDocumentController extends ApiController
{
    /** GET /api/customers/{customer}/documents — newest first, archived ones on request. */
    public function index(Request $request, string $customer): JsonResponse
    {
        $this->requirePermission($request, 'customer.manage');
        $model = $this->customer($request, $customer);

        return response()->json(
            Licence::where('organisation_id', $model->organisation_id)
                ->where('holder_type', 'CUSTOMER')->where('holder_id', $model->id)
                ->when(! $request->boolean('include_archived'), fn ($q) => $q->where('is_active', true))
                ->orderBy('licence_type')->orderByDesc('issue_date')->orderByDesc('created_at')
                ->get()
        );
    }

    /** POST /api/customers/{customer}/documents (multipart, with the scanned copy). */
    public function store(Request $request, string $customer): JsonResponse
    {
        $this->requirePermission($request, 'customer.manage');
        $model = $this->customer($request, $customer);
        $data = $request->validate($this->rules());
        $this->assertDatesInOrder($data['issue_date'] ?? null, $data['expiry_date'] ?? null);

        $document = new Licence(collect($data)->except('document')->all() + [
            'organisation_id' => $model->organisation_id,
            'holder_type' => 'CUSTOMER',
            'holder_id' => $model->id,
            'is_active' => true,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        if ($request->file('document') instanceof UploadedFile) {
            $this->attach($document, $request->file('document'));
        }
        $document->save();

        AuditLog::record('CUSTOMER_DOCUMENT_ADDED', 'customer', $model->id, [
            'reference' => $model->code,
            'after_json' => $document->only(['licence_type', 'licence_number', 'issue_date', 'expiry_date']),
        ]);

        return response()->json($document->fresh(), 201);
    }

    /**
     * POST /api/customers/{customer}/documents/{document} — correct the
     * details, replace the scan, or archive it (is_active = false).
     */
    public function update(Request $request, string $customer, string $document): JsonResponse
    {
        $this->requirePermission($request, 'customer.manage');
        $model = $this->customer($request, $customer);
        $licence = $this->document($model, $document);
        $data = $request->validate(array_map(fn (array $rule) => array_merge(['sometimes'], $rule), $this->rules()) + [
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $this->assertDatesInOrder(
            $data['issue_date'] ?? $licence->issue_date?->toDateString(),
            array_key_exists('expiry_date', $data) ? $data['expiry_date'] : $licence->expiry_date?->toDateString(),
        );

        $fields = collect($data)->except('document')->all();
        $before = $licence->only(array_keys($fields));
        $licence->fill($fields + ['updated_by' => $request->user()->id]);
        if ($request->file('document') instanceof UploadedFile) {
            $this->attach($licence, $request->file('document'));
        }
        $licence->save();

        AuditLog::record('CUSTOMER_DOCUMENT_UPDATED', 'customer', $model->id, [
            'reference' => $model->code,
            'before_json' => $before,
            'after_json' => $licence->only(array_keys($fields)),
            'changed_fields' => array_keys($data),
        ]);

        return response()->json($licence->fresh());
    }

    /** GET /api/customers/{customer}/documents/{document}/file — the scanned copy. */
    public function file(Request $request, string $customer, string $document): StreamedResponse|JsonResponse
    {
        $this->requirePermission($request, 'customer.manage');
        $licence = $this->document($this->customer($request, $customer), $document);
        if (! $licence->document_path || ! Storage::disk('local')->exists($licence->document_path)) {
            return $this->error('NOT_FOUND', 'No file is attached to this document.', 404);
        }

        return Storage::disk('local')->download($licence->document_path, $licence->document_name ?? basename($licence->document_path));
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        return [
            'licence_type' => ['required', 'in:'.LicenceController::LICENCE_TYPES],
            'licence_number' => ['required', 'string', 'max:100'],
            'issued_by' => ['nullable', 'string', 'max:150'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'required_unless:licence_type,'.LicenceController::NON_EXPIRING_TYPES, 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    private function customer(Request $request, string $id): Customer
    {
        return Customer::where('organisation_id', $this->organisationId($request))->findOrFail($id);
    }

    private function document(Customer $customer, string $id): Licence
    {
        return Licence::where('organisation_id', $customer->organisation_id)
            ->where('holder_type', 'CUSTOMER')->where('holder_id', $customer->id)
            ->findOrFail($id);
    }

    private function attach(Licence $licence, UploadedFile $file): void
    {
        $licence->document_path = $file->store('licences/customers', 'local') ?: null;
        $licence->document_name = $file->getClientOriginalName();
    }

    private function assertDatesInOrder(?string $issueDate, ?string $expiryDate): void
    {
        if ($issueDate && $expiryDate && strtotime($expiryDate) < strtotime($issueDate)) {
            throw ValidationException::withMessages(['expiry_date' => 'The expiry date cannot be before the issue date.']);
        }
    }
}
