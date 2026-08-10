<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TestDatabaseIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_database_solo_utiliza_sqlite_en_memoria(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        $databases = DB::select('PRAGMA database_list');

        $this->assertSame('', $databases[0]->file);
    }

    public function test_el_cache_persistente_normal_esta_desviado_y_no_se_carga(): void
    {
        $this->assertSame(
            base_path('bootstrap/cache/config-testing.php'),
            app()->getCachedConfigPath(),
        );
        $this->assertFalse(app()->configurationIsCached());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }
}
