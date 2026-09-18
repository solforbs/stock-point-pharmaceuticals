<?php

namespace App\Services\Admin;

use Illuminate\Support\Carbon;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Part 17 — operations: consistent gzip-compressed MySQL dumps written to
 * config('backup.path'), the same directory deploy/backup.sh uses on the
 * server, so the Backup screen lists both. The password reaches mysqldump
 * only through the MYSQL_PWD environment variable, never the command line
 * (where any local user could read it from the process list).
 */
class BackupService
{
    /** Files this service will list, download or prune: db dumps and deploy/backup.sh's files archives. */
    public const NAME_PATTERN = '/^(db|files)-\d{8}-\d{6}\.(sql\.gz|tar\.gz)$/';

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
        $directory = $this->directory();
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new BackupFailedException("The backup directory {$directory} does not exist and could not be created. Check BACKUP_PATH and its permissions.");
        }

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
        usort($files, fn (array $a, array $b) => strcmp($b['created_at'], $a['created_at']) ?: strcmp($b['name'], $a['name']));

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

        $process = new Process($command, null, ['MYSQL_PWD' => (string) ($db['password'] ?? '')], null, (float) config('backup.timeout_seconds', 900));
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
     * @return array{name: string, size: int, created_at: string, kind: string}
     */
    private function entry(string $path): array
    {
        $name = basename($path);

        return [
            'name' => $name,
            'size' => (int) filesize($path),
            'created_at' => Carbon::createFromTimestamp((int) filemtime($path))->toIso8601String(),
            'kind' => str_starts_with($name, 'db-') ? 'database' : 'files',
        ];
    }
}
