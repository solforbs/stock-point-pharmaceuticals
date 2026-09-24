<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\NumberSequence;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The central patient registry. One person, one record; every visit is an
 * encounter against it. Viewing a record is itself audited — patient data
 * is sensitive.
 */
class PatientController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'hospital.patient.view');

        return response()->json(
            Patient::query()
                ->when($request->input('q'), fn ($q, $term) => $q->search($term))
                ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
                ->orderBy('last_name')->orderBy('first_name')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $patient): JsonResponse
    {
        $this->requirePermission($request, 'hospital.patient.view');

        $record = Patient::with([
            'encounters' => fn ($q) => $q->with(['facility:id,name', 'department:id,name', 'attendingClinician:id,name'])->limit(50),
        ])->findOrFail($patient);

        AuditLog::record('PATIENT_VIEWED', 'patient', $record->id, [
            'user_id' => $request->user()->id,
            'reference' => $record->patient_no,
        ]);

        return response()->json($record);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'hospital.patient.manage');

        $data = $this->validatePatient($request);

        $patient = DB::transaction(fn () => Patient::create($data + [
            'patient_no' => NumberSequence::next($this->organisationId($request), 'PATIENT', null, 'PAT'),
            'created_by' => $request->user()->id,
        ]));

        AuditLog::record('PATIENT_REGISTERED', 'patient', $patient->id, [
            'user_id' => $request->user()->id,
            'reference' => $patient->patient_no,
            'after_json' => ['name' => $patient->fullName(), 'phone' => $patient->phone],
        ]);

        return response()->json($patient, 201);
    }

    public function update(Request $request, string $patient): JsonResponse
    {
        $this->requirePermission($request, 'hospital.patient.manage');

        $record = Patient::findOrFail($patient);
        $record->update($this->validatePatient($request, updating: true));

        AuditLog::record('PATIENT_UPDATED', 'patient', $record->id, [
            'user_id' => $request->user()->id,
            'reference' => $record->patient_no,
            'changed_fields' => array_keys($record->getChanges()),
        ]);

        return response()->json($record);
    }

    /** @return array<string, mixed> */
    private function validatePatient(Request $request, bool $updating = false): array
    {
        $sometimes = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'first_name' => [$sometimes, 'string', 'max:100'],
            'last_name' => [$sometimes, 'string', 'max:100'],
            'sex' => ['nullable', 'in:MALE,FEMALE,OTHER'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'next_of_kin_name' => ['nullable', 'string', 'max:150'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
