<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SqlitePragmaConfigurationTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/sqlite-wal-'.bin2hex(random_bytes(5)).'.sqlite');
        File::ensureDirectoryExists(dirname($this->databasePath));
        File::put($this->databasePath, '');

        config()->set('database.connections.sqlite_wal_test', [
            'driver' => 'sqlite',
            'database' => $this->databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => 'WAL',
            'transaction_mode' => 'DEFERRED',
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite_wal_test');

        File::delete([
            $this->databasePath,
            $this->databasePath.'-wal',
            $this->databasePath.'-shm',
        ]);

        parent::tearDown();
    }

    public function test_file_based_sqlite_connection_uses_expected_pragmas(): void
    {
        $connection = DB::connection('sqlite_wal_test');

        $journalMode = $connection->selectOne('PRAGMA journal_mode');
        $busyTimeout = $connection->selectOne('PRAGMA busy_timeout');
        $foreignKeys = $connection->selectOne('PRAGMA foreign_keys');

        $this->assertSame('wal', strtolower((string) $journalMode->journal_mode));
        $this->assertSame(5000, (int) $busyTimeout->timeout);
        $this->assertSame(1, (int) $foreignKeys->foreign_keys);
    }

    public function test_test_database_in_memory_does_not_force_wal(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertNull(config('database.connections.sqlite.journal_mode'));
        $this->assertSame(5000, config('database.connections.sqlite.busy_timeout'));
        $this->assertTrue(config('database.connections.sqlite.foreign_key_constraints'));
    }
}
