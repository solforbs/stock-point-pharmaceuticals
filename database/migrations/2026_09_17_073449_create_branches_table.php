<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('county')->nullable();
            $table->boolean('is_active')->default(true);
            // V6 simplifies these to plain per-branch flags (no org-level
            // default + nullable override).
            $table->boolean('retail_enabled')->default(false);
            $table->boolean('wholesale_enabled')->default(true);
            $table->boolean('dispensing_enabled')->default(false);
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
