<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            // scope = document type, e.g. "INVOICE", "GRN", "PO" — combined with
            // branch_id and fiscal_year this identifies one gapless counter.
            $table->string('scope');
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('prefix');
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->unsignedBigInteger('current_value')->default(0);
            $table->unsignedTinyInteger('padding')->default(6);
            $table->enum('reset_policy', ['ANNUAL', 'NEVER'])->default('ANNUAL');
            $table->timestamps();

            $table->unique(['organisation_id', 'scope', 'branch_id', 'fiscal_year'], 'number_sequences_unique_row');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
