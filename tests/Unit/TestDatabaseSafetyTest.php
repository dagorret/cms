<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseSafety;

final class TestDatabaseSafetyTest extends TestCase
{
    public function test_testing_con_sqlite_en_memoria_esta_permitido(): void
    {
        TestDatabaseSafety::assertSafe('testing', 'sqlite', 'sqlite', ':memory:');

        $this->addToAssertionCount(1);
    }

    public function test_un_entorno_distinto_de_testing_aborta(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fuera del entorno testing [production]');

        TestDatabaseSafety::assertSafe('production', 'sqlite', 'sqlite', ':memory:');
    }

    #[DataProvider('persistentSqlitePaths')]
    public function test_una_sqlite_persistente_aborta(string $database): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SEGURIDAD: los tests intentaron usar una SQLite persistente');
        $this->expectExceptionMessage($database);

        TestDatabaseSafety::assertSafe('testing', 'sqlite', 'sqlite', $database);
    }

    /** @return array<string, array{string}> */
    public static function persistentSqlitePaths(): array
    {
        return [
            'absoluta' => ['/var/www/database/database.sqlite'],
            'relativa' => ['database/database.sqlite'],
        ];
    }

    public function test_una_conexion_por_defecto_distinta_de_sqlite_aborta(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('conexion no permitida [mysql/sqlite]');

        TestDatabaseSafety::assertSafe('testing', 'mysql', 'sqlite', ':memory:');
    }
}
