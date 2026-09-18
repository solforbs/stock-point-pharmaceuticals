<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->enum('store_type', ['MAIN', 'COLD', 'QUARANTINE', 'RETAIL', 'TRANSIT', 'DISPENSARY']);
            // storage_condition_id (FK) is added once the storage_conditions
            // lookup table exists in the Master Data phase.
            $table->boolean('is_sellable')->default(true);
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['branch_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
