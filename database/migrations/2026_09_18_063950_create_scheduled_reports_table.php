<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 20.3 — a catalogue report delivered by email on a schedule. It runs
 * with its creator's permissions, so a schedule can never mail a report
 * its owner could not open on screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('report_key');
            $table->json('filters_json')->nullable();
            $table->enum('frequency', ['DAILY', 'WEEKLY', 'MONTHLY']);
            $table->string('run_at', 5); // HH:MM, application timezone
            $table->unsignedTinyInteger('weekday')->nullable(); // 1 = Monday … 7 = Sunday (WEEKLY)
            $table->unsignedTinyInteger('month_day')->nullable(); // 1–28 (MONTHLY)
            $table->json('recipients');
            $table->boolean('is_active')->default(true);
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 20)->nullable(); // SENT | FAILED
            $table->text('last_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};
