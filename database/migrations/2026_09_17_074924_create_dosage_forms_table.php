<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dosage_forms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name'); // Tablet, Capsule, Syrup, Injection, Cream, ...
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dosage_forms');
    }
};
