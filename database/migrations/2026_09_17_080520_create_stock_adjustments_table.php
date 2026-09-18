<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('doc_number')->unique();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            // Part 7.8: controlled reason list — unrestricted adjustment is
            // how inventory fraud happens.
            $table->enum('reason_code', [
                'BREAKAGE', 'THEFT', 'EXPIRY', 'SAMPLING',
                'CORRECTION_OF_ERROR', 'DONATION', 'COLD_CHAIN_LOSS',
            ]);
            $table->enum('approval_status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_value', 18, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
