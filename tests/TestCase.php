<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    private static bool $migrated = false;

    /**
     * Tests run against the real MySQL test database (see phpunit.xml) so
     * CHECK constraints, row locks and enum columns behave exactly as in
     * production. A full `migrate:fresh` takes minutes on XAMPP's MariaDB,
     * so instead we apply only pending migrations, once per process, and
     * roll every test back in a transaction.
     */
    protected function setUpTraits(): array
    {
        $this->migrateTestDatabaseOnce();

        return parent::setUpTraits();
    }

    private function migrateTestDatabaseOnce(): void
    {
        if (self::$migrated) {
            return;
        }

        $database = (string) DB::connection()->getDatabaseName();
        if (! str_contains($database, 'test')) {
            throw new \RuntimeException("Refusing to run tests against database '{$database}' — the test database name must contain 'test'.");
        }

        $this->artisan('migrate', ['--force' => true]);

        self::$migrated = true;
    }
}
