<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Retail and wholesale are two businesses under one roof: each store
 * belongs to one of them (null serves both), so the retail pharmacy holds
 * and sells only its own stock. Branches trading in both get an empty
 * retail store of their own; stock reaches it by transfer or receipt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('business_mode', 10)->nullable()->after('store_type');
        });

        DB::table('stores')->whereIn('store_type', ['RETAIL', 'DISPENSARY'])->update(['business_mode' => 'RETAIL']);
        DB::table('stores')->whereNotIn('store_type', ['RETAIL', 'DISPENSARY'])->update(['business_mode' => 'WHOLESALE']);

        $branches = DB::table('branches')->where('retail_enabled', true)->where('wholesale_enabled', true)->pluck('id');
        foreach ($branches as $branchId) {
            if (DB::table('stores')->where('branch_id', $branchId)->where('business_mode', 'RETAIL')->exists()) {
                continue;
            }
            $code = DB::table('stores')->where('branch_id', $branchId)->where('code', 'RETAIL')->exists() ? 'RETAIL-1' : 'RETAIL';
            DB::table('stores')->insert([
                'id' => (string) Str::uuid(), 'branch_id' => $branchId, 'code' => $code, 'name' => 'Retail pharmacy',
                'store_type' => 'RETAIL', 'business_mode' => 'RETAIL', 'is_sellable' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('business_mode');
        });
    }
};
