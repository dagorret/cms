<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Site;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

class PublishingTest extends TestCase
{
    use RefreshDatabase;

    private string $mediaBasePath = 'publishing-test-media';

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/public/'.$this->mediaBasePath));

        parent::tearDown();
    }

    public function test_un_post_guardado_como_borrador_no_dispara_la_cola_ni_genera_archivos(): void
    {
        Queue::fake();
        Storage::fake('public');

        $site = $this->createSite(Storage::disk('public')->path('static-site'));

        $post = Post::factory()->create([
            'site_id' => $site->short_name,
            'status' => Post::STATUS_DRAFT,
            'slug' => 'borrador-sin-publicar',
        ]);

        Queue::assertNothingPushed();
        Storage::disk('public')->assertMissing("static-site/{$post->slug}/index.html");
    }

    public function test_un_post_publicado_encola_la_orden_correctamente(): void
    {
        Queue::fake();
        Storage::fake('public');

        $site = $this->createSite(Storage::disk('public')->path('static-site'));

        $post = Post::factory()->create([
            'site_id' => $site->short_name,
            'status' => Post::STATUS_DRAFT,
            'slug' => 'post-para-publicar',
        ]);

        Queue::assertNothingPushed();

        $post->update([
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        Queue::assertPushed(QueuedCommand::class, function (QueuedCommand $job) use ($site, $post): bool {
            $data = $this->queuedCommandData($job);

            return ($data[0] ?? null) === 'site:build'
                && ($data[1]['site_id'] ?? null) === $site->short_name
                && ($data[1]['--post'] ?? null) === $post->getKey()
                && ($data[1]['--resource'] ?? null) === true;
        });
    }

    public function test_la_ejecucion_real_del_comando_genera_el_archivo_html_y_optimiza_imagenes(): void
    {
        if (method_exists(Queue::getFacadeRoot(), 'unfake')) {
            Queue::unfake();
        }

        Queue::swap($this->app['queue']);
        Storage::fake('public');

        $this->assertTrue(
            function_exists('exec') && trim((string) shell_exec('command -v cwebp')) !== '',
            'El binario cwebp debe estar disponible en el contenedor para validar la optimizacion real.'
        );

        config()->set('static_cms.rebuild_on_publish', false);
        config()->set('static_cms.media.base_path', $this->mediaBasePath);
        config()->set('static_cms.media.type_storage', 'copy');
        config()->set('static_cms.media.optimize', true);
        config()->set('static_cms.media.driver', 'cwebp');
        config()->set('static_cms.media.cwebp_path', 'cwebp');

        $this->putSourcePng("{$this->mediaBasePath}/imagen-publicada.png");

        $site = $this->createSite(Storage::disk('public')->path('static-site'));
        $post = Post::factory()->create([
            'site_id' => $site->short_name,
            'status' => Post::STATUS_PUBLISHED,
            'slug' => 'post-publicado-real',
            'title' => 'Post publicado real',
            'body' => 'Contenido publicado con imagen optimizada.',
            'type' => 'notebook',
            'published_at' => now(),
        ]);

        $exitCode = Artisan::call('site:build', [
            'site_id' => $site->short_name,
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        Storage::disk('public')->assertExists("static-site/{$post->slug}/index.html");
        Storage::disk('public')->assertExists("static-site/{$this->mediaBasePath}/imagen-publicada.webp");
    }

    private function createSite(string $distPath): Site
    {
        return Site::query()->create([
            'short_name' => 'ensayos',
            'long_name' => 'Bitacora de Ensayos',
            'slogan' => 'CMS FARO',
            'meta_description' => 'Sitio de pruebas de publicacion.',
            'domain' => 'https://example.test',
            'subdir' => null,
            'dist_path' => $distPath,
        ]);
    }

    private function putSourcePng(string $relativePath): void
    {
        $path = storage_path('app/public/'.$relativePath);

        File::ensureDirectoryExists(dirname($path));

        if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
            $image = imagecreatetruecolor(16, 16);
            $color = imagecolorallocate($image, 20, 120, 180);
            imagefill($image, 0, 0, $color);
            imagepng($image, $path);
            imagedestroy($image);

            return;
        }

        File::put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            true,
        ));
    }

    private function queuedCommandData(QueuedCommand $job): array
    {
        $reflection = new ReflectionClass($job);
        $property = $reflection->getProperty('data');
        $property->setAccessible(true);

        return $property->getValue($job);
    }
}
