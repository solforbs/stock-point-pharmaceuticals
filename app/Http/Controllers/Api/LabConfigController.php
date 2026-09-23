<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\LabTest;
use App\Models\LabTestCategory;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Laboratory configuration: categories and the priced test catalogue.
 * Everything here is administrator-maintained data — no test or category is
 * hard-coded anywhere in the workflow.
 */
class LabConfigController extends ApiController
{
    /** Laboratory facilities, for the lab dashboard's facility picker. */
    public function facilities(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'laboratory.view');

        return response()->json(
            Facility::query()->accessibleTo($request->user())
                ->where('offers_laboratory', true)
                ->with('hospitalLevel:id,name')->orderBy('name')->get()
        );
    }

    public function categories(Request $request): JsonResponse
    {
        $this->requireAnyPermission($request, ['laboratory.view', 'laboratory.order.create']);

        return response()->json(LabTestCategory::where('is_active', true)->orderBy('name')->get());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'laboratory.category.manage');

        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        return response()->json(LabTestCategory::create($data), 201);
    }

    public function tests(Request $request): JsonResponse
    {
        $this->requireAnyPermission($request, ['laboratory.view', 'laboratory.order.create']);

        return response()->json(
            LabTest::query()
                ->when($request->input('category_id'), fn ($q, $v) => $q->where('category_id', $v))
                ->when($request->input('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")))
                ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
                ->with('category:id,name')->orderBy('name')
                ->paginate($request->integer('per_page', 50))
        );
    }

    public function storeTest(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'laboratory.test.manage');

        $test = LabTest::create($this->validateTest($request));

        AuditLog::record('LAB_TEST_CREATED', 'lab_test', $test->id, [
            'user_id' => $request->user()->id,
            'reference' => $test->code,
            'after_json' => $test->only(['name', 'price']),
        ]);

        return response()->json($test->load('category:id,name'), 201);
    }

    public function updateTest(Request $request, string $test): JsonResponse
    {
        $this->requirePermission($request, 'laboratory.test.manage');

        $record = LabTest::findOrFail($test);
        $record->update($this->validateTest($request, updating: true));

        AuditLog::record('LAB_TEST_UPDATED', 'lab_test', $record->id, [
            'user_id' => $request->user()->id,
            'reference' => $record->code,
            'changed_fields' => array_keys($record->getChanges()),
        ]);

        return response()->json($record->fresh('category:id,name'));
    }

    /** @return array<string, mixed> */
    private function validateTest(Request $request, bool $updating = false): array
    {
        $sometimes = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'category_id' => [$sometimes, 'uuid', TenantRules::exists('lab_test_categories')],
            'code' => [$sometimes, 'string', 'max:30'],
            'name' => [$sometimes, 'string', 'max:200'],
            'price' => [$sometimes, 'numeric', 'min:0'],
            'sample_type' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'normal_range' => ['nullable', 'string', 'max:200'],
            'unit' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
