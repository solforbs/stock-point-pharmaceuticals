<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client feedback 2026-09-30 (finance questions):
 *  - our own bank accounts, and who paid us (payer bank / account) on a receipt;
 *  - a customer's bank and M-PESA details, kept on the customer;
 *  - petty cash: a float per branch and the vouchers spent from it;
 *  - non-pharmaceutical supplies (cleaning, stationery): requested, approved,
 *    then bought and expensed — never stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('bank_name', 100);
            $table->string('account_number', 60);
            $table->string('account_name', 150)->nullable();
            $table->string('branch_name', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organisation_id', 'bank_name', 'account_number']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignUuid('bank_account_id')->nullable()->after('reference')->constrained('bank_accounts')->nullOnDelete();
            $table->string('payer_name', 150)->nullable()->after('bank_account_id');
            $table->string('payer_bank', 100)->nullable()->after('payer_name');
            $table->string('payer_account', 60)->nullable()->after('payer_bank');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_name', 150)->nullable();
            $table->string('bank_account_number', 60)->nullable();
            $table->string('mpesa_phone', 30)->nullable();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('petty_cash_float', 18, 4)->default(0);
        });

        Schema::create('petty_cash_vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('doc_number', 40);
            $table->enum('voucher_type', ['TOPUP', 'EXPENSE']);
            $table->date('voucher_date');
            // EXPENSE: the expense account charged. TOPUP: null (funded from cash or bank).
            $table->foreignUuid('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->enum('funding_source', ['CASH', 'BANK'])->nullable();
            $table->decimal('amount', 18, 4);
            $table->string('payee', 150)->nullable();
            $table->string('description', 255);
            $table->string('receipt_ref', 100)->nullable();
            $table->enum('status', ['POSTED', 'VOID'])->default('POSTED');
            $table->foreignUuid('journal_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'doc_number']);
            $table->index(['branch_id', 'voucher_date']);
        });

        Schema::create('supply_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('doc_number', 40);
            $table->enum('status', ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'PURCHASED', 'REJECTED'])->default('DRAFT');
            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name', 150)->nullable();
            $table->string('paid_from', 20)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->foreignUuid('journal_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('purchased_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reject_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'doc_number']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('supply_request_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supply_request_id')->constrained('supply_requests')->cascadeOnDelete();
            $table->string('item', 150);
            $table->string('category', 60)->default('OTHER');
            $table->decimal('qty', 12, 3);
            $table->string('unit', 30)->default('pcs');
            $table->decimal('est_unit_cost', 18, 4)->default(0);
            $table->decimal('actual_unit_cost', 18, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_request_lines');
        Schema::dropIfExists('supply_requests');
        Schema::dropIfExists('petty_cash_vouchers');
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('petty_cash_float'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['bank_name', 'bank_account_name', 'bank_account_number', 'mpesa_phone']));
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropColumn(['payer_name', 'payer_bank', 'payer_account']);
        });
        Schema::dropIfExists('bank_accounts');
    }
};
