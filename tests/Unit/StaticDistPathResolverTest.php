<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\StaticDistPathResolver;
use RuntimeException;
use Tests\TestCase;

class StaticDistPathResolverTest extends TestCase
{
    public function test_resuelve_una_ruta_relativa_desde_la_raiz_del_proyecto(): void
    {
        $this->assertSame(
            base_path('dist/ensayos'),
            (new StaticDistPathResolver)->resolve('dist/ensayos'),
        );
    }

    public function test_hace_portable_una_ruta_absoluta_legacy_que_contiene_dist(): void
    {
        $this->assertSame(
            base_path('dist/ensayos'),
            (new StaticDistPathResolver)->resolve('/home/otro/proyecto/dist/ensayos'),
        );
    }

    public function test_rechaza_una_ruta_fuera_del_proyecto_y_sin_raiz_portable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fuera del proyecto');

        (new StaticDistPathResolver)->resolve('/tmp/salida-no-permitida');
    }

    public function test_rechaza_recorridos_relativos_hacia_fuera(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('segmento no permitido');

        (new StaticDistPathResolver)->resolve('../dist');
    }
}
