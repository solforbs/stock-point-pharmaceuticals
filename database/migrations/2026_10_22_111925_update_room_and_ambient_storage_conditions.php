<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Controlled room temperature is 20–25 °C and ambient 15–30 °C. The seeder
 * only creates missing conditions, so the rows already installed are
 * corrected here; cold-chain monitoring reads these ranges directly.
 */
return new class extends Migration
{
    /** @var array<string, array{name: string, min_temp_c: ?string, max_temp_c: ?string}> */
    private const AMENDED = [
        'ROOM' => ['name' => 'Controlled Room Temperature (20 °C – 25 °C)', 'min_temp_c' => '20.00', 'max_temp_c' => '25.00'],
        'AMBIENT' => ['name' => 'Ambient (15 °C – 30 °C)', 'min_temp_c' => '15.00', 'max_temp_c' => '30.00'],
    ];

    /** @var array<string, array{name: string, min_temp_c: ?string, max_temp_c: ?string}> */
    private const PREVIOUS = [
        'ROOM' => ['name' => 'Controlled room temperature (15–25 °C)', 'min_temp_c' => '15.00', 'max_temp_c' => '25.00'],
        'AMBIENT' => ['name' => 'Ambient (below 25 °C)', 'min_temp_c' => null, 'max_temp_c' => '25.00'],
    ];

    public function up(): void
    {
        foreach (self::AMENDED as $code => $values) {
            DB::table('storage_conditions')->where('code', $code)->update($values + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::PREVIOUS as $code => $values) {
            DB::table('storage_conditions')->where('code', $code)->update($values + ['updated_at' => now()]);
        }
    }
};
