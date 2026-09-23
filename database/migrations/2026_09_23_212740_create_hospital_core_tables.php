<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Healthcare platform — hospital core structure.
 *
 * A Facility is the umbrella for the three service modules (Hospital,
 * Laboratory, Pharmacy). A facility can offer any combination of the three,
 * so an independent laboratory or an independent pharmacy is just a facility
 * with a single service flag. A facility's pharmacy service points at an
 * existing Branch, because the running pharmacy (stock, sales, dispensing)
 * is branch-based — the hospital module never grows its own inventory.
 *
 * Hospital levels (Level 1–6, Referral, …) are a configurable catalogue
 * rather than a column enum: classifications change by jurisdiction.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Platform-maintained catalogue (organisation_id NULL) that a tenant
        // may extend with its own rows — same pattern as product_categories.
        Schema::create('hospital_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->nullable()->constrained('organisations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('rank')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organisation_id', 'name']);
        });

        Schema::create('facilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->foreignUuid('hospital_level_id')->nullable()->constrained('hospital_levels')->nullOnDelete();
            // Which services this facility provides. None is forced: a pure
            // diagnostic centre is laboratory-only, a chemist pharmacy-only.
            $table->boolean('offers_hospital')->default(false);
            $table->boolean('offers_laboratory')->default(false);
            $table->boolean('offers_pharmacy')->default(false);
            // The existing pharmacy branch that dispenses for this facility.
            // Nullable: a hospital without a pharmacy refers patients out.
            $table->foreignUuid('pharmacy_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organisation_id', 'code']);
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['facility_id', 'name']);
        });

        // Staff assignment doubles as facility-level access control: a user
        // assigned to Facility A does not see Facility B's patients' clinical
        // work unless also assigned there (admins with hospital.manage see all).
        Schema::create('facility_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('job_title', 100)->nullable();
            $table->timestamps();
            $table->unique(['facility_id', 'user_id']);
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            // One person, one record — repeat visits are new encounters, never
            // new patients. PAT-YYYY-000001 via number_sequences.
            $table->string('patient_no', 30);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('sex', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('next_of_kin_name', 150)->nullable();
            $table->string('next_of_kin_phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['organisation_id', 'patient_no']);
            $table->index(['organisation_id', 'last_name']);
            $table->index(['organisation_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
        Schema::dropIfExists('facility_user');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('hospital_levels');
    }
};
