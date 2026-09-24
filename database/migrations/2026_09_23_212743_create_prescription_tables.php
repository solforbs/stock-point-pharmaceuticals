<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Healthcare platform — prescriptions, the bridge from the clinical side to
 * the EXISTING pharmacy. A prescription line references the pharmacy's own
 * products table; dispensing runs through the existing checkout engine
 * (FEFO allocation, stock ledger, sale, journal), so the hospital module
 * owns no inventory of its own. The resulting Sale is linked back here.
 *
 * A line may instead carry free text (product_id NULL) for a medicine the
 * pharmacy does not stock — the patient takes that line elsewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('rx_no', 30); // RX-YYYY-000001
            $table->foreignUuid('encounter_id')->constrained('encounters')->restrictOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            // The pharmacy branch this was sent to. Nullable: a hospital with
            // no pharmacy prints the prescription for an outside chemist.
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('prescribed_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('PENDING'); // PENDING | DISPENSED | CANCELLED
            $table->text('notes')->nullable();
            $table->foreignUuid('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('dispensed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dispensed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'rx_no']);
            $table->index(['branch_id', 'status']);
            $table->index('encounter_id');
        });

        Schema::create('prescription_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->restrictOnDelete();
            // The selling unit, as checkout knows it (units_of_measure, the
            // same id sale lines carry — not the product_uoms pivot row).
            $table->foreignUuid('uom_id')->nullable()->constrained('units_of_measure')->restrictOnDelete();
            // Always snapshotted, so the prescription reads the same even if
            // the product is later renamed or retired.
            $table->string('medicine_name', 300);
            $table->decimal('quantity', 18, 4);
            $table->string('frequency', 100)->nullable(); // e.g. 3 times daily
            $table->string('duration', 100)->nullable();  // e.g. 7 days
            $table->string('instructions', 500)->nullable();
            $table->decimal('dispensed_qty', 18, 4)->default(0);
            $table->timestamps();
            $table->index('prescription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_lines');
        Schema::dropIfExists('prescriptions');
    }
};
