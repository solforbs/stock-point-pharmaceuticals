<?php

namespace App\Http\Controllers\Api;

use App\Models\Prescription;
use App\Services\Hospital\PrescriptionService;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pharmacy-side prescriptions: the queue of prescriptions sent to the
 * active branch, and dispensing through the existing checkout engine.
 * These routes live with the pharmacy module (prescription.* permissions),
 * not the hospital module.
 */
class PrescriptionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'prescription.view');

        $branchId = $this->branchId($request);

        return response()->json(
            Prescription::query()
                // The branch's own queue, plus unrouted prescriptions any
                // pharmacy may pick up.
                ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->with(['patient:id,patient_no,first_name,last_name', 'prescribedBy:id,name', 'encounter:id,encounter_no'])
                ->withCount('lines')
                ->orderByDesc('created_at')->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $prescription, PrescriptionService $prescriptions): JsonResponse
    {
        $this->requireAnyPermission($request, ['prescription.view', 'hospital.prescription.create']);

        $record = Prescription::with([
            'patient:id,patient_no,first_name,last_name,sex,date_of_birth',
            'encounter:id,encounter_no', 'prescribedBy:id,name', 'dispensedBy:id,name',
            'lines.product:id,code,name', 'sale:id,doc_number,grand_total,status',
        ])->findOrFail($prescription);

        // A pending prescription carries what the till will charge, so the
        // pharmacist collects the exact amount instead of computing one.
        $estimate = null;
        if ($record->status === 'PENDING') {
            try {
                $estimate = $prescriptions->priceEstimate(
                    (string) $record->organisation_id,
                    (string) ($record->branch_id ?? $this->branchId($request)),
                    $request->user()->id,
                    $record->lines->map(fn ($l) => ['product_id' => $l->product_id, 'uom_id' => $l->uom_id, 'quantity' => (string) $l->quantity])->all(),
                );
            } catch (\Throwable) {
                // Pricing can fail (a product deactivated since it was
                // written); the prescription itself must still open.
            }
        }

        return response()->json(['estimate' => $estimate] + $record->toArray());
    }

    public function dispense(Request $request, string $prescription, PrescriptionService $prescriptions): JsonResponse
    {
        $this->requirePermission($request, 'prescription.dispense');

        $data = $request->validate([
            'store_id' => ['required', 'uuid', TenantRules::exists('stores')],
            'payments' => ['sometimes', 'array'],
            'payments.*.method' => ['required_with:payments', 'string', 'max:30'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ]);

        $record = Prescription::with('lines')->findOrFail($prescription);

        return response()->json($prescriptions->dispense($record, $request->user(), $this->branchId($request), [
            'store_id' => $data['store_id'],
            'idempotency_key' => $this->idempotencyKey($request),
            'payments' => collect($data['payments'] ?? [])->map(fn ($p) => [
                'method' => $p['method'],
                'amount' => (string) $p['amount'],
                'reference' => $p['reference'] ?? null,
            ])->all(),
        ]));
    }

    public function cancel(Request $request, string $prescription, PrescriptionService $prescriptions): JsonResponse
    {
        $this->requireAnyPermission($request, ['hospital.prescription.create', 'prescription.dispense']);

        return response()->json($prescriptions->cancel(
            Prescription::findOrFail($prescription), $request->user(), $request->input('reason'),
        ));
    }
}
