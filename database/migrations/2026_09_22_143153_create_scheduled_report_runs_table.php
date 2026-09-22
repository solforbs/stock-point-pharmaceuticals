<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 20.3 — every scheduled-report run (on schedule or "Run now") is
 * archived with its CSV so someone can open it in the app, proof-read it
 * and mark it verified or flagged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_report_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('scheduled_report_id')->nullable()->constrained('scheduled_reports')->nullOnDelete();
            $table->string('report_key');
            $table->string('report_title');
            $table->date('period_from');
            $table->date('period_to');
            $table->string('trigger', 20); // SCHEDULED | MANUAL
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->index();
            $table->unsignedInteger('row_count')->nullable();
            $table->json('columns_json')->nullable();
            $table->json('totals_json')->nullable();
            $table->string('csv_path')->nullable(); // on the local (private) disk
            $table->string('csv_filename')->nullable();
            $table->json('emailed_to')->nullable();
            $table->string('status', 20); // SUCCESS | FAILED
            $table->text('error')->nullable();
            $table->string('review_status', 20)->default('UNREVIEWED')->index(); // UNREVIEWED | VERIFIED | FLAGGED
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'branch_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_report_runs');
    }
};
