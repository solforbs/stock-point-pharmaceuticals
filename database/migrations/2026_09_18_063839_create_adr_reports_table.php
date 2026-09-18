<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 11.4 — pharmacovigilance. An adverse drug reaction linked to a batch
 * is what turns an isolated complaint into a detectable pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adr_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('doc_number')->unique();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products');
            $table->foreignUuid('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('patient_initials', 10);
            $table->unsignedSmallInteger('patient_age')->nullable();
            $table->enum('patient_sex', ['M', 'F', 'U'])->nullable();
            $table->text('reaction_description');
            $table->date('onset_date');
            $table->enum('seriousness', ['NON_SERIOUS', 'SERIOUS', 'LIFE_THREATENING', 'FATAL']);
            $table->enum('outcome', ['RECOVERED', 'RECOVERING', 'NOT_RECOVERED', 'UNKNOWN', 'FATAL']);
            $table->text('action_taken')->nullable();
            $table->string('reporter_name');
            $table->text('investigation_notes')->nullable();
            $table->enum('status', ['DRAFT', 'SUBMITTED', 'CLOSED'])->default('DRAFT');
            $table->string('ppb_reference', 100)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adr_reports');
    }
};
