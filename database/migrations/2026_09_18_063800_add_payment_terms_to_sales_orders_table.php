<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 10.4 — an order is either on the customer's account (credit-checked
 * against the limit) or paid on delivery (no credit is extended, so no
 * limit applies). A manager may also override the limit for one order; the
 * override is recorded on the order itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->enum('payment_terms', ['ACCOUNT', 'CASH_ON_DELIVERY'])->default('ACCOUNT')->after('required_date');
            $table->foreignId('credit_override_by')->nullable()->after('payment_terms')->constrained('users')->nullOnDelete();
            $table->string('credit_override_reason')->nullable()->after('credit_override_by');
            $table->timestamp('credit_override_at')->nullable()->after('credit_override_reason');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_override_by');
            $table->dropColumn(['payment_terms', 'credit_override_reason', 'credit_override_at']);
        });
    }
};
