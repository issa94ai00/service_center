<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against anything but a throwaway database.
     *
     * phpunit.xml points the suite at an in-memory SQLite database, but those
     * <env> entries are ignored whenever bootstrap/cache/config.php exists —
     * Laravel loads the cached config instead and never re-reads them. On a
     * config-cached production box that silently aims the whole suite, and
     * RefreshDatabase's migrate:fresh, at the live database.
     *
     * Running `php artisan config:clear` before the suite is the fix; this is
     * the seatbelt that makes forgetting it loud instead of catastrophic.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to run tests against the '{$connection}' connection (database: {$database}). "
                .'The suite must use in-memory SQLite. The config cache is almost certainly stale — '
                .'run `php artisan config:clear`, run the tests, then `php artisan config:cache`. '
                .'`composer test` does all three.'
            );
        }
    }
}
