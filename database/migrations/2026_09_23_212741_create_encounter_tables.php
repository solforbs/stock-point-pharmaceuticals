<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Healthcare platform — the encounter (visit) and everything that hangs off
 * it. The encounter is the clinical spine: consultation notes, diagnoses,
 * referrals, lab orders, prescriptions and charges all attach to one
 * encounter, and an encounter belongs to exactly one patient and facility.
 *
 * The workflow is deliberately loose: a visit may be consultation-only,
 * consultation → lab → consultation, or consultation → pharmacy. Nothing
 * here forces a lab step or a prescription.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('encounter_no', 30); // ENC-YYYY-000001
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUuid('facility_id')->constrained('facilities')->restrictOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('encounter_type', 20)->default('OUTPATIENT');
            // REGISTERED → WAITING → IN_CONSULTATION → AWAITING_RESULTS →
            // COMPLETED, or CANCELLED from any open state.
            $table->string('status', 20)->default('REGISTERED');
            $table->foreignId('attending_clinician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('consultation_fee', 18, 4)->default(0);
            $table->string('fee_status', 20)->default('PENDING'); // PENDING | PAID | WAIVED
            $table->text('presenting_notes')->nullable(); // reception's note of why the patient came
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            // dateTime not timestamp: MySQL timestamps stop at 2038 and MariaDB
            // auto-updates the first timestamp column.
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'encounter_no']);
            $table->index(['facility_id', 'status']);
            $table->index(['patient_id', 'started_at']);
        });

        Schema::create('consultations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('clinician_id')->constrained('users')->restrictOnDelete();
            $table->text('chief_complaint')->nullable();
            $table->text('history')->nullable(); // history of presenting illness
            $table->text('symptoms')->nullable();
            // {"temperature":"37.2","bp":"120/80","pulse":"72","weight":"64", ...}
            $table->json('vital_signs')->nullable();
            $table->text('examination')->nullable();
            $table->text('clinical_notes')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->text('follow_up')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index('encounter_id');
        });

        Schema::create('diagnoses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->string('diagnosis', 500);
            $table->string('icd_code', 20)->nullable();
            $table->string('diagnosis_type', 20)->default('PROVISIONAL'); // PROVISIONAL | FINAL
            $table->foreignId('diagnosed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index('encounter_id');
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->string('referred_to', 300); // free text: facility/specialist name
            $table->text('reason');
            $table->string('status', 20)->default('PENDING'); // PENDING | COMPLETED | CANCELLED
            $table->foreignId('referred_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index('encounter_id');
        });

        // Visit-level billing lines (consultation fee, lab tests, procedures).
        // Medicine charges stay in the pharmacy's own sales tables — a
        // dispensed prescription becomes a Sale, not an encounter charge.
        Schema::create('encounter_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->string('charge_type', 30); // CONSULTATION | LAB_TEST | PROCEDURE | OTHER
            $table->string('description', 300);
            $table->decimal('amount', 18, 4);
            $table->string('status', 20)->default('PENDING'); // PENDING | PAID | WAIVED
            // Polymorphic-lite pointer to what generated the charge (lab order …).
            $table->string('source_type', 40)->nullable();
            $table->uuid('source_id')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['encounter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_charges');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('diagnoses');
        Schema::dropIfExists('consultations');
        Schema::dropIfExists('encounters');
    }
};
