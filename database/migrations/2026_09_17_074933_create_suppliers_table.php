<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('licence_number')->nullable();
            $table->date('licence_expiry')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->unsignedSmallInteger('lead_time_days')->default(14);
            $table->string('currency', 3)->default('KES');
            // Encrypted at rest via the model's cast (Part 19.2 flags bank
            // detail changes as a classic fraud vector to audit closely).
            $table->text('bank_name')->nullable();
            $table->text('bank_account')->nullable();
            $table->enum('status', ['ACTIVE', 'SUSPENDED', 'BLACKLISTED'])->default('ACTIVE');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['organisation_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
