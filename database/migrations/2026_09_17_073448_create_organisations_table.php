<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('kra_pin')->nullable();
            $table->string('vat_number')->nullable();
            $table->boolean('vat_registered')->default(false);
            $table->string('base_currency', 3)->default('KES');
            $table->unsignedTinyInteger('fiscal_year_start')->default(1); // month 1-12
            $table->char('country_code', 2)->default('KE');
            $table->string('timezone')->default('Africa/Nairobi');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
