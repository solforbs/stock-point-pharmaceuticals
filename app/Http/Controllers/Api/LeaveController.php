<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Leave\LeaveException;
use App\Services\Leave\LeaveService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Part 21.16 — leave requests, approvals and balances. Approvers (and
 * payroll staff) see everyone in the organisation; anyone else with
 * leave.request sees and requests only for their own employee record.
 */
class LeaveController extends ApiController
{
    public function __construct(private readonly LeaveService $leave) {}

    public function types(Request $request): JsonResponse
    {
        $this->requireAnyLeavePermission($request);

        return response()->json(['data' => $this->leave->types($this->organisationId($request))]);
    }

    /** GET /api/leave/employees — the employees this user may raise or view leave for. */
    public function employees(Request $request): JsonResponse
    {
        $this->requireAnyLeavePermission($request);

        return response()->json(['data' => $this->visibleEmployees($request)
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('employee_no')
            ->get(['id', 'employee_no', 'name', 'job_title', 'department', 'branch_id', 'user_id', 'date_joined', 'date_left', 'is_active'])]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->requireAnyLeavePermission($request);
        $filters = $request->validate([
            'employee_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'in:DRAFT,PENDING,APPROVED,REJECTED,CANCELLED'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'leave_type_id' => ['nullable', 'uuid'],
        ]);
        $employeeIds = $this->visibleEmployees($request)->pluck('id');

        return response()->json(
            LeaveRequest::where('organisation_id', $this->organisationId($request))
                ->whereIn('employee_id', $employeeIds)
                ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
                ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
                ->when($filters['leave_type_id'] ?? null, fn ($q, $v) => $q->where('leave_type_id', $v))
                ->when($filters['year'] ?? null, fn ($q, $v) => $q->whereDate('start_date', '<=', "{$v}-12-31")->whereDate('end_date', '>=', "{$v}-01-01"))
                ->with(['employee:id,employee_no,name,department,user_id', 'leaveType:id,code,name,is_paid', 'approver:id,name', 'creator:id,name'])
                ->orderByDesc('start_date')->orderByDesc('created_at')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->requireAnyLeavePermission($request);
        $data = $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'leave_type_id' => ['required', 'uuid', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $this->visibleEmployees($request)->whereKey($data['employee_id'])->exists()) {
            return $this->error('NOT_OWN_EMPLOYEE', 'You may only request leave for your own employee record.', 403);
        }

        return $this->attempt(fn () => response()->json($this->withRelations($this->leave->request($this->organisationId($request), $data, $request->user()->id)), 201));
    }

    public function approve(Request $request, string $leave): JsonResponse
    {
        $this->requirePermission($request, 'leave.approve');
        $row = $this->find($request, $leave);

        return $this->attempt(fn () => response()->json($this->withRelations($this->leave->approve($row, $request->user()->id, $request->user()->can('payroll.approve.own')))));
    }

    public function reject(Request $request, string $leave): JsonResponse
    {
        $this->requirePermission($request, 'leave.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $row = $this->find($request, $leave);

        return $this->attempt(fn () => response()->json($this->withRelations($this->leave->reject($row, $request->user()->id, $data['reason']))));
    }

    /** The requester may withdraw their own request; an approver may cancel anyone's. */
    public function cancel(Request $request, string $leave): JsonResponse
    {
        $this->requireAnyLeavePermission($request);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $row = $this->find($request, $leave);

        $isOwn = (int) $row->created_by === $request->user()->id
            || Employee::whereKey($row->employee_id)->where('user_id', $request->user()->id)->exists();
        if (! $isOwn && ! $request->user()->can('leave.approve')) {
            return $this->error('FORBIDDEN', 'Only the requester or a leave approver can cancel this request.', 403);
        }

        return $this->attempt(fn () => response()->json($this->withRelations($this->leave->cancel($row, $data['reason'] ?? null))));
    }

    /** GET /api/leave/balances?year= — entitlement, taken, pending and balance per employee and leave type. */
    public function balances(Request $request): JsonResponse
    {
        $this->requireAnyLeavePermission($request);
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'employee_id' => ['nullable', 'uuid'],
        ]);
        $year = (int) ($data['year'] ?? now()->year);
        $types = $this->leave->types($this->organisationId($request))->where('is_active', true);

        $employees = $this->visibleEmployees($request)
            ->when($data['employee_id'] ?? null, fn ($q, $v) => $q->whereKey($v))
            ->where('is_active', true)->orderBy('employee_no')->get();

        $rows = [];
        foreach ($employees as $employee) {
            $rows[] = [
                'employee' => $employee->only(['id', 'employee_no', 'name', 'department', 'date_joined']),
                'balances' => $types->map(fn ($type) => $this->leave->balance($employee, $type, $year))->values()->all(),
            ];
        }

        return response()->json(['year' => $year, 'types' => $types->values(), 'data' => $rows]);
    }

    private function requireAnyLeavePermission(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! ($user->can('leave.request') || $user->can('leave.approve'))) {
            throw new HttpException(403, "You do not have the 'leave.request' permission.");
        }
    }

    /**
     * @return Builder<Employee>
     */
    private function visibleEmployees(Request $request): Builder
    {
        $user = $request->user();
        $seesEveryone = $user->can('leave.approve') || $user->can('payroll.view') || $user->can('payroll.process');

        return Employee::query()->where('organisation_id', $this->organisationId($request))
            ->when(! $seesEveryone, fn ($q) => $q->where('user_id', $user->id));
    }

    private function find(Request $request, string $id): LeaveRequest
    {
        $row = LeaveRequest::where('organisation_id', $this->organisationId($request))->findOrFail($id);
        if (! $this->visibleEmployees($request)->whereKey($row->employee_id)->exists()) {
            abort(404);
        }

        return $row;
    }

    private function withRelations(LeaveRequest $row): LeaveRequest
    {
        return $row->fresh(['employee:id,employee_no,name,department,user_id', 'leaveType:id,code,name,is_paid', 'approver:id,name', 'creator:id,name']) ?? $row;
    }

    /**
     * @param  callable(): JsonResponse  $action
     */
    private function attempt(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (LeaveException $e) {
            return $this->error($e->errorCode, $e->getMessage(), $e->status, $e->details);
        }
    }
}
