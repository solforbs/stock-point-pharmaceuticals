<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 16.4 (V6) — SOPs and controlled documents: versioned files with
 * per-version acknowledgement tracking an inspector can print.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controlled_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('title');
            $table->enum('category', ['SOP', 'POLICY', 'FORM', 'WORK_INSTRUCTION', 'OTHER']);
            $table->uuid('current_version_id')->nullable();
            $table->date('review_due_date')->nullable();
            $table->enum('status', ['DRAFT', 'ACTIVE', 'RETIRED'])->default('DRAFT');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organisation_id', 'code']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('controlled_document_id')->constrained('controlled_documents')->cascadeOnDelete();
            $table->string('version', 20);
            $table->string('file_path');
            $table->string('file_name');
            $table->text('change_summary')->nullable();
            $table->date('effective_date');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['controlled_document_id', 'version']);
        });

        Schema::table('controlled_documents', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('document_versions')->nullOnDelete();
        });

        Schema::create('document_acknowledgements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_version_id')->constrained('document_versions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('acknowledged_at');
            $table->timestamps();
            $table->unique(['document_version_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('controlled_documents', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('document_acknowledgements');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('controlled_documents');
    }
};
