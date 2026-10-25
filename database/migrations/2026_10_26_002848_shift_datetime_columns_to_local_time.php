<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The app moves from UTC to Kenyan time (+03:00). TIMESTAMP columns need
 * nothing: MySQL keeps them in UTC and the connection now reads them back
 * at +03:00. DATETIME columns hold the wall-clock value as written, which
 * was UTC, so they move forward three hours to say the same moment.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->shift('+');
    }

    public function down(): void
    {
        $this->shift('-');
    }

    private function shift(string $sign): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $columns = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('data_type', 'datetime')
            ->get(['table_name', 'column_name']);

        foreach ($columns as $column) {
            $table = $column->table_name ?? $column->TABLE_NAME;
            $name = $column->column_name ?? $column->COLUMN_NAME;
            DB::statement("UPDATE `{$table}` SET `{$name}` = `{$name}` {$sign} INTERVAL 3 HOUR WHERE `{$name}` IS NOT NULL");
        }
    }
};
