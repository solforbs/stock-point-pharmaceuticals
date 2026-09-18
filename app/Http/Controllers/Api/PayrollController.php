<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PayrollBand;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Part 21.16 — employees, statutory bands and payroll runs. */
class PayrollController extends ApiController
{
    public function employees(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'payroll.view');

        return response()->json(
            Employee::where('organisation_id', $this->organisationId($request))
                ->when(! $request->boolean('all_branches'), fn ($q) => $q->where('branch_id', $this->branchId($request)))
                ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
                ->orderBy('employee_no')->paginate($request->integer('per_page', 50))
        );
    }

    public function storeEmployee(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'payroll.process');

        $data = $request->validate($this->employeeRules(null));
        $employee = Employee::create($data + [
            'organisation_id' => $this->organisationId($request),
            'branch_id' => $data['branch_id'] ?? $this->branchId($request),
            'created_by' => $request->user()->id,
        ]);

        AuditLog::record('EMPLOYEE_CREATED', 'employee', $employee->id, ['reference' => $employee->employee_no]);

        return response()->json($employee, 201);
    }

    public function updateEmployee(Request $request, string $employee): JsonResponse
    {
        $this->requirePermission($request, 'payroll.process');
        $employee = Employee::where('organisation_id', $this->organisationId($request))->findOrFail($employee);

        $data = $request->validate($this->employeeRules($employee));
        $before = $employee->only(['basic_salary', 'regular_allowances', 'pension_contribution', 'is_active', 'date_left', 'bank_name']);
        $employee->update($data);

        // Part 19.2 — payroll and bank detail changes are always audited.
        AuditLog::record('EMPLOYEE_UPDATED', 'employee', $employee->id, [
            'reference' => $employee->employee_no, 'before_json' => $before,
            'after_json' => $employee->fresh()->only(['basic_salary', 'regular_allowances', 'pension_contribution', 'is_active', 'date_left', 'bank_name']),
            'changed_fields' => array_keys($data),
        ]);

        return response()->json($employee->fresh());
    }

    public function bands(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'payroll.view');
        $asOf = (string) $request->input('as_of', now()->toDateString());

        return response()->json(['as_of' => $asOf, 'data' => PayrollBand::inForce($this->organisationId($request), $asOf)]);
    }

    public function runs(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'payroll.view');

        return response()->json(
            PayrollRun::where('branch_id', $this->branchId($request))->withCount('lines')
                ->orderByDesc('period_year')->orderByDesc('period_month')->paginate($request->integer('per_page', 24))
        );
    }

    public function run(Request $request, string $run): JsonResponse
    {
        $this->requirePermission($request, 'payroll.view');

        return response()->json($this->find($request, $run)->load('lines.employee:id,employee_no,name,job_title,department'));
    }

    public function open(Request $request, PayrollService $payroll): JsonResponse
    {
        $this->requirePermission($request, 'payroll.process');
        $data = $request->validate(['period_year' => ['required', 'integer', 'between:2020,2100'], 'period_month' => ['required', 'integer', 'between:1,12']]);

        return response()->json($payroll->open($this->organisationId($request), $this->branchId($request), (int) $data['period_year'], (int) $data['period_month'], $request->user()->id), 201);
    }

    /** POST /api/payroll/runs/{id}/compute — with per-employee variable inputs for the month. */
    public function compute(Request $request, string $run, PayrollService $payroll): JsonResponse
    {
        $this->requirePermission($request, 'payroll.process');
        $data = $request->validate([
            'inputs' => ['nullable', 'array'],
            'inputs.*.employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'inputs.*.allowances' => ['nullable', 'numeric', 'min:0'],
            'inputs.*.overtime' => ['nullable', 'numeric', 'min:0'],
            'inputs.*.pension_contribution' => ['nullable', 'numeric', 'min:0'],
            'inputs.*.other_deductions' => ['nullable', 'numeric', 'min:0'],
        ]);
        $inputs = [];
        foreach ($data['inputs'] ?? [] as $row) {
            $inputs[$row['employee_id']] = array_map(fn ($v) => (string) $v, array_filter(collect($row)->except('employee_id')->all(), fn ($v) => $v !== null));
        }

        return response()->json($payroll->compute($this->find($request, $run), $request->user()->id, $inputs)->load('lines.employee:id,employee_no,name'));
    }

    public function approve(Request $request, string $run, PayrollService $payroll): JsonResponse
    {
        $this->requirePermission($request, 'payroll.process');

        return response()->json($payroll->approve($this->find($request, $run), $request->user()->id, $request->user()->can('payroll.approve.own')));
    }

    public function post(Request $request, string $run, PayrollService $payroll): JsonResponse
    {
        $this->requirePermission($request, 'journal.post');

        return response()->json($payroll->post($this->find($request, $run), $request->user()->id));
    }

    public function pay(Request $request, string $run, PayrollService $payroll): JsonResponse
    {
        $this->requirePermission($request, 'payment.record');
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:100']]);

        return response()->json($payroll->pay($this->find($request, $run), $request->user()->id, $data['reference'] ?? null));
    }

    /** GET /api/payroll/runs/{id}/payslips/{employee} — the payslip data, with the band breakdown. */
    public function payslip(Request $request, string $run, string $employee): JsonResponse
    {
        $this->requirePermission($request, 'payroll.view');
        $run = $this->find($request, $run);
        $line = $run->lines()->where('employee_id', $employee)->with('employee')->firstOrFail();

        return response()->json([
            'run' => $run->only(['id', 'doc_number', 'period_year', 'period_month', 'status', 'bands_as_of']),
            'employee' => $line->employee?->only(['id', 'employee_no', 'name', 'job_title', 'department', 'kra_pin', 'nssf_no', 'shif_no', 'bank_name']),
            'line' => $line->toArray(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeRules(?Employee $existing): array
    {
        $sometimes = $existing ? 'sometimes' : 'required';

        return [
            'employee_no' => [$sometimes, 'string', 'max:30', Rule::unique('employees', 'employee_no')->ignore($existing?->id)],
            'name' => [$sometimes, 'string', 'max:150'],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'national_id' => ['nullable', 'string', 'max:30'],
            'kra_pin' => ['nullable', 'string', 'max:20'],
            'nssf_no' => ['nullable', 'string', 'max:30'],
            'shif_no' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['nullable', 'in:PERMANENT,CONTRACT,CASUAL'],
            'date_joined' => ['nullable', 'date'],
            'date_left' => ['nullable', 'date'],
            'basic_salary' => [$sometimes, 'numeric', 'min:0'],
            'regular_allowances' => ['nullable', 'numeric', 'min:0'],
            'pension_contribution' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function find(Request $request, string $id): PayrollRun
    {
        return PayrollRun::where('branch_id', $this->branchId($request))->findOrFail($id);
    }
}
