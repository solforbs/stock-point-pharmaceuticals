<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('doc_number')->unique();
            $table->date('entry_date');
            $table->foreignUuid('period_id')->constrained('financial_periods')->restrictOnDelete();
            $table->string('source_doc_type')->nullable();
            $table->uuid('source_doc_id')->nullable();
            $table->string('narration');
            $table->foreignUuid('reverses_journal_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->useCurrent();

            $table->index(['source_doc_type', 'source_doc_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
