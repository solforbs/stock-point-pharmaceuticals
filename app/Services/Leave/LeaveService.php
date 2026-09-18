<?php

namespace App\Services\Leave;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\NumberSequence;
use Database\Seeders\LeaveTypesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Part 21.16 — leave requests and balances. A request counts working days
 * (Monday to Friday; public holidays are not deducted), only APPROVED days
 * consume the balance, and nobody approves their own leave (Part 18.3).
 */
class LeaveService
{
    /**
     * @return Collection<int, LeaveType>
     */
    public function types(string $organisationId): Collection
    {
        if (! LeaveType::where('organisation_id', $organisationId)->exists()) {
            LeaveTypesSeeder::seedFor($organisationId);
        }

        return LeaveType::where('organisation_id', $organisationId)->orderBy('code')->get();
    }

    /**
     * Working days (Mon-Fri) between two dates inclusive, optionally clipped
     * to one calendar year.
     */
    public function workingDays(Carbon $start, Carbon $end, ?int $year = null): int
    {
        $from = $start->copy()->startOfDay();
        $to = $end->copy()->startOfDay();
        if ($year !== null) {
            $yearStart = Carbon::create($year, 1, 1);
            $yearEnd = Carbon::create($year, 12, 31);
            $from = $from->lt($yearStart) ? $yearStart : $from;
            $to = $to->gt($yearEnd) ? $yearEnd : $to;
        }

        $days = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if (! $d->isWeekend()) {
                $days++;
            }
        }

        return $days;
    }

    /**
     * The year's entitlement, pro-rated (to the nearest half day) for an
     * employee who joined or left part-way through it.
     */
    public function entitlement(Employee $employee, LeaveType $type, int $year): string
    {
        $full = (string) $type->days_per_year;
        $yearStart = Carbon::create($year, 1, 1);
        $yearEnd = Carbon::create($year, 12, 31);
        $from = $employee->date_joined && $employee->date_joined->gt($yearStart) ? $employee->date_joined->copy() : $yearStart;
        $to = $employee->date_left && $employee->date_left->lt($yearEnd) ? $employee->date_left->copy() : $yearEnd;

        if ($from->gt($to)) {
            return '0.00';
        }
        if ($from->eq($yearStart) && $to->eq($yearEnd)) {
            return bcadd($full, '0', 2);
        }

        $served = (int) $from->diffInDays($to) + 1;
        $daysInYear = $yearStart->isLeapYear() ? 366 : 365;
        $halfDays = (int) round((float) bcdiv(bcmul(bcmul($full, (string) $served, 6), '2', 6), (string) $daysInYear, 6));

        return bcdiv((string) $halfDays, '2', 2);
    }

    /**
     * Working days of one type falling in a year, for the given statuses.
     *
     * @param  list<string>  $statuses
     */
    public function daysUsed(string $employeeId, string $leaveTypeId, int $year, array $statuses = ['APPROVED'], ?string $exceptRequestId = null): string
    {
        $requests = LeaveRequest::where('employee_id', $employeeId)->where('leave_type_id', $leaveTypeId)
            ->whereIn('status', $statuses)
            ->whereDate('start_date', '<=', "{$year}-12-31")->whereDate('end_date', '>=', "{$year}-01-01")
            ->when($exceptRequestId, fn ($q) => $q->whereKeyNot($exceptRequestId))
            ->get();

        $total = 0;
        foreach ($requests as $request) {
            $total += $this->workingDays($request->start_date, $request->end_date, $year);
        }

        return bcadd((string) $total, '0', 2);
    }

    /**
     * @return array{year: int, employee_id: string, leave_type_id: string, code: string, name: string, is_paid: bool, entitlement: string, taken: string, pending: string, balance: string}
     */
    public function balance(Employee $employee, LeaveType $type, int $year): array
    {
        $entitlement = $this->entitlement($employee, $type, $year);
        $taken = $this->daysUsed($employee->id, $type->id, $year);
        $pending = $this->daysUsed($employee->id, $type->id, $year, ['PENDING']);

        return [
            'year' => $year, 'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'code' => $type->code, 'name' => $type->name,
            'is_paid' => $type->is_paid, 'entitlement' => $entitlement, 'taken' => $taken, 'pending' => $pending,
            'balance' => bcsub($entitlement, $taken, 2),
        ];
    }

    /**
     * @param  array{employee_id: string, leave_type_id: string, start_date: string, end_date: string, reason?: ?string}  $data
     */
    public function request(string $organisationId, array $data, int $userId): LeaveRequest
    {
        $employee = Employee::where('organisation_id', $organisationId)->findOrFail($data['employee_id']);
        $type = LeaveType::where('organisation_id', $organisationId)->findOrFail($data['leave_type_id']);
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        if (! $type->is_active) {
            throw new LeaveException('LEAVE_TYPE_INACTIVE', "{$type->name} is no longer offered.");
        }
        $days = $this->workingDays($start, $end);
        if ($days === 0) {
            throw new LeaveException('NO_WORKING_DAYS', 'The requested period contains no working days (Monday to Friday).');
        }

        return DB::transaction(function () use ($organisationId, $employee, $type, $start, $end, $days, $data, $userId) {
            // Serialise requests for one employee so two overlapping requests cannot both pass the checks.
            Employee::whereKey($employee->id)->lockForUpdate()->first();

            $this->assertNoOverlap($employee->id, $start, $end);
            $this->assertBalance($employee, $type, $start, $end);

            $request = LeaveRequest::create([
                'organisation_id' => $organisationId,
                'doc_number' => NumberSequence::next($organisationId, 'LEAVE', null, 'LV'),
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => (string) $days,
                'reason' => $data['reason'] ?? null,
                'status' => 'PENDING',
                'created_by' => $userId,
            ]);

            AuditLog::record('LEAVE_REQUESTED', 'leave_request', $request->id, [
                'reference' => $request->doc_number,
                'after_json' => ['employee' => $employee->employee_no, 'type' => $type->code, 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'days' => (string) $days],
            ]);

            return $request;
        });
    }

    public function approve(LeaveRequest $request, int $userId, bool $mayApproveOwn): LeaveRequest
    {
        return DB::transaction(function () use ($request, $userId, $mayApproveOwn) {
            $request = LeaveRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($request, ['PENDING']);
            $employee = Employee::findOrFail($request->employee_id);

            if ($employee->user_id !== null && (int) $employee->user_id === $userId && ! $mayApproveOwn) {
                throw new LeaveException('SELF_APPROVAL', 'Leave must be approved by someone other than the employee taking it (Part 18.3 separation of duties).', 403);
            }

            // Re-checked: another request may have been approved since this one was raised.
            $type = LeaveType::findOrFail($request->leave_type_id);
            $this->assertBalance($employee, $type, $request->start_date, $request->end_date, $request->id);

            $request->update(['status' => 'APPROVED', 'approved_by' => $userId, 'approved_at' => now()]);
            AuditLog::record('LEAVE_APPROVED', 'leave_request', $request->id, ['reference' => $request->doc_number, 'before_json' => ['status' => 'PENDING'], 'after_json' => ['status' => 'APPROVED']]);

            return $request;
        });
    }

    public function reject(LeaveRequest $request, int $userId, string $reason): LeaveRequest
    {
        return DB::transaction(function () use ($request, $userId, $reason) {
            $request = LeaveRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($request, ['PENDING']);

            $request->update(['status' => 'REJECTED', 'approved_by' => $userId, 'approved_at' => now(), 'rejection_reason' => $reason]);
            AuditLog::record('LEAVE_REJECTED', 'leave_request', $request->id, ['reference' => $request->doc_number, 'before_json' => ['status' => 'PENDING'], 'after_json' => ['status' => 'REJECTED'], 'reason' => $reason]);

            return $request;
        });
    }

    public function cancel(LeaveRequest $request, ?string $reason): LeaveRequest
    {
        return DB::transaction(function () use ($request, $reason) {
            $request = LeaveRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($request, ['DRAFT', 'PENDING', 'APPROVED']);
            $before = $request->status;

            $request->update(['status' => 'CANCELLED']);
            AuditLog::record('LEAVE_CANCELLED', 'leave_request', $request->id, ['reference' => $request->doc_number, 'before_json' => ['status' => $before], 'after_json' => ['status' => 'CANCELLED'], 'reason' => $reason]);

            return $request;
        });
    }

    private function assertNoOverlap(string $employeeId, Carbon $start, Carbon $end): void
    {
        $clash = LeaveRequest::where('employee_id', $employeeId)->whereIn('status', ['PENDING', 'APPROVED'])
            ->whereDate('start_date', '<=', $end->toDateString())->whereDate('end_date', '>=', $start->toDateString())
            ->first();

        if ($clash) {
            throw new LeaveException('LEAVE_OVERLAP', "This period overlaps {$clash->doc_number} ({$clash->start_date->toDateString()} to {$clash->end_date->toDateString()}, {$clash->status}).", 422, ['conflicting_request' => $clash->doc_number]);
        }
    }

    /**
     * A paid type may not go below zero in any year the request touches.
     */
    private function assertBalance(Employee $employee, LeaveType $type, Carbon $start, Carbon $end, ?string $exceptRequestId = null): void
    {
        if (! $type->is_paid) {
            return;
        }

        for ($year = $start->year; $year <= $end->year; $year++) {
            $wanted = $this->workingDays($start, $end, $year);
            if ($wanted === 0) {
                continue;
            }
            $available = bcsub($this->entitlement($employee, $type, $year), $this->daysUsed($employee->id, $type->id, $year, ['APPROVED'], $exceptRequestId), 2);

            if (bccomp((string) $wanted, $available, 2) > 0) {
                throw new LeaveException('INSUFFICIENT_LEAVE_BALANCE', "{$type->name}: {$wanted} day(s) requested in {$year} but only {$available} remain.", 422, ['year' => $year, 'requested' => (string) $wanted, 'available' => $available]);
            }
        }
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertStatus(LeaveRequest $request, array $expected): void
    {
        if (! in_array($request->status, $expected, true)) {
            throw new LeaveException('INVALID_STATUS', "Leave request {$request->doc_number} is {$request->status}, not ".implode('/', $expected).'.', 409);
        }
    }
}
