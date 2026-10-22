<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How customers pay the institution — Lipa na M-PESA paybill and account,
 * or a till — printed on price lists beside its bank accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('mpesa_paybill', 20)->nullable()->after('postal_address');
            $table->string('mpesa_account', 50)->nullable()->after('mpesa_paybill');
            $table->string('mpesa_till', 20)->nullable()->after('mpesa_account');
            $table->string('payment_note', 255)->nullable()->after('mpesa_till');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn(['mpesa_paybill', 'mpesa_account', 'mpesa_till', 'payment_note']);
        });
    }
};
