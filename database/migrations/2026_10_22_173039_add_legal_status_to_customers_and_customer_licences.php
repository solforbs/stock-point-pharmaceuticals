<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer's legal status (how it is constituted, its registration, its
 * superintendent pharmacist) and its legal documents. The documents live in
 * the licence register as CUSTOMER licences — certificate of incorporation,
 * PPB licence, county permit — so expiries show beside every other licence.
 * A certificate of incorporation never expires, so expiry becomes optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('legal_status', 30)->nullable()->after('customer_type');
            $table->string('registration_number', 100)->nullable()->after('legal_status');
            $table->string('kra_pin', 30)->nullable()->after('registration_number');
            $table->string('superintendent_name', 150)->nullable()->after('kra_pin');
            $table->string('superintendent_reg_no', 100)->nullable()->after('superintendent_name');
        });

        Schema::table('licences', function (Blueprint $table) {
            $table->enum('holder_type', ['ORGANISATION', 'BRANCH', 'EMPLOYEE', 'SUPPLIER', 'CUSTOMER'])->change();
            $table->enum('licence_type', [
                'PPB_PREMISES', 'PPB_PHARMACIST', 'PPB_PHARMTECH', 'BUSINESS_PERMIT', 'FIRE', 'PUBLIC_HEALTH', 'KRA_TCC', 'NHIF_SHIF', 'OTHER',
                'CERT_OF_INCORPORATION', 'COUNTY_PERMIT',
            ])->change();
            $table->date('expiry_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['legal_status', 'registration_number', 'kra_pin', 'superintendent_name', 'superintendent_reg_no']);
        });
        // The enum and expiry changes are widened only; narrowing them back
        // could fail on rows that use the new values, so they are left as is.
    }
};
