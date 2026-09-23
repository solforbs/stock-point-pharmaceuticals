<?php

namespace Tests\Support;

use App\Models\Department;
use App\Models\Facility;
use App\Models\HospitalLevel;
use App\Models\LabTest;
use App\Models\LabTestCategory;

/**
 * Layers the hospital/laboratory world on top of BuildsBlueprintWorld: one
 * facility offering all three services (its pharmacy is the blueprint
 * branch), an outpatient department, and a small orderable test catalogue.
 */
trait BuildsHospitalWorld
{
    use BuildsBlueprintWorld;

    protected Facility $facility;

    protected Department $outpatient;

    protected LabTest $malaria;

    protected LabTest $bloodSugar;

    protected function buildHospitalWorld(): void
    {
        $this->buildWorld();

        $level = HospitalLevel::create(['name' => 'Level 3', 'rank' => 3, 'organisation_id' => null]);

        $this->facility = Facility::create([
            'organisation_id' => $this->org->id,
            'code' => 'NMC',
            'name' => 'Nairobi Medical Centre',
            'hospital_level_id' => $level->id,
            'offers_hospital' => true,
            'offers_laboratory' => true,
            'offers_pharmacy' => true,
            'pharmacy_branch_id' => $this->branch->id,
        ]);

        $this->outpatient = Department::create([
            'organisation_id' => $this->org->id,
            'facility_id' => $this->facility->id,
            'name' => 'Outpatient',
        ]);

        $hematology = LabTestCategory::create(['name' => 'Hematology', 'organisation_id' => null]);
        $biochemistry = LabTestCategory::create(['name' => 'Biochemistry', 'organisation_id' => null]);

        $this->malaria = LabTest::create([
            'organisation_id' => $this->org->id,
            'category_id' => $hematology->id,
            'code' => 'MPS',
            'name' => 'Malaria Parasite Slide',
            'price' => '300.0000',
            'sample_type' => 'Blood',
            'normal_range' => 'Negative',
        ]);

        $this->bloodSugar = LabTest::create([
            'organisation_id' => $this->org->id,
            'category_id' => $biochemistry->id,
            'code' => 'RBS',
            'name' => 'Random Blood Sugar',
            'price' => '250.0000',
            'sample_type' => 'Blood',
            'normal_range' => '3.9 - 7.8',
            'unit' => 'mmol/L',
        ]);
    }

    /** Registers a patient over the API and returns the response array. */
    protected function registerPatient(array $overrides = []): array
    {
        return $this->postJson('/api/hospital/patients', array_merge([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'sex' => 'MALE',
            'date_of_birth' => '1990-05-14',
            'phone' => '0712345678',
        ], $overrides))->assertCreated()->json();
    }

    /** Opens an encounter for a patient over the API. */
    protected function openEncounter(string $patientId, array $overrides = []): array
    {
        return $this->postJson('/api/hospital/encounters', array_merge([
            'patient_id' => $patientId,
            'facility_id' => $this->facility->id,
            'department_id' => $this->outpatient->id,
            'consultation_fee' => '500',
        ], $overrides))->assertCreated()->json();
    }
}
