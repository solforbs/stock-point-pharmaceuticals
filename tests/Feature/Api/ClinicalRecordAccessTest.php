<?php

namespace Tests\Feature\Api;

use App\Models\Facility;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsHospitalWorld;
use Tests\TestCase;

/**
 * Who may read a patient's clinical record: staff assigned to a facility see
 * only that facility's work, and only clinical staff see a visit's medical
 * content.
 */
class ClinicalRecordAccessTest extends TestCase
{
    use BuildsHospitalWorld;

    private const CLINICIAN = [
        'hospital.view', 'hospital.patient.view', 'hospital.patient.manage',
        'hospital.encounter.manage', 'hospital.consultation.manage', 'hospital.diagnosis.manage',
        'hospital.prescription.create', 'laboratory.order.create', 'laboratory.order.view',
        'laboratory.view', 'laboratory.order.accept',
    ];

    private const RECEPTION = ['hospital.view', 'hospital.patient.view', 'hospital.patient.manage', 'hospital.encounter.manage'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildHospitalWorld();
    }

    /**
     * A visit with a diagnosis, a lab order and a prescription, all recorded by a clinician.
     *
     * @return array{patient: array<string, mixed>, encounter: string, lab_order: string, prescription: string}
     */
    private function clinicalVisit(): array
    {
        $this->grantPermissions(self::CLINICIAN);
        Sanctum::actingAs($this->user);

        $patient = $this->registerPatient();
        $encounter = $this->openEncounter($patient['id'])['id'];
        $this->postJson("/api/hospital/encounters/{$encounter}/diagnoses", ['diagnosis' => 'Malaria'])->assertCreated();
        $labOrder = $this->postJson("/api/hospital/encounters/{$encounter}/lab-orders", ['facility_id' => $this->facility->id, 'test_ids' => [$this->malaria->id]])
            ->assertCreated()->json('id');
        $prescription = $this->postJson("/api/hospital/encounters/{$encounter}/prescriptions", ['lines' => [['medicine_name' => 'Artemether', 'quantity' => 6]]])
            ->assertCreated()->json('id');

        return ['patient' => $patient, 'encounter' => $encounter, 'lab_order' => $labOrder, 'prescription' => $prescription];
    }

    private function otherFacility(): Facility
    {
        return Facility::create([
            'organisation_id' => $this->org->id, 'code' => 'KSM', 'name' => 'Kisumu Clinic',
            'offers_hospital' => true, 'offers_laboratory' => true, 'offers_pharmacy' => false,
        ]);
    }

    public function test_staff_assigned_elsewhere_cannot_reach_another_facilitys_clinical_records(): void
    {
        $visit = $this->clinicalVisit();
        $elsewhere = $this->otherFacility();
        $elsewhere->staff()->attach($this->user->id, ['id' => (string) str()->uuid()]);

        $this->getJson('/api/hospital/encounters')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/hospital/encounters/{$visit['encounter']}")->assertNotFound();
        $this->postJson("/api/hospital/encounters/{$visit['encounter']}/diagnoses", ['diagnosis' => 'Typhoid'])->assertNotFound();
        $this->getJson('/api/laboratory/orders')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/laboratory/orders/{$visit['lab_order']}")->assertNotFound();
        $this->getJson("/api/hospital/lab-orders/{$visit['lab_order']}")->assertNotFound();
        $this->postJson("/api/laboratory/orders/{$visit['lab_order']}/accept")->assertNotFound();
        $this->getJson("/api/prescriptions/{$visit['prescription']}")->assertNotFound();
        $this->postJson("/api/prescriptions/{$visit['prescription']}/cancel")->assertNotFound();
        $this->postJson('/api/hospital/encounters', ['patient_id' => $visit['patient']['id'], 'facility_id' => $this->facility->id])
            ->assertForbidden()->assertJsonPath('error.code', 'FACILITY_ACCESS_DENIED');

        // The registry stays shared (one person, one record), without the other facility's visits.
        $this->getJson("/api/hospital/patients/{$visit['patient']['id']}")->assertOk()->assertJsonCount(0, 'encounters');
    }

    public function test_staff_assigned_to_the_facility_keep_their_access(): void
    {
        $visit = $this->clinicalVisit();
        $this->facility->staff()->attach($this->user->id, ['id' => (string) str()->uuid()]);

        $this->getJson('/api/hospital/encounters')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/hospital/encounters/{$visit['encounter']}")->assertOk();
        $this->getJson("/api/laboratory/orders/{$visit['lab_order']}")->assertOk();
        $this->getJson("/api/prescriptions/{$visit['prescription']}")->assertOk();
        $this->getJson("/api/hospital/patients/{$visit['patient']['id']}")->assertOk()->assertJsonCount(1, 'encounters');
    }

    public function test_an_ordering_clinician_still_reads_an_order_sent_to_an_outside_laboratory(): void
    {
        $this->grantPermissions(self::CLINICIAN);
        Sanctum::actingAs($this->user);
        $this->facility->staff()->attach($this->user->id, ['id' => (string) str()->uuid()]);
        $encounter = $this->openEncounter($this->registerPatient()['id'])['id'];

        $order = $this->postJson("/api/hospital/encounters/{$encounter}/lab-orders", ['facility_id' => $this->otherFacility()->id, 'test_ids' => [$this->malaria->id]])
            ->assertCreated()->json('id');

        $this->getJson("/api/hospital/lab-orders/{$order}")->assertOk();
    }

    public function test_reception_sees_the_visit_and_its_bill_but_not_the_medical_content(): void
    {
        $visit = $this->clinicalVisit();
        $this->revokeAllRoles();
        $this->grantPermissions(self::RECEPTION, 'Reception');

        $response = $this->getJson("/api/hospital/encounters/{$visit['encounter']}")
            ->assertOk()
            ->assertJsonPath('clinical_access', false)
            ->assertJsonMissingPath('diagnoses')
            ->assertJsonMissingPath('consultations')
            ->assertJsonMissingPath('referrals')
            ->assertJsonMissingPath('lab_orders')
            ->assertJsonMissingPath('prescriptions');
        $this->assertStringNotContainsString('Malaria', $response->getContent());

        $labCharge = collect($response->json('charges'))->firstWhere('charge_type', 'LAB_TEST');
        $this->assertSame('Laboratory test', $labCharge['description']);
        $this->assertSame('300.0000', $labCharge['amount']);

        $settled = $this->postJson("/api/hospital/encounters/{$visit['encounter']}/charges/{$labCharge['id']}/settle")
            ->assertOk()->assertJsonPath('status', 'PAID')->assertJsonPath('description', 'Laboratory test');
        $this->assertStringNotContainsString('Malaria', $settled->getContent());
        $this->assertDatabaseHas('encounter_charges', ['id' => $labCharge['id'], 'description' => 'Malaria Parasite Slide']);
    }

    public function test_clinical_staff_see_the_medical_content(): void
    {
        $visit = $this->clinicalVisit();

        $this->getJson("/api/hospital/encounters/{$visit['encounter']}")
            ->assertOk()
            ->assertJsonPath('clinical_access', true)
            ->assertJsonPath('diagnoses.0.diagnosis', 'Malaria')
            ->assertJsonCount(1, 'lab_orders')
            ->assertJsonCount(1, 'prescriptions');
    }
}
