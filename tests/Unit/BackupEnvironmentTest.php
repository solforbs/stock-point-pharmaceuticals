<?php

namespace Tests\Unit;

use App\Services\Admin\BackupService;
use PHPUnit\Framework\TestCase;

/**
 * Part 17 — mysqldump connects over TCP, and on Windows Winsock cannot
 * start without SystemRoot. Apache does not pass it to PHP, so dropping
 * these variables makes the Backup screen fail with
 * "Can't create TCP/IP socket (10106)" while the console still works.
 */
class BackupEnvironmentTest extends TestCase
{
    public function test_the_dump_environment_carries_the_password_and_the_windows_system_variables(): void
    {
        $service = new class extends BackupService
        {
            /** @return array<string, string> */
            public function environmentFor(string $password): array
            {
                return $this->dumpEnvironment($password);
            }
        };

        $env = $service->environmentFor('s3cret');

        $this->assertSame('s3cret', $env['MYSQL_PWD'], 'the password travels by environment, never on the command line');

        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame(['MYSQL_PWD'], array_keys($env), 'nothing extra is forced on POSIX');

            return;
        }

        $this->assertArrayHasKey('SystemRoot', $env);
        $this->assertNotSame('', $env['SystemRoot']);
        $this->assertArrayHasKey('windir', $env);
    }

    public function test_a_full_backup_is_recognised_by_the_listing_pattern(): void
    {
        $this->assertMatchesRegularExpression(BackupService::NAME_PATTERN, 'full-20260918-224528.zip');
        $this->assertMatchesRegularExpression(BackupService::NAME_PATTERN, 'db-20260918-224528.sql.gz');
        $this->assertMatchesRegularExpression(BackupService::NAME_PATTERN, 'files-20260101-020000.tar.gz');

        $this->assertDoesNotMatchRegularExpression(BackupService::NAME_PATTERN, 'notes.txt');
        $this->assertDoesNotMatchRegularExpression(BackupService::NAME_PATTERN, '../../.env');
        $this->assertDoesNotMatchRegularExpression(BackupService::NAME_PATTERN, 'full-20260918-224528.zip.exe');
    }
}
