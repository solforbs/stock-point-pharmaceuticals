<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_conditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name'); // Room Temperature, Cold Chain 2-8C, Frozen, ...
            $table->decimal('min_temp_c', 5, 2)->nullable();
            $table->decimal('max_temp_c', 5, 2)->nullable();
            $table->unsignedInteger('max_excursion_minutes')->nullable();
            $table->boolean('requires_cold_chain')->default(false); // Part 8.5 cold chain
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_conditions');
    }
};
