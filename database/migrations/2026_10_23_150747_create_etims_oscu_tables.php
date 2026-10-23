<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KRA eTIMS through an Online Sales Control Unit (OSCU). Each branch that
 * files with KRA is one OSCU device: KRA approves a device serial for the
 * taxpayer's PIN and branch, and initialising it returns the communication
 * key every later call carries. The trader invoice number KRA expects is a
 * plain counter per device, kept here.
 *
 * Every call to KRA is kept as a transmission: what was sent, what came
 * back, and for an invoice the receipt signature and number that print on
 * the receipt and build its QR link.
 *
 * KRA's code lists (tax types, units, payment types, refund reasons…) and
 * item classes are national, so they are shared by every institution.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etims_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('environment', 12)->default('sandbox'); // sandbox | production
            $table->string('tin', 20);           // the taxpayer's KRA PIN
            $table->string('bhf_id', 5)->default('00'); // KRA branch id; 00 = head office
            $table->string('device_serial', 100);
            $table->text('cmc_key')->nullable(); // encrypted; issued when the device is initialised
            $table->string('status', 20)->default('PENDING'); // PENDING | ACTIVE | FAILED
            $table->string('last_error', 500)->nullable();
            $table->unsignedBigInteger('last_invoice_no')->default(0);
            $table->dateTime('initialised_at')->nullable();
            $table->timestamps();
            $table->unique('branch_id');
        });

        Schema::create('etims_transmissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('etims_device_id')->constrained('etims_devices')->cascadeOnDelete();
            // sale | credit_note | item | init
            $table->string('document_type', 20);
            $table->uuid('document_id')->nullable();
            $table->string('endpoint', 60);
            $table->unsignedBigInteger('invoice_no')->nullable(); // trader invoice number, for sales and credit notes
            $table->string('status', 12)->default('PENDING');     // PENDING | SUCCESS | FAILED
            $table->string('result_code', 10)->nullable();
            $table->string('result_message', 500)->nullable();
            $table->json('request_json')->nullable();
            $table->json('response_json')->nullable();
            $table->unsignedBigInteger('receipt_no')->nullable();       // curRcptNo
            $table->unsignedBigInteger('total_receipt_no')->nullable(); // totRcptNo
            $table->string('receipt_signature', 100)->nullable();       // rcptSign
            $table->string('internal_data', 200)->nullable();           // intrlData
            $table->string('sdc_datetime', 20)->nullable();
            $table->string('qr_url', 500)->nullable();
            $table->timestamps();
            $table->index(['document_type', 'document_id']);
            $table->unique(['etims_device_id', 'document_type', 'invoice_no'], 'etims_transmissions_device_invoice_unique');
        });

        Schema::create('etims_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code_class', 10);
            $table->string('code_class_name', 100)->nullable();
            $table->string('code', 20);
            $table->string('name', 200);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('in_use')->default(true);
            $table->timestamps();
            $table->unique(['code_class', 'code']);
        });

        Schema::create('etims_item_classes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 300);
            $table->unsignedTinyInteger('level')->nullable();
            $table->string('tax_type', 2)->nullable();
            $table->boolean('in_use')->default(true);
            $table->timestamps();
        });

        // What KRA needs to know about each product: its item class, the
        // units it is packed and counted in, where it is made, and the item
        // code built from those once it is registered.
        Schema::table('products', function (Blueprint $table) {
            $table->string('etims_item_class', 20)->nullable()->after('tax_code_id');
            $table->string('etims_packaging_unit', 5)->nullable()->after('etims_item_class');
            $table->string('etims_quantity_unit', 5)->nullable()->after('etims_packaging_unit');
            $table->string('etims_origin_country', 2)->nullable()->after('etims_quantity_unit');
            $table->string('etims_item_code', 30)->nullable()->after('etims_origin_country');
        });

        // KRA's tax type letter for each tax code (A exempt, B 16%, C zero-rated, D non-VAT, E 8%).
        Schema::table('tax_codes', function (Blueprint $table) {
            $table->string('etims_tax_type', 2)->nullable()->after('tax_type');
        });
    }

    public function down(): void
    {
        Schema::table('tax_codes', function (Blueprint $table) {
            $table->dropColumn('etims_tax_type');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['etims_item_class', 'etims_packaging_unit', 'etims_quantity_unit', 'etims_origin_country', 'etims_item_code']);
        });
        Schema::dropIfExists('etims_item_classes');
        Schema::dropIfExists('etims_codes');
        Schema::dropIfExists('etims_transmissions');
        Schema::dropIfExists('etims_devices');
    }
};
