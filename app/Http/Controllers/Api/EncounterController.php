<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\Encounter;
use App\Models\EncounterCharge;
use App\Models\LabTest;
use App\Services\Hospital\EncounterService;
use App\Services\Hospital\PrescriptionService;
use App\Services\Laboratory\LabOrderService;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The visit workflow: reception opens the encounter and charges the
 * consultation fee; the clinician works the queue, records the
 * consultation, diagnoses, refers, orders tests and prescribes; the
 * flexible paths (consultation only, → lab, → pharmacy, → referral) all
 * live here as optional attachments to one encounter.
 */
class EncounterController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requireAnyPermission($request, ['hospital.encounter.manage', 'hospital.consultation.manage', 'hospital.patient.view']);

        return response()->json(
            Encounter::query()
                ->when($request->input('status'), fn ($q, $v) => $v === 'OPEN' ? $q->whereIn('status', Encounter::OPEN_STATUSES) : $q->where('status', $v))
                ->when($request->input('facility_id'), fn ($q, $v) => $q->where('facility_id', $v))
                ->when($request->input('patient_id'), fn ($q, $v) => $q->where('patient_id', $v))
                ->with(['patient:id,patient_no,first_name,last_name,sex,date_of_birth', 'facility:id,name', 'department:id,name', 'attendingClinician:id,name'])
                ->orderByDesc('started_at')->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $encounter): JsonResponse
    {
        $this->requireAnyPermission($request, ['hospital.encounter.manage', 'hospital.consultation.manage', 'hospital.patient.view']);

        return response()->json(Encounter::with([
            'patient', 'facility:id,name,pharmacy_branch_id,offers_pharmacy', 'department:id,name', 'attendingClinician:id,name',
            'consultations.clinician:id,name', 'diagnoses.diagnosedBy:id,name', 'referrals.referredBy:id,name',
            'charges', 'labOrders.tests', 'labOrders.orderedBy:id,name',
            'prescriptions.lines', 'prescriptions.prescribedBy:id,name',
        ])->findOrFail($encounter));
    }

    public function store(Request $request, EncounterService $encounters): JsonResponse
    {
        $this->requirePermission($request, 'hospital.encounter.manage');

        $data = $request->validate([
            'patient_id' => ['required', 'uuid', TenantRules::exists('patients')],
            'facility_id' => ['required', 'uuid', TenantRules::exists('facilities')],
            'department_id' => ['nullable', 'uuid', TenantRules::exists('departments')],
            'encounter_type' => ['sometimes', 'in:OUTPATIENT,INPATIENT,EMERGENCY,FOLLOW_UP'],
            'consultation_fee' => ['nullable', 'numeric', 'min:0'],
            'presenting_notes' => ['nullable', 'string'],
        ]);

        return response()->json(
            $encounters->create($data, $request->user())->load(['patient:id,patient_no,first_name,last_name', 'charges']),
            201
        );
    }

    public function startConsultation(Request $request, string $encounter, EncounterService $encounters): JsonResponse
    {
        $this->requirePermission($request, 'hospital.consultation.manage');

        return response()->json($encounters->startConsultation($this->find($encounter), $request->user()));
    }

    public function complete(Request $request, string $encounter, EncounterService $encounters): JsonResponse
    {
        $this->requirePermission($request, 'hospital.consultation.manage');

        return response()->json($encounters->complete($this->find($encounter), $request->user()));
    }

    public function cancel(Request $request, string $encounter, EncounterService $encounters): JsonResponse
    {
        $this->requirePermission($request, 'hospital.encounter.manage');

        return response()->json($encounters->cancel(
            $this->find($encounter), $request->user(), $request->input('reason'),
        ));
    }

    /** The clinician's consultation record (created once, then edited). */
    public function saveConsultation(Request $request, string $encounter): JsonResponse
    {
        $this->requirePermission($request, 'hospital.consultation.manage');

        $record = $this->find($encounter);
        $data = $request->validate([
            'id' => ['nullable', 'uuid'],
            'chief_complaint' => ['nullable', 'string'],
            'history' => ['nullable', 'string'],
            'symptoms' => ['nullable', 'string'],
            'vital_signs' => ['nullable', 'array'],
            'examination' => ['nullable', 'string'],
            'clinical_notes' => ['nullable', 'string'],
            'treatment_plan' => ['nullable', 'string'],
            'follow_up' => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date'],
        ]);

        $consultation = isset($data['id'])
            ? Consultation::where('encounter_id', $record->id)->findOrFail($data['id'])
            : new Consultation(['encounter_id' => $record->id, 'clinician_id' => $request->user()->id]);
        $consultation->fill(collect($data)->except('id')->all())->save();

        AuditLog::record('CONSULTATION_SAVED', 'consultation', $consultation->id, [
            'user_id' => $request->user()->id,
            'reference' => $record->encounter_no,
        ]);

        return response()->json($consultation, isset($data['id']) ? 200 : 201);
    }

    public function addDiagnosis(Request $request, string $encounter): JsonResponse
    {
        $this->requirePermission($request, 'hospital.diagnosis.manage');

        $record = $this->find($encounter);
        $data = $request->validate([
            'diagnosis' => ['required', 'string', 'max:500'],
            'icd_code' => ['nullable', 'string', 'max:20'],
            'diagnosis_type' => ['sometimes', 'in:PROVISIONAL,FINAL'],
        ]);

        $diagnosis = $record->diagnoses()->create($data + ['diagnosed_by' => $request->user()->id]);

        AuditLog::record('DIAGNOSIS_RECORDED', 'diagnosis', $diagnosis->id, [
            'user_id' => $request->user()->id,
            'reference' => $record->encounter_no,
            'after_json' => ['diagnosis' => $diagnosis->diagnosis],
        ]);

        return response()->json($diagnosis, 201);
    }

    public function addReferral(Request $request, string $encounter): JsonResponse
    {
        $this->requirePermission($request, 'hospital.referral.manage');

        $record = $this->find($encounter);
        $data = $request->validate([
            'referred_to' => ['required', 'string', 'max:300'],
            'reason' => ['required', 'string'],
        ]);

        $referral = $record->referrals()->create($data + ['referred_by' => $request->user()->id]);

        AuditLog::record('REFERRAL_CREATED', 'referral', $referral->id, [
            'user_id' => $request->user()->id,
            'reference' => $record->encounter_no,
            'after_json' => ['referred_to' => $referral->referred_to],
        ]);

        return response()->json($referral, 201);
    }

    public function settleCharge(Request $request, string $encounter, string $charge, EncounterService $encounters): JsonResponse
    {
        $this->requirePermission($request, 'hospital.encounter.manage');

        $record = EncounterCharge::where('encounter_id', $this->find($encounter)->id)->findOrFail($charge);

        return response()->json($encounters->settleCharge($record, $request->user(), $request->boolean('waive')));
    }

    /** The orderable test catalogue, for the clinician's lab-order form. */
    public function labTests(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'laboratory.order.create');

        return response()->json(
            LabTest::where('is_active', true)->with('category:id,name')->orderBy('name')->get()
        );
    }

    public function orderLabTests(Request $request, string $encounter, LabOrderService $labOrders): JsonResponse
    {
        $this->requirePermission($request, 'laboratory.order.create');

        $record = $this->find($encounter);
        $data = $request->validate([
            'facility_id' => ['required', 'uuid', TenantRules::exists('facilities')],
            'test_ids' => ['required', 'array', 'min:1'],
            'test_ids.*' => ['uuid', TenantRules::exists('lab_tests')],
            'clinical_notes' => ['nullable', 'string'],
        ]);

        return response()->json(
            $labOrders->create($record, $data['facility_id'], $data['test_ids'], $request->user(), $data['clinical_notes'] ?? null)
                ->load('tests'),
            201
        );
    }

    public function prescribe(Request $request, string $encounter, PrescriptionService $prescriptions): JsonResponse
    {
        $this->requirePermission($request, 'hospital.prescription.create');

        $record = $this->find($encounter);
        $data = $request->validate([
            'branch_id' => ['nullable', 'uuid', TenantRules::exists('branches')],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'uuid', TenantRules::exists('products')],
            'lines.*.medicine_name' => ['nullable', 'string', 'max:300'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.frequency' => ['nullable', 'string', 'max:100'],
            'lines.*.duration' => ['nullable', 'string', 'max:100'],
            'lines.*.instructions' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json(
            $prescriptions->create($record, $data['lines'], $request->user(), $data['branch_id'] ?? null, $data['notes'] ?? null),
            201
        );
    }

    private function find(string $encounter): Encounter
    {
        return Encounter::findOrFail($encounter);
    }
}
