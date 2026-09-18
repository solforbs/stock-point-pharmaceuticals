<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            // default_price_list_id references price_lists, created in the
            // Pricing Engine phase — added there as a follow-up column.
            $table->decimal('default_discount_pct', 6, 3)->default(0);
            $table->decimal('max_discount_pct', 6, 3)->default(0);
            $table->unsignedSmallInteger('credit_terms_days')->default(0);
            $table->timestamps();

            $table->unique(['organisation_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tiers');
    }
};
