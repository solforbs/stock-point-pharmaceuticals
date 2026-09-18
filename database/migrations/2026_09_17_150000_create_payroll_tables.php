<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 21.16 — payroll. Statutory deductions live in effective-dated
 * bracket tables; every run stamps the band version it used so an old
 * payroll re-runs to exactly the same figures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_no')->unique();
            $table->string('name');
            $table->string('national_id')->nullable();
            $table->string('kra_pin')->nullable();
            $table->string('nssf_no')->nullable();
            $table->string('shif_no')->nullable();
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->enum('employment_type', ['PERMANENT', 'CONTRACT', 'CASUAL'])->default('PERMANENT');
            $table->date('date_joined')->nullable();
            $table->date('date_left')->nullable();
            $table->decimal('basic_salary', 18, 4)->default(0);
            $table->decimal('regular_allowances', 18, 4)->default(0);
            $table->decimal('pension_contribution', 18, 4)->default(0);
            $table->text('bank_name')->nullable();
            $table->text('bank_account')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_bands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->nullable()->constrained('organisations')->cascadeOnDelete();
            $table->string('band_type')->index(); // PAYE, PAYE_RELIEF, NSSF, SHIF, HOUSING_LEVY, PENSION_RELIEF_CAP
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('lower', 18, 4)->default(0);
            $table->decimal('upper', 18, 4)->nullable();
            $table->decimal('rate_pct', 8, 4)->default(0);
            $table->decimal('fixed_amount', 18, 4)->nullable();
            $table->json('meta_json')->nullable();
            $table->string('source')->nullable(); // the statute or notice the band comes from
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('doc_number')->unique();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->enum('status', ['DRAFT', 'COMPUTED', 'APPROVED', 'POSTED', 'PAID'])->default('DRAFT')->index();
            $table->json('bands_snapshot_json')->nullable();
            $table->date('bands_as_of')->nullable();
            $table->decimal('total_gross', 18, 4)->default(0);
            $table->decimal('total_paye', 18, 4)->default(0);
            $table->decimal('total_nssf_employee', 18, 4)->default(0);
            $table->decimal('total_nssf_employer', 18, 4)->default(0);
            $table->decimal('total_shif', 18, 4)->default(0);
            $table->decimal('total_housing_levy_employee', 18, 4)->default(0);
            $table->decimal('total_housing_levy_employer', 18, 4)->default(0);
            $table->decimal('total_other_deductions', 18, 4)->default(0);
            $table->decimal('total_net', 18, 4)->default(0);
            $table->uuid('journal_id')->nullable();
            $table->uuid('payment_journal_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('computed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'period_year', 'period_month']);
        });

        Schema::create('payroll_run_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('basic', 18, 4)->default(0);
            $table->decimal('allowances', 18, 4)->default(0);
            $table->decimal('overtime', 18, 4)->default(0);
            $table->decimal('gross', 18, 4)->default(0);
            $table->decimal('pension_contribution', 18, 4)->default(0);
            $table->decimal('taxable', 18, 4)->default(0);
            $table->decimal('paye', 18, 4)->default(0);
            $table->decimal('nssf_employee', 18, 4)->default(0);
            $table->decimal('nssf_employer', 18, 4)->default(0);
            $table->decimal('shif', 18, 4)->default(0);
            $table->decimal('housing_levy_employee', 18, 4)->default(0);
            $table->decimal('housing_levy_employer', 18, 4)->default(0);
            $table->decimal('other_deductions', 18, 4)->default(0);
            $table->decimal('net', 18, 4)->default(0);
            $table->json('breakdown_json')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_lines');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_bands');
        Schema::dropIfExists('employees');
    }
};
