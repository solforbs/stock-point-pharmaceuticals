<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Part 4.5's worked example rounds the final unit price to the nearest
     * KES 0.50 (446.81 → 447.00). The original enum stopped at ten cents.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE product_discount_policies MODIFY round_to ENUM('NONE','WHOLE','FIFTY_CENTS','TEN_CENTS','FIVE_CENTS') NOT NULL DEFAULT 'NONE'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE product_discount_policies MODIFY round_to ENUM('NONE','WHOLE','FIVE_CENTS','TEN_CENTS') NOT NULL DEFAULT 'NONE'");
        }
    }
};
