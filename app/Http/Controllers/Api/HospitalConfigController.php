<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Facility;
use App\Models\HospitalLevel;
use App\Models\User;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hospital module configuration: facilities (with their service mix and
 * hospital level), departments, and staff assignment. Levels are a
 * configurable catalogue, never hard-coded.
 */
class HospitalConfigController extends ApiController
{
    public function levels(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'hospital.view');

        return response()->json(HospitalLevel::where('is_active', true)->orderBy('rank')->get());
    }

    public function facilities(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'hospital.view');

        return response()->json(
            Facility::query()
                ->accessibleTo($request->user())
                ->when($request->boolean('hospital_only'), fn ($q) => $q->where('offers_hospital', true))
                ->with(['hospitalLevel:id,name', 'pharmacyBranch:id,code,name', 'staff:users.id,name,email', 'departments' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
                ->orderBy('name')->get()
        );
    }

    public function storeFacility(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'hospital.manage');

        $data = $this->validateFacility($request);

        $facility = Facility::create($data);
        AuditLog::record('FACILITY_CREATED', 'facility', $facility->id, [
            'user_id' => $request->user()->id,
            'reference' => $facility->code,
            'after_json' => $facility->only(['name', 'offers_hospital', 'offers_laboratory', 'offers_pharmacy']),
        ]);

        return response()->json($facility->load('hospitalLevel:id,name'), 201);
    }

    public function updateFacility(Request $request, string $facility): JsonResponse
    {
        $this->requirePermission($request, 'hospital.manage');

        $record = Facility::findOrFail($facility);
        $record->update($this->validateFacility($request, updating: true));
        AuditLog::record('FACILITY_UPDATED', 'facility', $record->id, [
            'user_id' => $request->user()->id,
            'reference' => $record->code,
            'changed_fields' => array_keys($record->getChanges()),
        ]);

        return response()->json($record->fresh(['hospitalLevel:id,name', 'departments']));
    }

    public function storeDepartment(Request $request, string $facility): JsonResponse
    {
        $this->requirePermission($request, 'hospital.manage');

        $record = Facility::findOrFail($facility);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:30'],
        ]);

        return response()->json($record->departments()->create($data), 201);
    }

    public function updateDepartment(Request $request, string $department): JsonResponse
    {
        $this->requirePermission($request, 'hospital.manage');

        $record = Department::findOrFail($department);
        $record->update($request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return response()->json($record);
    }

    /** The institution's active accounts, for the staff assignment picker. */
    public function staffOptions(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'hospital.manage');

        return response()->json(
            User::where('organisation_id', $this->organisationId($request))
                ->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email'])
        );
    }

    /** Assign or remove staff: the facility-level access list. */
    public function syncStaff(Request $request, string $facility): JsonResponse
    {
        $this->requirePermission($request, 'hospital.manage');

        $record = Facility::findOrFail($facility);
        $data = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        // Only this institution's own accounts can be assigned.
        $userIds = User::whereIn('id', $data['user_ids'])
            ->where('organisation_id', $this->organisationId($request))->pluck('id')->all();
        $record->staff()->sync($userIds);

        AuditLog::record('FACILITY_STAFF_SYNCED', 'facility', $record->id, [
            'user_id' => $request->user()->id,
            'reference' => $record->code,
            'after_json' => ['user_ids' => $userIds],
        ]);

        return response()->json($record->load('staff:id,name,email'));
    }

    /** @return array<string, mixed> */
    private function validateFacility(Request $request, bool $updating = false): array
    {
        $sometimes = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'code' => [$sometimes, 'string', 'max:30'],
            'name' => [$sometimes, 'string', 'max:200'],
            'hospital_level_id' => ['nullable', 'uuid', TenantRules::exists('hospital_levels')],
            'offers_hospital' => ['sometimes', 'boolean'],
            'offers_laboratory' => ['sometimes', 'boolean'],
            'offers_pharmacy' => ['sometimes', 'boolean'],
            'pharmacy_branch_id' => ['nullable', 'uuid', TenantRules::exists('branches')],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
