<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The product name is now the general/scientific name, so the separate
 * generic name goes. Nothing is lost: a generic name the product name does
 * not already contain is kept in the description first.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->whereNotNull('generic_name')->where('generic_name', '!=', '')
            ->orderBy('id')->select(['id', 'name', 'generic_name', 'description'])
            ->chunk(500, function ($products) {
                foreach ($products as $product) {
                    $generic = trim((string) $product->generic_name);
                    if ($generic === '' || mb_stripos((string) $product->name, $generic) !== false) {
                        continue;
                    }
                    $description = trim((string) $product->description);
                    if (mb_stripos($description, $generic) !== false) {
                        continue;
                    }
                    $merged = $description === '' ? "Generic: {$generic}" : "{$description} · Generic: {$generic}";
                    DB::table('products')->where('id', $product->id)->update(['description' => mb_substr($merged, 0, 500)]);
                }
            });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('generic_name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('generic_name')->nullable()->after('brand_name');
        });
    }
};
