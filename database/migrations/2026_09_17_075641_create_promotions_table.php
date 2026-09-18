<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->enum('promo_type', ['PRICE_OVERRIDE', 'PERCENT_OFF', 'BUY_X_GET_Y'])->default('PERCENT_OFF');
            $table->date('effective_from');
            $table->date('effective_to');
            $table->string('customer_scope')->nullable(); // null = all customers
            $table->foreignUuid('branch_scope')->nullable()->constrained('branches')->nullOnDelete();
            // Part 0.4 bonus-scheme note: mark who actually pays for this.
            $table->enum('funded_by', ['SUPPLIER', 'US'])->default('US');
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organisation_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
