<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The platform around the tenants: an institution asks for a quote, the
 * platform approves it and emails a one-time registration link, the
 * institution registers into a seven-day trial, and a Paystack
 * subscription keeps it active afterwards. A lapsed institution keeps
 * read-only access until it pays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            // TRIAL → ACTIVE (paying) → LAPSED; SUSPENDED is the platform's stop.
            $table->string('subscription_status', 20)->default('ACTIVE')->after('is_active');
            // Never billed: the platform owner's own institution, partners, pilots.
            $table->boolean('is_complimentary')->default(false)->after('subscription_status');
            $table->timestamp('trial_ends_at')->nullable()->after('is_complimentary');
            $table->timestamp('suspended_at')->nullable()->after('trial_ends_at');
            $table->string('suspension_reason')->nullable()->after('suspended_at');
            $table->string('contact_email')->nullable()->after('suspension_reason');
            $table->string('contact_phone', 30)->nullable()->after('contact_email');
        });

        // The institutions already here were never on a trial.
        DB::table('organisations')->update(['subscription_status' => 'ACTIVE', 'is_complimentary' => true]);

        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('currency', 3)->default('KES');
            $table->decimal('price_monthly', 12, 2);
            $table->decimal('price_yearly', 12, 2)->nullable();
            $table->unsignedSmallInteger('max_branches')->nullable(); // NULL = unlimited
            $table->unsignedSmallInteger('max_users')->nullable();
            $table->json('features')->nullable();
            // Paystack plan codes make the payment recur automatically.
            $table->string('paystack_plan_monthly')->nullable();
            $table->string('paystack_plan_yearly')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('plans');
            $table->enum('billing_interval', ['MONTHLY', 'YEARLY']);
            $table->enum('status', ['PENDING', 'ACTIVE', 'CANCELLED', 'EXPIRED'])->default('PENDING');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->string('paystack_customer_code')->nullable()->index();
            $table->string('paystack_subscription_code')->nullable()->unique();
            $table->string('paystack_email_token')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'status']);
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignUuid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->enum('billing_interval', ['MONTHLY', 'YEARLY'])->nullable();
            $table->string('reference')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('KES');
            $table->enum('status', ['PENDING', 'SUCCESS', 'FAILED'])->default('PENDING');
            $table->string('channel')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('gateway_response')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'created_at']);
        });

        Schema::create('tenant_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('institution_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('town')->nullable();
            $table->unsignedSmallInteger('branches_count')->nullable();
            $table->unsignedSmallInteger('users_count')->nullable();
            $table->foreignUuid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->text('message')->nullable();
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'REGISTERED'])->default('PENDING');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->foreignUuid('organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // REGISTER: an approved prospect sets up their institution.
            // ACTIVATE: the admin of an institution the platform created sets a password.
            $table->enum('purpose', ['REGISTER', 'ACTIVATE']);
            $table->string('email');
            $table->foreignUuid('tenant_request_id')->nullable()->constrained('tenant_requests')->nullOnDelete();
            $table->foreignUuid('organisation_id')->nullable()->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            // Only hashes are stored: the link token and, once opened, the form session.
            $table->string('token_hash', 64)->unique();
            $table->string('session_hash', 64)->nullable()->unique();
            $table->timestamp('expires_at');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('session_expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
        Schema::dropIfExists('tenant_requests');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn(['subscription_status', 'is_complimentary', 'trial_ends_at', 'suspended_at', 'suspension_reason', 'contact_email', 'contact_phone']);
        });
    }
};
