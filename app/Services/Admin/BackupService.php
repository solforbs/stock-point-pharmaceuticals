<?php

namespace App\Services\Admin;

use Illuminate\Support\Carbon;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Part 17 — operations: consistent gzip-compressed MySQL dumps written to
 * config('backup.path'), the same directory deploy/backup.sh uses on the
 * server, so the Backup screen lists both. The password reaches mysqldump
 * only through the MYSQL_PWD environment variable, never the command line
 * (where any local user could read it from the process list).
 */
class BackupService
{
    /** Files this service will list, download or prune: db dumps, deploy/backup.sh's files archives and full zips. */
    public const NAME_PATTERN = '/^(db|files)-\d{8}-\d{6}\.(sql\.gz|tar\.gz)$|^full-\d{8}-\d{6}\.zip$/';

    /**
     * Directories inside storage/app taken into a full backup: everything a
     * user put there (licence scans, certificates of analysis, ADR
     * attachments) and nothing the code can rebuild.
     *
     * @var list<string>
     */
    public const FILE_ROOTS = ['private', 'public'];

    private const XAMPP_MYSQLDUMP = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

    public function directory(): string
    {
        return rtrim((string) config('backup.path'), '/\\');
    }

    /**
     * The mysqldump binary that will be run, or null when none can be found.
     */
    public function binary(): ?string
    {
        $configured = (string) config('backup.mysqldump', 'mysqldump');

        if ($configured !== '' && $configured !== 'mysqldump') {
            return is_file($configured) ? $configured : null;
        }
        if (PHP_OS_FAMILY === 'Windows' && is_file(self::XAMPP_MYSQLDUMP)) {
            return self::XAMPP_MYSQLDUMP;
        }

        return (new ExecutableFinder)->find('mysqldump');
    }

    /**
     * Takes a dump now and returns its listing entry.
     *
     * @return array{name: string, size: int, created_at: string, kind: string}
     *
     * @throws BackupFailedException
     */
    public function create(): array
    {
        $directory = $this->ensureDirectory();

        $name = 'db-'.now()->format('Ymd-His').'.sql.gz';
        $target = $directory.DIRECTORY_SEPARATOR.$name;
        $gz = @gzopen($target, 'wb6');
        if ($gz === false) {
            throw new BackupFailedException("Cannot write to {$directory}. Check the directory's permissions.");
        }

        try {
            $this->runDump(function (string $chunk) use ($gz): void {
                gzwrite($gz, $chunk);
            });
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($target);

            throw $e instanceof BackupFailedException ? $e : new BackupFailedException($e->getMessage(), 0, $e);
        }
        gzclose($gz);
        clearstatcache(true, $target);

        return $this->entry($target);
    }

    /**
     * A full backup: the database dump and every uploaded file in one zip,
     * ready to download. Code and configuration are deliberately left out —
     * code comes from the repository, and an archive that travels off the
     * server must not carry the credentials in .env.
     *
     * @return array{name: string, size: int, created_at: string, kind: string}
     *
     * @throws BackupFailedException
     */
    public function createFull(): array
    {
        $directory = $this->ensureDirectory();
        $stamp = now()->format('Ymd-His');
        $name = "full-{$stamp}.zip";
        $target = $directory.DIRECTORY_SEPARATOR.$name;

        $zip = new ZipArchive;
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BackupFailedException("Cannot write {$target}. Check the backup directory's permissions.");
        }

        try {
            $sql = '';
            $this->runDump(function (string $chunk) use (&$sql): void {
                $sql .= $chunk;
            });
            $zip->addFromString("database-{$stamp}.sql", $sql);

            $files = $this->collectFiles();
            foreach ($files as $relative => $path) {
                $zip->addFile($path, 'files/'.$relative);
            }

            $zip->addFromString('MANIFEST.txt', $this->manifest($stamp, strlen($sql), count($files)));
        } catch (\Throwable $e) {
            $zip->close();
            @unlink($target);

            throw $e instanceof BackupFailedException ? $e : new BackupFailedException($e->getMessage(), 0, $e);
        }

        if (! $zip->close()) {
            @unlink($target);

            throw new BackupFailedException('The backup archive could not be finalised; the disk may be full.');
        }

        clearstatcache(true, $target);

        return $this->entry($target);
    }

    /**
     * Uploaded files, keyed by the path they take inside the archive. The
     * backup directory itself is skipped, so a full backup never swallows
     * the backups that came before it.
     *
     * @return array<string, string>
     */
    public function collectFiles(): array
    {
        // Both the configured directory and the conventional one: on a server
        // BACKUP_PATH usually points outside storage, but storage/app/private/
        // backups can still hold older dumps, and a backup must never contain
        // the backups that came before it.
        $excluded = array_filter([
            realpath($this->directory()) ?: $this->directory(),
            realpath(storage_path('app/private/backups')) ?: null,
        ]);
        $found = [];

        foreach (self::FILE_ROOTS as $root) {
            $base = storage_path('app'.DIRECTORY_SEPARATOR.$root);
            if (! is_dir($base)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            /** @var \SplFileInfo $item */
            foreach ($iterator as $item) {
                $path = $item->getPathname();
                if (! $item->isFile()) {
                    continue;
                }
                foreach ($excluded as $directory) {
                    if (str_starts_with($path, $directory)) {
                        continue 2; // the next file, not the next root
                    }
                }
                $found[$root.'/'.str_replace('\\', '/', substr($path, strlen($base) + 1))] = $path;
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * Every backup file in the directory, newest first.
     *
     * @return list<array{name: string, size: int, created_at: string, kind: string}>
     */
    public function list(): array
    {
        $directory = $this->directory();
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        foreach (scandir($directory) ?: [] as $name) {
            $path = $directory.DIRECTORY_SEPARATOR.$name;
            if (preg_match(self::NAME_PATTERN, $name) === 1 && is_file($path)) {
                $files[] = $this->entry($path);
            }
        }
        usort($files, fn (array $a, array $b) => strcmp($b['created_at'], $a['created_at']) ?: strcmp($a['name'], $b['name']));

        return $files;
    }

    /**
     * The absolute path of a listed backup, or null. The name must equal a
     * file in the listing exactly, so no path can ever escape the directory.
     */
    public function pathFor(string $name): ?string
    {
        foreach ($this->list() as $file) {
            if (hash_equals($file['name'], $name)) {
                return $this->directory().DIRECTORY_SEPARATOR.$file['name'];
            }
        }

        return null;
    }

    /**
     * Deletes backups older than the retention window; returns the names removed.
     *
     * @return list<string>
     */
    public function prune(int $retentionDays): array
    {
        $cutoff = now()->subDays(max(1, $retentionDays));
        $removed = [];
        foreach ($this->list() as $file) {
            if (Carbon::parse($file['created_at'])->lt($cutoff) && @unlink($this->directory().DIRECTORY_SEPARATOR.$file['name'])) {
                $removed[] = $file['name'];
            }
        }

        return $removed;
    }

    /**
     * Runs mysqldump and hands its output to $write in chunks. Tests replace
     * this method so no real dump is taken.
     *
     * @param  \Closure(string): void  $write
     *
     * @throws BackupFailedException
     */
    protected function runDump(\Closure $write): void
    {
        $binary = $this->binary();
        if ($binary === null) {
            throw new BackupFailedException('mysqldump was not found on this server. Install the MySQL client tools or set MYSQLDUMP_PATH in .env to the mysqldump binary.');
        }

        /** @var array{host?: string, port?: string|int, database?: string, username?: string, password?: string, unix_socket?: string} $db */
        $db = (array) config('database.connections.'.config('backup.connection', 'mysql'), []);
        $command = [$binary, '--user='.($db['username'] ?? 'root'), '--single-transaction', '--quick', '--routines', '--triggers', '--no-tablespaces'];
        if (! empty($db['unix_socket'])) {
            $command[] = '--socket='.$db['unix_socket'];
        } else {
            $command[] = '--host='.($db['host'] ?? '127.0.0.1');
            $command[] = '--port='.($db['port'] ?? 3306);
        }
        $command[] = (string) ($db['database'] ?? '');

        $process = new Process($command, null, $this->dumpEnvironment((string) ($db['password'] ?? '')), null, (float) config('backup.timeout_seconds', 900));
        $stderr = '';
        $bytes = 0;

        try {
            $process->run(function (string $type, string $buffer) use ($write, &$stderr, &$bytes): void {
                if ($type === Process::OUT) {
                    $bytes += strlen($buffer);
                    $write($buffer);
                } else {
                    $stderr .= $buffer;
                }
            });
        } catch (ProcessStartFailedException $e) {
            throw new BackupFailedException("mysqldump could not be started ({$binary}). Set MYSQLDUMP_PATH in .env to a working binary.", 0, $e);
        }

        if (! $process->isSuccessful() || $bytes === 0) {
            $firstLine = trim((string) strtok($stderr, "\n"));

            throw new BackupFailedException('mysqldump failed: '.($firstLine !== '' ? $firstLine : 'exit code '.$process->getExitCode()));
        }
    }

    /**
     * @throws BackupFailedException
     */
    private function ensureDirectory(): string
    {
        $directory = $this->directory();
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new BackupFailedException("The backup directory {$directory} does not exist and could not be created. Check BACKUP_PATH and its permissions.");
        }

        return $directory;
    }

    /** A plain note inside the archive saying what it holds and how to put it back. */
    private function manifest(string $stamp, int $sqlBytes, int $fileCount): string
    {
        $database = (string) config('database.connections.'.config('backup.connection', 'mysql').'.database');

        return implode("\n", [
            config('app.name').' — full backup',
            'Taken: '.now()->toDayDateTimeString(),
            "Database: {$database} (database-{$stamp}.sql, ".number_format($sqlBytes).' bytes)',
            "Uploaded files: {$fileCount} under files/ (from storage/app)",
            '',
            'Restore:',
            "  mysql -u <user> -p {$database} < database-{$stamp}.sql",
            '  copy files/private and files/public back into storage/app/',
            '',
            'Application code comes from the Git repository and configuration',
            'from .env; neither is in this archive, so it carries no passwords.',
            '',
        ]);
    }

    /**
     * The environment mysqldump runs in.
     *
     * mysqldump opens a TCP socket, and on Windows Winsock cannot start
     * without SystemRoot. Apache does not pass it to PHP, so the dump fails
     * from the browser with "Can't create TCP/IP socket (10106)" while
     * working perfectly from the console. Naming the variables explicitly
     * makes both contexts behave the same.
     *
     * @return array<string, string>
     */
    protected function dumpEnvironment(string $password): array
    {
        $env = ['MYSQL_PWD' => $password];

        if (PHP_OS_FAMILY !== 'Windows') {
            return $env;
        }

        foreach (['SystemRoot', 'windir', 'SystemDrive', 'ComSpec', 'PATH', 'TEMP', 'TMP', 'ProgramData'] as $name) {
            $value = getenv($name);
            if ($value === false || $value === '') {
                $value = $_SERVER[$name] ?? null;
            }
            if (is_string($value) && $value !== '') {
                $env[$name] = $value;
            }
        }

        $env['SystemRoot'] ??= 'C:\Windows';
        $env['windir'] ??= $env['SystemRoot'];

        return $env;
    }

    /**
     * @return array{name: string, size: int, created_at: string, kind: string}
     */
    private function entry(string $path): array
    {
        $name = basename($path);

        return [
            'name' => $name,
            'size' => (int) filesize($path),
            'created_at' => Carbon::createFromTimestamp((int) filemtime($path))->toIso8601String(),
            'kind' => match (true) {
                str_starts_with($name, 'db-') => 'database',
                str_starts_with($name, 'full-') => 'full',
                default => 'files',
            },
        ];
    }
}
