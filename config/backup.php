<?php

/*
|--------------------------------------------------------------------------
| Backups (Part 17 — operations)
|--------------------------------------------------------------------------
| Where database dumps are written and read from. On the server set
| BACKUP_PATH to the directory deploy/backup.sh writes to
| (/var/backups/pharmacy_erp) so the Backup screen lists both the nightly
| script's dumps and the ones taken from the application. mysqldump is
| found on PATH unless MYSQLDUMP_PATH names the binary; on a Windows XAMPP
| install C:\xampp\mysql\bin\mysqldump.exe is used when present.
*/
return [
    'path' => env('BACKUP_PATH', storage_path('app/private/backups')),

    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),

    'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),

    'connection' => env('BACKUP_DB_CONNECTION', 'mysql'),

    'timeout_seconds' => (int) env('BACKUP_TIMEOUT', 900),
];
