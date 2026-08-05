<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\StaticViteAssetResolver;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class StaticViteAssetResolverTest extends TestCase
{
    private string $buildPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPath = storage_path('framework/testing/vite-resolver-'.bin2hex(random_bytes(5)));
        File::ensureDirectoryExists($this->buildPath.'/assets');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->buildPath);

        parent::tearDown();
    }

    public function test_falla_con_mensaje_claro_si_falta_el_manifest(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('npm run build');

        (new StaticViteAssetResolver($this->buildPath))->resolve();
    }

    public function test_falla_si_el_manifest_es_invalido(): void
    {
        File::put($this->buildPath.'/manifest.json', '{invalido');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JSON invalido');

        (new StaticViteAssetResolver($this->buildPath))->resolve();
    }

    public function test_falla_si_falta_la_entrada_css_principal(): void
    {
        $this->writeManifest([
            'resources/js/app.js' => ['file' => 'assets/app.js', 'isEntry' => true],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('resources/css/app.css');

        (new StaticViteAssetResolver($this->buildPath))->resolve();
    }

    public function test_resuelve_css_javascript_css_asociado_e_imports_con_hash(): void
    {
        $this->writeAsset('assets/app-ABC.css');
        $this->writeAsset('assets/app-XYZ.js');
        $this->writeAsset('assets/chunk-123.js');
        $this->writeAsset('assets/chunk-456.css');
        $this->writeManifest([
            'resources/css/app.css' => ['file' => 'assets/app-ABC.css', 'isEntry' => true],
            'resources/js/app.js' => [
                'file' => 'assets/app-XYZ.js',
                'css' => ['assets/chunk-456.css'],
                'imports' => ['_chunk.js'],
                'isEntry' => true,
            ],
            '_chunk.js' => ['file' => 'assets/chunk-123.js'],
        ]);

        $assets = (new StaticViteAssetResolver($this->buildPath))->resolve('/blog');

        $this->assertSame(
            ['/blog/build/assets/app-ABC.css', '/blog/build/assets/chunk-456.css'],
            $assets->stylesheetUrls(),
        );
        $this->assertSame(
            ['/blog/build/assets/app-XYZ.js'],
            $assets->scriptUrls(),
        );
    }

    public function test_admite_una_exportacion_sin_javascript(): void
    {
        $this->writeAsset('assets/app-CSSONLY.css');
        $this->writeManifest([
            'resources/css/app.css' => ['file' => 'assets/app-CSSONLY.css', 'isEntry' => true],
        ]);

        $assets = (new StaticViteAssetResolver($this->buildPath))->resolve();

        $this->assertSame(['/build/assets/app-CSSONLY.css'], $assets->stylesheetUrls());
        $this->assertSame([], $assets->scriptUrls());
    }

    public function test_rechaza_rutas_inseguras_del_manifest(): void
    {
        $this->writeManifest([
            'resources/css/app.css' => ['file' => '../fuera.css', 'isEntry' => true],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ruta de asset insegura');

        (new StaticViteAssetResolver($this->buildPath))->resolve();
    }

    /** @param array<string, mixed> $manifest */
    private function writeManifest(array $manifest): void
    {
        File::put($this->buildPath.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    private function writeAsset(string $path): void
    {
        File::ensureDirectoryExists(dirname($this->buildPath.'/'.$path));
        File::put($this->buildPath.'/'.$path, $path);
    }
}
