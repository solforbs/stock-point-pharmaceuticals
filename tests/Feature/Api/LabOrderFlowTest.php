<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsHospitalWorld;
use Tests\TestCase;

/**
 * Clinician → laboratory → clinician. The order moves only along
 * PENDING → ACCEPTED → SAMPLE_COLLECTED → PROCESSING → COMPLETED, each
 * step by someone holding that step's permission, with actor and time
 * recorded; results come back to the encounter for the clinician.
 */
class LabOrderFlowTest extends TestCase
{
    use BuildsHospitalWorld;

    private User $labTech;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildHospitalWorld();

        // The clinician (the world's main user).
        $this->grantPermissions([
            'hospital.view', 'hospital.patient.view', 'hospital.patient.manage',
            'hospital.encounter.manage', 'hospital.consultation.manage',
            'laboratory.order.create', 'laboratory.order.view',
        ]);

        // The lab technician, in the same institution.
        $this->labTech = $this->colleague([
            'name' => 'Lab Tech Jane', 'username' => 'labtech', 'email' => 'labtech@example.test',
            'password' => 'password-long-enough',
        ]);
        $this->grantPermissionsTo($this->labTech, [
            'laboratory.view', 'laboratory.order.accept', 'laboratory.sample.collect',
            'laboratory.result.enter', 'laboratory.order.cancel',
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_the_full_lab_journey_from_order_to_reviewed_results(): void
    {
        $patient = $this->registerPatient();
        $encounter = $this->openEncounter($patient['id']);
        $this->postJson("/api/hospital/encounters/{$encounter['id']}/start-consultation")->assertOk();

        // The clinician orders from the catalogue.
        $order = $this->postJson("/api/hospital/encounters/{$encounter['id']}/lab-orders", [
            'facility_id' => $this->facility->id,
            'test_ids' => [$this->malaria->id, $this->bloodSugar->id],
            'clinical_notes' => 'Fever and headache for 3 days.',
        ])->assertCreated()->json();

        $this->assertStringStartsWith('LAB-', $order['order_no']);
        $this->assertSame('PENDING', $order['status']);
        $this->getJson("/api/hospital/encounters/{$encounter['id']}")->assertOk()
            ->assertJsonPath('status', 'AWAITING_RESULTS')
            ->assertJsonCount(3, 'charges'); // consultation + two tests

        // The bench works it — as the lab technician.
        Sanctum::actingAs($this->labTech);

        $this->getJson('/api/laboratory/orders?status=PENDING')->assertOk()->assertJsonPath('data.0.id', $order['id']);

        // Results cannot be entered before the sample exists.
        $this->postJson("/api/laboratory/orders/{$order['id']}/results", [
            'results' => [['lab_order_test_id' => $order['tests'][0]['id'], 'result_value' => 'Positive']],
        ])->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');

        $this->postJson("/api/laboratory/orders/{$order['id']}/accept")->assertOk()->assertJsonPath('status', 'ACCEPTED');
        $this->postJson("/api/laboratory/orders/{$order['id']}/collect-sample", ['sample_type' => 'Blood'])
            ->assertCreated()->assertJsonPath('sample_type', 'Blood');
        $this->postJson("/api/laboratory/orders/{$order['id']}/start-processing")->assertOk()->assertJsonPath('status', 'PROCESSING');

        // A partial result set keeps the order in PROCESSING…
        $byName = collect($order['tests'])->keyBy('test_name');
        $this->postJson("/api/laboratory/orders/{$order['id']}/results", [
            'results' => [[
                'lab_order_test_id' => $byName['Malaria Parasite Slide']['id'],
                'result_value' => 'Positive', 'is_abnormal' => true,
            ]],
        ])->assertOk()->assertJsonPath('status', 'PROCESSING');

        // …and the last result completes it.
        $completed = $this->postJson("/api/laboratory/orders/{$order['id']}/results", [
            'results' => [[
                'lab_order_test_id' => $byName['Random Blood Sugar']['id'],
                'result_value' => '5.4', 'is_abnormal' => false,
            ]],
        ])->assertOk()->assertJsonPath('status', 'COMPLETED')->json();
        $this->assertNotNull($completed['completed_at']);

        // The clinician reads the results from the encounter.
        Sanctum::actingAs($this->user);
        $this->getJson("/api/hospital/encounters/{$encounter['id']}")->assertOk()
            ->assertJsonPath('status', 'WAITING')
            ->assertJsonPath('lab_orders.0.tests.0.result_value', 'Positive');

        $this->assertTrue(AuditLog::where('action', 'LAB_RESULT_ENTERED')->where('user_id', $this->labTech->id)->exists());
    }

    public function test_transitions_need_their_permission_and_cannot_be_skipped(): void
    {
        $patient = $this->registerPatient();
        $encounter = $this->openEncounter($patient['id']);
        $order = $this->postJson("/api/hospital/encounters/{$encounter['id']}/lab-orders", [
            'facility_id' => $this->facility->id,
            'test_ids' => [$this->malaria->id],
        ])->assertCreated()->json();

        // The clinician holds no bench permissions: the module gate stops them.
        $this->postJson("/api/laboratory/orders/{$order['id']}/accept")
            ->assertStatus(403)->assertJsonPath('code', 'MODULE_ACCESS_DENIED');

        // The bench cannot jump PENDING → sample collection.
        Sanctum::actingAs($this->labTech);
        $this->postJson("/api/laboratory/orders/{$order['id']}/collect-sample", ['sample_type' => 'Blood'])
            ->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');
    }

    public function test_cancelling_an_order_waives_its_pending_charges(): void
    {
        $patient = $this->registerPatient();
        $encounter = $this->openEncounter($patient['id'], ['consultation_fee' => '0']);
        $order = $this->postJson("/api/hospital/encounters/{$encounter['id']}/lab-orders", [
            'facility_id' => $this->facility->id,
            'test_ids' => [$this->malaria->id],
        ])->assertCreated()->json();

        Sanctum::actingAs($this->labTech);
        $this->postJson("/api/laboratory/orders/{$order['id']}/cancel", ['reason' => 'Duplicate order'])
            ->assertOk()->assertJsonPath('status', 'CANCELLED');

        Sanctum::actingAs($this->user);
        $charges = $this->getJson("/api/hospital/encounters/{$encounter['id']}")->assertOk()->json('charges');
        $this->assertSame('WAIVED', collect($charges)->firstWhere('charge_type', 'LAB_TEST')['status']);
    }

    /** Branch-scoped role for another user, mirroring grantPermissions(). */
    private function grantPermissionsTo(User $user, array $permissions): void
    {
        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $role = Role::firstOrCreate([
            'organisation_id' => $this->org->id, 'name' => 'Lab bench role', 'guard_name' => 'web', 'branch_id' => null,
        ]);
        $role->syncPermissions($permissions);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
