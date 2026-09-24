<?php

namespace Tests\Feature\Api;

use App\Events\PatientFlowUpdated;
use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsHospitalWorld;
use Tests\TestCase;

/**
 * The reception → clinician journey: one patient record forever, a new
 * encounter per visit, the consultation fee charged at reception, the
 * clinician's notes/diagnosis on the encounter, and a clean completion.
 */
class HospitalFlowTest extends TestCase
{
    use BuildsHospitalWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildHospitalWorld();
        $this->grantPermissions([
            'hospital.view', 'hospital.patient.view', 'hospital.patient.manage',
            'hospital.encounter.manage', 'hospital.consultation.manage',
            'hospital.diagnosis.manage', 'hospital.referral.manage',
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_reception_registers_once_then_every_visit_is_a_new_encounter_on_the_same_record(): void
    {
        $patient = $this->registerPatient();
        $this->assertStringStartsWith('PAT-', $patient['patient_no']);

        $first = $this->openEncounter($patient['id']);
        $second = $this->openEncounter($patient['id'], ['consultation_fee' => '0']);

        $this->assertSame(1, Patient::count(), 'a returning patient is never re-registered');
        $this->assertNotSame($first['encounter_no'], $second['encounter_no']);

        // Reception's search finds them by phone or by number.
        $this->getJson('/api/hospital/patients?q=0712345678')->assertOk()->assertJsonPath('data.0.id', $patient['id']);
        $this->getJson('/api/hospital/patients?q='.$patient['patient_no'])->assertOk()->assertJsonPath('data.0.id', $patient['id']);
    }

    public function test_the_consultation_fee_is_charged_at_reception_and_settled_at_the_desk(): void
    {
        $patient = $this->registerPatient();
        $encounter = $this->openEncounter($patient['id']);

        $this->assertSame('WAITING', $encounter['status']);
        $this->assertSame('PENDING', $encounter['fee_status']);
        $charge = $encounter['charges'][0];
        $this->assertSame('CONSULTATION', $charge['charge_type']);
        $this->assertSame('500.0000', $charge['amount']);

        $this->postJson("/api/hospital/encounters/{$encounter['id']}/charges/{$charge['id']}/settle")
            ->assertOk()->assertJsonPath('status', 'PAID');
        $this->getJson("/api/hospital/encounters/{$encounter['id']}")->assertOk()->assertJsonPath('fee_status', 'PAID');
    }

    public function test_the_clinician_consults_diagnoses_and_completes_and_everything_is_audited(): void
    {
        $patient = $this->registerPatient();
        $encounter = $this->openEncounter($patient['id']);

        $this->postJson("/api/hospital/encounters/{$encounter['id']}/start-consultation")
            ->assertOk()->assertJsonPath('status', 'IN_CONSULTATION');

        $this->postJson("/api/hospital/encounters/{$encounter['id']}/consultation", [
            'chief_complaint' => 'Fever and headache for 3 days',
            'vital_signs' => ['temperature' => '38.4', 'bp' => '120/80'],
            'clinical_notes' => 'Suspect malaria.',
            'treatment_plan' => 'Await lab confirmation.',
        ])->assertCreated();

        $this->postJson("/api/hospital/encounters/{$encounter['id']}/diagnoses", [
            'diagnosis' => 'Malaria', 'diagnosis_type' => 'PROVISIONAL',
        ])->assertCreated();

        $this->postJson("/api/hospital/encounters/{$encounter['id']}/complete")
            ->assertOk()->assertJsonPath('status', 'COMPLETED');

        // A completed encounter cannot be completed (or cancelled) again.
        $this->postJson("/api/hospital/encounters/{$encounter['id']}/complete")
            ->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');

        $full = $this->getJson("/api/hospital/encounters/{$encounter['id']}")->assertOk()->json();
        $this->assertCount(1, $full['consultations']);
        $this->assertSame('Malaria', $full['diagnoses'][0]['diagnosis']);

        foreach (['PATIENT_REGISTERED', 'ENCOUNTER_CREATED', 'CONSULTATION_SAVED', 'DIAGNOSIS_RECORDED', 'ENCOUNTER_COMPLETED'] as $action) {
            $this->assertTrue(AuditLog::where('action', $action)->exists(), "missing audit action {$action}");
        }
    }

    public function test_every_stage_change_broadcasts_a_patient_flow_event(): void
    {
        Event::fake([PatientFlowUpdated::class]);

        $patient = $this->registerPatient();
        $encounter = $this->openEncounter($patient['id']);
        $this->postJson("/api/hospital/encounters/{$encounter['id']}/start-consultation")->assertOk();
        $this->postJson("/api/hospital/encounters/{$encounter['id']}/complete")->assertOk();

        // Opened (WAITING), consultation started, completed: three stages,
        // three broadcasts on the institution's healthcare channel.
        Event::assertDispatchedTimes(PatientFlowUpdated::class, 3);
        Event::assertDispatched(PatientFlowUpdated::class, fn (PatientFlowUpdated $e) => $e->kind === 'ENCOUNTER'
            && $e->payload['status'] === 'COMPLETED'
            && $e->broadcastOn()[0]->name === 'private-healthcare.'.$this->org->id);
    }

    public function test_a_referral_is_a_valid_way_out_of_a_consultation(): void
    {
        $patient = $this->registerPatient();
        $encounter = $this->openEncounter($patient['id']);
        $this->postJson("/api/hospital/encounters/{$encounter['id']}/start-consultation")->assertOk();

        $this->postJson("/api/hospital/encounters/{$encounter['id']}/referrals", [
            'referred_to' => 'Kenyatta National Hospital — Cardiology',
            'reason' => 'Suspected structural heart disease beyond this facility\'s level.',
        ])->assertCreated()->assertJsonPath('status', 'PENDING');

        $this->postJson("/api/hospital/encounters/{$encounter['id']}/complete")->assertOk();
    }
}
