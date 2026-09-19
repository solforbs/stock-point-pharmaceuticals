<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Part 18.3 — deleting master data. A row nothing points at can go; a row
 * something points at cannot, because deleting it would leave documents
 * referring to a record that no longer exists.
 *
 * What counts as "points at" is read from the database's own foreign keys
 * rather than a list kept by hand, so a reference added by a later migration
 * protects the row automatically and can never be forgotten here.
 */
class RecordDeletionService
{
    /**
     * Rows that refer to this record, per referring table. Empty means the
     * record is safe to delete.
     *
     * @return list<array{table: string, column: string, count: int}>
     */
    public function references(string $table, string $id): array
    {
        $found = [];

        foreach ($this->foreignKeysTo($table) as $fk) {
            $count = (int) DB::table($fk['table'])->where($fk['column'], $id)->count();
            if ($count > 0) {
                $found[] = ['table' => $fk['table'], 'column' => $fk['column'], 'count' => $count];
            }
        }

        usort($found, fn (array $a, array $b) => $b['count'] <=> $a['count']);

        return $found;
    }

    /**
     * Deletes the record, or refuses and says exactly what is holding it.
     * The whole row goes into the audit log first, so a deletion made in
     * error can be read back and re-entered.
     *
     * @throws RecordInUseException
     */
    public function delete(Model $model, string $label, ?int $userId = null): void
    {
        $table = $model->getTable();
        $id = (string) $model->getKey();

        $references = $this->references($table, $id);
        if ($references !== []) {
            throw new RecordInUseException($label, $references);
        }

        DB::transaction(function () use ($model, $table, $id, $label, $userId): void {
            AuditLog::record('RECORD_DELETED', $table, $id, [
                'user_id' => $userId,
                'reference' => $label,
                'before_json' => $model->attributesToArray(),
            ]);

            $model->delete();
        });
    }

    /**
     * Every column in the schema that is a foreign key onto this table's
     * primary key.
     *
     * @return list<array{table: string, column: string}>
     */
    private function foreignKeysTo(string $table): array
    {
        // Self-references are included deliberately: a category with children
        // is held by them exactly as it would be by another table's rows.
        $rows = DB::select(
            'SELECT TABLE_NAME as referring_table, COLUMN_NAME as referring_column
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME = ?',
            [$table]
        );

        return array_map(
            fn (object $row) => ['table' => (string) $row->referring_table, 'column' => (string) $row->referring_column],
            $rows
        );
    }
}
