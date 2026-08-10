<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

final class TestDatabaseSafety
{
    public static function assertApplicationIsSafe(Application $app): void
    {
        self::assertSafe(
            environment: $app->environment(),
            defaultConnection: (string) $app['config']->get('database.default'),
            sqliteDriver: (string) $app['config']->get('database.connections.sqlite.driver'),
            sqliteDatabase: $app['config']->get('database.connections.sqlite.database'),
        );
    }

    public static function assertSafe(
        string $environment,
        string $defaultConnection,
        string $sqliteDriver,
        mixed $sqliteDatabase,
    ): void {
        if ($environment !== 'testing') {
            throw new RuntimeException(
                "SEGURIDAD: la suite intento ejecutarse fuera del entorno testing [{$environment}].",
            );
        }

        if ($defaultConnection !== 'sqlite' || $sqliteDriver !== 'sqlite') {
            throw new RuntimeException(
                "SEGURIDAD: los tests intentaron usar una conexion no permitida [{$defaultConnection}/{$sqliteDriver}].",
            );
        }

        if ($sqliteDatabase !== ':memory:') {
            $database = is_scalar($sqliteDatabase) || $sqliteDatabase === null
                ? var_export($sqliteDatabase, true)
                : get_debug_type($sqliteDatabase);

            throw new RuntimeException(
                "SEGURIDAD: los tests intentaron usar una SQLite persistente: {$database}",
            );
        }
    }
}
