<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->enum('customer_type', [
                'WALK_IN', 'RETAIL_PHARMACY', 'HOSPITAL', 'CLINIC',
                'NGO', 'GOVERNMENT', 'TENDER', 'INSTITUTION',
            ])->default('WALK_IN');
            $table->foreignUuid('tier_id')->nullable()->constrained('customer_tiers')->nullOnDelete();
            $table->string('tax_status')->default('STANDARD');
            $table->string('exemption_ref')->nullable();
            $table->date('exemption_expiry')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->string('fulfilment_policy')->default('PARTIAL');
            // price_list_id -> price_lists, added as a follow-up column once
            // the Pricing Engine phase creates that table.
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['organisation_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
