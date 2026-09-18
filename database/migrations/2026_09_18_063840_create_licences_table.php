<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 16.3 (V6) — licence and certificate register. Licences are archived,
 * never deleted: an inspector may ask what was held on any past date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->enum('holder_type', ['ORGANISATION', 'BRANCH', 'EMPLOYEE', 'SUPPLIER']);
            $table->string('holder_id', 64)->nullable();
            $table->enum('licence_type', ['PPB_PREMISES', 'PPB_PHARMACIST', 'PPB_PHARMTECH', 'BUSINESS_PERMIT', 'FIRE', 'PUBLIC_HEALTH', 'KRA_TCC', 'NHIF_SHIF', 'OTHER']);
            $table->string('licence_number', 100);
            $table->string('issued_by', 150)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date');
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organisation_id', 'expiry_date']);
            $table->index(['holder_type', 'holder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licences');
    }
};
