<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nothing is hardcoded (Part 13.1) — rates are effective-dated
        // configuration, never a constant in code.
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tax_code_id')->constrained('tax_codes')->cascadeOnDelete();
            $table->decimal('rate_pct', 6, 3);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['tax_code_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
