<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_tiers', function (Blueprint $table) {
            $table->foreignUuid('default_price_list_id')->nullable()->after('name')
                ->constrained('price_lists')->nullOnDelete();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignUuid('price_list_id')->nullable()->after('fulfilment_policy')
                ->constrained('price_lists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_tiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_price_list_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_list_id');
        });
    }
};
