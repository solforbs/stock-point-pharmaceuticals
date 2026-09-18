<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_discount_authorities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('role_id'); // Spatie roles.id (bigint, not scoped by team here)
            $table->decimal('max_line_discount_pct', 6, 3)->default(0);
            $table->decimal('max_header_discount_pct', 6, 3)->default(0);
            $table->boolean('may_override_floor')->default(false);
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->unique('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_discount_authorities');
    }
};
