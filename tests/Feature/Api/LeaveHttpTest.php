<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 21.16 — leave: working-day counting, balances pro-rated from the
 * joining date, no overlaps, no overdrawn paid leave, and nobody approves
 * their own request.
 */
class LeaveHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['leave.request', 'leave.approve', 'payroll.view']);
        Sanctum::actingAs($this->user);

        $this->employee = Employee::create([
            'organisation_id' => $this->org->id, 'branch_id' => $this->branch->id, 'employee_no' => 'E001', 'name' => 'Akai Lokwang',
            'date_joined' => '2020-01-15', 'basic_salary' => '50000', 'is_active' => true,
        ]);
    }

    public function test_a_request_counts_working_days_and_only_approval_consumes_the_balance(): void
    {
        $types = collect($this->getJson('/api/leave/types')->assertOk()->json('data'))->keyBy('code');
        $this->assertSame(['ANNUAL', 'COMPASSIONATE', 'MATERNITY', 'PATERNITY', 'SICK', 'UNPAID'], $types->keys()->all());
        $this->assertSame('21.00', $types['ANNUAL']['days_per_year']);
        $this->assertFalse($types['UNPAID']['is_paid']);

        // Mon 5 Oct to Fri 16 Oct 2026: two weekends inside, ten working days.
        $request = $this->postJson('/api/leave/requests', [
            'employee_id' => $this->employee->id, 'leave_type_id' => $types['ANNUAL']['id'], 'start_date' => '2026-10-05', 'end_date' => '2026-10-16', 'reason' => 'Family visit',
        ])->assertCreated()->assertJsonPath('status', 'PENDING')->assertJsonPath('days', '10.00')->assertJsonPath('employee.employee_no', 'E001')->json();
        $this->assertStringStartsWith('LV-', $request['doc_number']);

        $annual = fn () => collect($this->getJson('/api/leave/balances?year=2026')->assertOk()->json('data.0.balances'))->firstWhere('code', 'ANNUAL');
        $this->assertSame(['21.00', '0.00', '10.00', '21.00'], [$annual()['entitlement'], $annual()['taken'], $annual()['pending'], $annual()['balance']]);

        $approver = $this->actingAsNewUser('approver', ['leave.approve']);
        $this->postJson("/api/leave/requests/{$request['id']}/approve")->assertOk()->assertJsonPath('status', 'APPROVED')->assertJsonPath('approver.id', $approver->id);
        $this->assertSame(['10.00', '0.00', '11.00'], [$annual()['taken'], $annual()['pending'], $annual()['balance']]);

        // The list filters by status and year.
        $this->getJson('/api/leave/requests?status=APPROVED&year=2026')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/leave/requests?year=2025')->assertOk()->assertJsonPath('total', 0);
        $this->assertSame(1, AuditLog::where('action', 'LEAVE_APPROVED')->where('entity_id', $request['id'])->count());

        // Cancelling approved leave gives the days back.
        $this->postJson("/api/leave/requests/{$request['id']}/cancel", ['reason' => 'Trip postponed'])->assertOk()->assertJsonPath('status', 'CANCELLED');
        $this->assertSame('21.00', $annual()['balance']);
        $this->postJson("/api/leave/requests/{$request['id']}/approve")->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_overlapping_and_overdrawn_requests_are_refused_but_unpaid_leave_is_not_capped(): void
    {
        $annual = $this->type('ANNUAL');
        $first = $this->postJson('/api/leave/requests', ['employee_id' => $this->employee->id, 'leave_type_id' => $annual->id, 'start_date' => '2026-10-05', 'end_date' => '2026-10-16'])->assertCreated()->json();

        $this->postJson('/api/leave/requests', ['employee_id' => $this->employee->id, 'leave_type_id' => $this->type('SICK')->id, 'start_date' => '2026-10-16', 'end_date' => '2026-10-19'])
            ->assertStatus(422)->assertJsonPath('error.code', 'LEAVE_OVERLAP')->assertJsonPath('error.details.conflicting_request', $first['doc_number']);

        $this->actingAsNewUser('approver', ['leave.approve']);
        $this->postJson("/api/leave/requests/{$first['id']}/approve")->assertOk();
        Sanctum::actingAs($this->user);

        // 11 days remain; 2 Nov to 17 Nov is 12 working days.
        $this->postJson('/api/leave/requests', ['employee_id' => $this->employee->id, 'leave_type_id' => $annual->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-17'])
            ->assertStatus(422)->assertJsonPath('error.code', 'INSUFFICIENT_LEAVE_BALANCE')->assertJsonPath('error.details.available', '11.00');

        $this->postJson('/api/leave/requests', ['employee_id' => $this->employee->id, 'leave_type_id' => $this->type('UNPAID')->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-17'])
            ->assertCreated()->assertJsonPath('days', '12.00');

        // A weekend is not leave.
        $this->postJson('/api/leave/requests', ['employee_id' => $this->employee->id, 'leave_type_id' => $annual->id, 'start_date' => '2026-12-05', 'end_date' => '2026-12-06'])
            ->assertStatus(422)->assertJsonPath('error.code', 'NO_WORKING_DAYS');
        $this->postJson('/api/leave/requests', ['employee_id' => $this->employee->id, 'leave_type_id' => $annual->id, 'start_date' => '2026-12-10', 'end_date' => '2026-12-01'])
            ->assertStatus(422);
    }

    public function test_nobody_approves_their_own_leave_without_payroll_approve_own(): void
    {
        $this->employee->update(['user_id' => $this->user->id]);
        $request = $this->postJson('/api/leave/requests', ['employee_id' => $this->employee->id, 'leave_type_id' => $this->type('ANNUAL')->id, 'start_date' => '2026-10-05', 'end_date' => '2026-10-06'])->assertCreated()->json();

        $this->postJson("/api/leave/requests/{$request['id']}/approve")->assertStatus(403)->assertJsonPath('error.code', 'SELF_APPROVAL');

        $this->grantPermissions(['leave.request', 'leave.approve', 'payroll.view', 'payroll.approve.own']);
        $this->postJson("/api/leave/requests/{$request['id']}/approve")->assertOk()->assertJsonPath('status', 'APPROVED');
    }

    public function test_rejection_needs_a_reason_and_approval_needs_the_permission(): void
    {
        $request = $this->postJson('/api/leave/requests', ['employee_id' => $this->employee->id, 'leave_type_id' => $this->type('SICK')->id, 'start_date' => '2026-10-05', 'end_date' => '2026-10-07'])->assertCreated()->json();

        $this->postJson("/api/leave/requests/{$request['id']}/reject")->assertStatus(422);
        $this->postJson("/api/leave/requests/{$request['id']}/reject", ['reason' => 'No sick note attached'])->assertOk()
            ->assertJsonPath('status', 'REJECTED')->assertJsonPath('rejection_reason', 'No sick note attached');
        $this->postJson("/api/leave/requests/{$request['id']}/cancel")->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->actingAsNewUser('clerk', ['leave.request']);
        $this->postJson("/api/leave/requests/{$request['id']}/approve")->assertStatus(403);

        $this->actingAsNewUser('nobody', ['sale.view'], 'No leave role');
        $this->getJson('/api/leave/requests')->assertStatus(403);
        $this->getJson('/api/leave/balances')->assertStatus(403);
    }

    public function test_a_requester_without_approval_rights_sees_and_requests_only_their_own_leave(): void
    {
        $this->postJson('/api/leave/requests', ['employee_id' => $this->employee->id, 'leave_type_id' => $this->type('ANNUAL')->id, 'start_date' => '2026-10-05', 'end_date' => '2026-10-06'])->assertCreated();

        $cashier = $this->actingAsNewUser('cashier2', ['leave.request']);
        $own = Employee::create(['organisation_id' => $this->org->id, 'branch_id' => $this->branch->id, 'user_id' => $cashier->id, 'employee_no' => 'E002', 'name' => 'Ekai Nakwawi', 'date_joined' => '2026-07-01', 'basic_salary' => '30000', 'is_active' => true]);

        $this->getJson('/api/leave/employees')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.employee_no', 'E002');
        $this->getJson('/api/leave/requests')->assertOk()->assertJsonPath('total', 0);
        $this->postJson('/api/leave/requests', ['employee_id' => $this->employee->id, 'leave_type_id' => $this->type('ANNUAL')->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-03'])
            ->assertStatus(403)->assertJsonPath('error.code', 'NOT_OWN_EMPLOYEE');

        // Joined 1 July: 184 of 365 days of 21 = 10.59, rounded to the half day.
        $balances = $this->getJson('/api/leave/balances?year=2026')->assertOk()->assertJsonCount(1, 'data')->json('data.0.balances');
        $this->assertSame('10.50', collect($balances)->firstWhere('code', 'ANNUAL')['entitlement']);
        $this->assertSame('0.00', collect($balances)->firstWhere('code', 'UNPAID')['entitlement']);

        $mine = $this->postJson('/api/leave/requests', ['employee_id' => $own->id, 'leave_type_id' => $this->type('ANNUAL')->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-03'])->assertCreated()->json();
        $this->postJson("/api/leave/requests/{$mine['id']}/cancel")->assertOk()->assertJsonPath('status', 'CANCELLED');
    }

    private function type(string $code): LeaveType
    {
        $this->getJson('/api/leave/types')->assertOk();

        return LeaveType::where('organisation_id', $this->org->id)->where('code', $code)->firstOrFail();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function actingAsNewUser(string $username, array $permissions, ?string $roleName = null): User
    {
        $user = User::create(['name' => ucfirst($username), 'username' => $username, 'email' => "{$username}@example.test", 'password' => 'password-long-enough']);
        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $role = Role::firstOrCreate(['organisation_id' => $this->org->id, 'name' => $roleName ?? "Role {$username}", 'guard_name' => 'web', 'branch_id' => null]);
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($user);

        return $user;
    }
}
