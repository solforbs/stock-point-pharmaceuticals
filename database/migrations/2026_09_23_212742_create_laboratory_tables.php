<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Healthcare platform — laboratory module.
 *
 * Test categories and the test catalogue are configuration, never code:
 * an administrator maintains them. A lab order moves through a guarded
 * status chain (PENDING → ACCEPTED → SAMPLE_COLLECTED → PROCESSING →
 * COMPLETED, CANCELLED from any pre-completed state) with the actor and
 * time recorded at every step; results live on the order's test lines so
 * each test carries its own value against its snapshotted normal range.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Hematology, Biochemistry, … — platform-seeded (organisation_id
        // NULL), tenant-extensible: the shared-catalogue pattern.
        Schema::create('lab_test_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->nullable()->constrained('organisations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organisation_id', 'name']);
        });

        Schema::create('lab_tests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('lab_test_categories')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->decimal('price', 18, 4)->default(0);
            $table->string('sample_type', 50)->nullable(); // Blood, Urine, Stool, Swab …
            $table->string('description', 500)->nullable();
            $table->string('normal_range', 200)->nullable();
            $table->string('unit', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organisation_id', 'code']);
        });

        Schema::create('lab_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('order_no', 30); // LAB-YYYY-000001
            $table->foreignUuid('encounter_id')->constrained('encounters')->restrictOnDelete();
            // Denormalised so the lab bench never joins through encounters.
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            // The performing laboratory facility.
            $table->foreignUuid('facility_id')->constrained('facilities')->restrictOnDelete();
            $table->foreignId('ordered_by')->constrained('users')->restrictOnDelete();
            $table->text('clinical_notes')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('accepted_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 300)->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'order_no']);
            $table->index(['facility_id', 'status']);
            $table->index('encounter_id');
        });

        Schema::create('lab_order_tests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->foreignUuid('lab_test_id')->constrained('lab_tests')->restrictOnDelete();
            // Snapshots: the catalogue may be re-priced or edited later, but
            // this order keeps what was true when it was placed.
            $table->string('test_name', 200);
            $table->decimal('price', 18, 4)->default(0);
            $table->string('normal_range', 200)->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('result_value', 300)->nullable();
            $table->text('result_notes')->nullable();
            $table->boolean('is_abnormal')->nullable();
            $table->foreignId('result_entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('result_entered_at')->nullable();
            $table->timestamps();
            $table->index('lab_order_id');
        });

        Schema::create('lab_samples', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->string('sample_no', 30); // SMP-YYYY-000001
            $table->string('sample_type', 50);
            $table->foreignId('collected_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('collected_at');
            $table->string('condition_notes', 300)->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'sample_no']);
            $table->index('lab_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_samples');
        Schema::dropIfExists('lab_order_tests');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('lab_tests');
        Schema::dropIfExists('lab_test_categories');
    }
};
