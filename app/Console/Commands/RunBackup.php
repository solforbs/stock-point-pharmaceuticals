<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\Admin\BackupFailedException;
use App\Services\Admin\BackupService;
use Illuminate\Console\Command;

/**
 * Part 17 — the nightly database backup: a gzip-compressed mysqldump into
 * config('backup.path'), then backups older than the retention window are
 * pruned. A failed dump exits non-zero so the scheduler's output shows it.
 */
class RunBackup extends Command
{
    protected $signature = 'backup:run {--no-prune : Keep old backups}';

    protected $description = 'Dump the database to the backup directory and prune backups past retention (nightly)';

    public function handle(BackupService $backups): int
    {
        try {
            $file = $backups->create();
        } catch (BackupFailedException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        AuditLog::record('BACKUP_CREATED', 'backup', $file['name'], ['reference' => $file['name'], 'after_json' => $file + ['source' => 'backup:run']]);
        $this->info("Backup written: {$file['name']} ({$file['size']} bytes) in {$backups->directory()}");

        if (! $this->option('no-prune')) {
            $removed = $backups->prune((int) config('backup.retention_days', 30));
            $this->line(count($removed).' backup(s) older than '.config('backup.retention_days', 30).' days removed.');
        }

        return self::SUCCESS;
    }
}
