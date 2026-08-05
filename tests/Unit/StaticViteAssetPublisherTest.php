<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\StaticViteAssetPublisher;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class StaticViteAssetPublisherTest extends TestCase
{
    private string $root;

    private string $source;

    private string $dist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/vite-publisher-'.bin2hex(random_bytes(5)));
        $this->source = $this->root.'/source';
        $this->dist = $this->root.'/dist';
        File::ensureDirectoryExists($this->source.'/assets');
        File::ensureDirectoryExists($this->dist);
        File::put($this->source.'/manifest.json', '{}');
        File::put($this->source.'/assets/app.css', 'nuevo');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_reemplaza_el_build_completo_y_elimina_assets_obsoletos(): void
    {
        File::ensureDirectoryExists($this->dist.'/build/assets');
        File::put($this->dist.'/build/assets/obsoleto.css', 'viejo');

        (new StaticViteAssetPublisher)->publish($this->source, $this->dist);

        $this->assertFileExists($this->dist.'/build/manifest.json');
        $this->assertSame('nuevo', File::get($this->dist.'/build/assets/app.css'));
        $this->assertFileDoesNotExist($this->dist.'/build/assets/obsoleto.css');
        $this->assertSame([], File::glob($this->dist.'/.build-*') ?: []);
    }

    public function test_rechaza_enlaces_simbolicos_dentro_del_build(): void
    {
        $external = $this->root.'/externo.css';
        File::put($external, 'externo');
        symlink($external, $this->source.'/assets/enlace.css');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('enlace simbolico');

        (new StaticViteAssetPublisher)->publish($this->source, $this->dist);
    }
}
