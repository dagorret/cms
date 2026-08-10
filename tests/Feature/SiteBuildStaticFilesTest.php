<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\SiteBuildCommand;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SiteBuildStaticFilesTest extends TestCase
{
    private string $resourcePath;

    private string $distRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = bin2hex(random_bytes(5));
        $this->resourcePath = storage_path("framework/testing/static-resources-{$suffix}");
        $this->distRoot = storage_path("framework/testing/static-dist-{$suffix}");

        File::ensureDirectoryExists($this->resourcePath);
        File::ensureDirectoryExists($this->distRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->resourcePath);
        File::deleteDirectory($this->distRoot);

        parent::tearDown();
    }

    public function test_publica_un_archivo_en_la_raiz_del_dist_path(): void
    {
        File::ensureDirectoryExists($this->resourcePath.'/static');
        File::put($this->resourcePath.'/static/favicon.ico', 'favicon de prueba');

        $this->publishStaticFiles($this->distRoot.'/sitio-uno');

        $this->assertFileExists($this->distRoot.'/sitio-uno/favicon.ico');
        $this->assertSame('favicon de prueba', File::get($this->distRoot.'/sitio-uno/favicon.ico'));
    }

    public function test_publica_archivos_preservando_subdirectorios(): void
    {
        File::ensureDirectoryExists($this->resourcePath.'/static/images');
        File::put($this->resourcePath.'/static/images/default-logo.png', 'logo de prueba');

        $this->publishStaticFiles($this->distRoot.'/sitio-uno');

        $this->assertFileExists($this->distRoot.'/sitio-uno/images/default-logo.png');
        $this->assertSame('logo de prueba', File::get($this->distRoot.'/sitio-uno/images/default-logo.png'));
    }

    public function test_omite_la_publicacion_si_resources_static_no_existe(): void
    {
        $targetFolder = $this->distRoot.'/sitio-uno';
        File::ensureDirectoryExists($targetFolder);

        $this->publishStaticFiles($targetFolder);

        $this->assertDirectoryExists($targetFolder);
        $this->assertSame([], File::files($targetFolder));
    }

    public function test_publica_en_dos_dist_path_sin_mezclar_archivos(): void
    {
        File::ensureDirectoryExists($this->resourcePath.'/static');
        File::put($this->resourcePath.'/static/solo-sitio-uno.txt', 'uno');

        $this->publishStaticFiles($this->distRoot.'/sitio-uno');

        File::delete($this->resourcePath.'/static/solo-sitio-uno.txt');
        File::put($this->resourcePath.'/static/solo-sitio-dos.txt', 'dos');

        $this->publishStaticFiles($this->distRoot.'/sitio-dos');

        $this->assertFileExists($this->distRoot.'/sitio-uno/solo-sitio-uno.txt');
        $this->assertFileDoesNotExist($this->distRoot.'/sitio-uno/solo-sitio-dos.txt');
        $this->assertFileExists($this->distRoot.'/sitio-dos/solo-sitio-dos.txt');
        $this->assertFileDoesNotExist($this->distRoot.'/sitio-dos/solo-sitio-uno.txt');
    }

    private function publishStaticFiles(string $targetFolder): void
    {
        File::ensureDirectoryExists($targetFolder);

        $command = new class($this->resourcePath.'/static') extends SiteBuildCommand
        {
            public function __construct(private readonly string $sourcePath)
            {
                parent::__construct();
            }

            public function publishStaticFilesForTest(string $targetFolder): void
            {
                $this->publishStaticFiles($targetFolder);
            }

            public function info($string, $verbosity = null): void
            {
                // Console output is irrelevant for these filesystem-focused tests.
            }

            public function comment($string, $verbosity = null): void
            {
                // Console output is irrelevant for these filesystem-focused tests.
            }

            protected function staticFilesSourcePath(): string
            {
                return $this->sourcePath;
            }
        };
        $command->setLaravel($this->app);
        $command->publishStaticFilesForTest($targetFolder);
    }
}
