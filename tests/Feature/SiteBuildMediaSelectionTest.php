<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Site;
use App\Services\ScheduledPostPublisher;
use App\Support\MediaReferenceResolver;
use App\Support\StaticBuildLauncher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class SiteBuildMediaSelectionTest extends TestCase
{
    use RefreshDatabase;

    private string $buildOutput = '';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('static_cms.rebuild_on_publish', false);
        config()->set('static_cms.media.base_path', 'site-media-selection');
        config()->set('static_cms.media.type_storage', 'copy');
        config()->set('static_cms.media.optimize', false);
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_exporta_solo_medios_referenciados_por_publicados_del_site_y_sus_conversiones(): void
    {
        $siteA = $this->site('site-a');
        $siteB = $this->site('site-b');
        $published = $this->createPost($siteA, Post::STATUS_PUBLISHED, 'publicado-a');
        $required = $this->attach($published, 'necesario.png');
        $this->reference($published, $required);
        $orphan = $this->attach($published, 'huerfano.png');
        $draft = $this->createPost($siteA, Post::STATUS_DRAFT, 'borrador-a');
        $draftMedia = $this->attach($draft, 'borrador.png');
        $this->reference($draft, $draftMedia);
        $otherPost = $this->createPost($siteB, Post::STATUS_PUBLISHED, 'publicado-b');
        $otherMedia = $this->attach($otherPost, 'otro-site.png');
        $this->reference($otherPost, $otherMedia);

        $this->assertSame(0, $this->build($siteA, force: true), Artisan::output());

        $this->assertFileExists($this->distMediaPath($siteA, $required, $required->file_name));
        $this->assertFileExists($this->distMediaPath($siteA, $required, $this->conversionRelativePath($required)));
        $this->assertFileDoesNotExist($this->distMediaDirectory($siteA, $orphan));
        $this->assertFileDoesNotExist($this->distMediaDirectory($siteA, $draftMedia));
        $this->assertFileDoesNotExist($this->distMediaDirectory($siteA, $otherMedia));
    }

    public function test_registro_sin_archivo_no_usado_no_aborta_el_build(): void
    {
        $site = $this->site('huerfanos');
        $draft = $this->createPost($site, Post::STATUS_DRAFT, 'draft-roto');
        $unused = $this->attach($draft, 'favico.webp');
        File::deleteDirectory(dirname($unused->getPath()));

        $this->assertDatabaseHas('media', ['id' => $unused->id, 'file_name' => 'favico.webp']);
        $this->assertSame(0, $this->build($site, force: true), Artisan::output());
        $this->assertFileDoesNotExist($this->distMediaDirectory($site, $unused));
    }

    public function test_medio_referenciado_sin_archivo_emite_warning_continua_y_no_modifica_la_base(): void
    {
        $site = $this->site('roto');
        $post = $this->createPost($site, Post::STATUS_PUBLISHED, 'post-roto');
        $missing = $this->attach($post, 'faltante.png');
        $this->reference($post, $missing);
        $healthyPost = $this->createPost($site, Post::STATUS_PUBLISHED, 'post-sano');
        $healthy = $this->attach($healthyPost, 'sano.png');
        $this->reference($healthyPost, $healthy);
        $mediaRow = (array) DB::table('media')->where('id', $missing->id)->first();
        File::deleteDirectory(dirname($missing->getPath()));

        $this->assertSame(0, $this->build($site), Artisan::output());
        $output = $this->buildOutput;
        $this->assertStringContainsString('Falta el archivo fisico de un medio Spatie requerido', $output);
        $this->assertStringContainsString('Se omite este medio', $output);

        foreach ([
            'site='.$site->short_name,
            'media_id='.$missing->id,
            'file_name=faltante.png',
            'model_type='.Post::class,
            'model_id='.$post->id,
            'collection=body_images',
        ] as $context) {
            $this->assertStringContainsString($context, $output);
        }

        $this->assertFileDoesNotExist($this->distMediaDirectory($site, $missing));
        $this->assertFileExists($this->distMediaPath($site, $healthy, $healthy->file_name));
        $this->assertSame($mediaRow, (array) DB::table('media')->where('id', $missing->id)->first());
    }

    public function test_conversion_faltante_emite_warning_y_publica_el_original(): void
    {
        $site = $this->site('conversion-faltante');
        $post = $this->createPost($site, Post::STATUS_PUBLISHED, 'conversion-faltante');
        $media = $this->attach($post, 'original.png');
        $this->reference($post, $media);
        File::delete($media->getPath('preview'));

        $this->assertSame(0, $this->build($site), Artisan::output());
        $output = $this->buildOutput;

        $this->assertStringContainsString('Falta la conversion fisica [preview]', $output);
        $this->assertStringContainsString('media_id='.$media->id, $output);
        $this->assertStringContainsString('file_name=original.png', $output);
        $this->assertFileExists($this->distMediaPath($site, $media, $media->file_name));
        $this->assertFileDoesNotExist($this->distMediaPath($site, $media, $this->conversionRelativePath($media)));
    }

    public function test_build_incremental_publica_medios_y_repetirlo_es_idempotente(): void
    {
        $site = $this->site('incremental');
        $post = $this->createPost($site, Post::STATUS_PUBLISHED, 'incremental-media');
        $media = $this->attach($post, 'incremental.png');
        $this->reference($post, $media);

        $this->assertSame(0, $this->build($site, postId: $post->id), Artisan::output());
        $this->assertFileExists($this->distMediaPath($site, $media, $media->file_name));
        $this->assertSame(0, $this->build($site, postId: $post->id), Artisan::output());
        $this->assertFileExists($this->distMediaPath($site, $media, $media->file_name));
    }

    public function test_referencia_legacy_interna_selecciona_el_medio_sin_busqueda_de_strings_global(): void
    {
        $site = $this->site('legacy');
        $post = $this->createPost($site, Post::STATUS_PUBLISHED, 'legacy-media');
        $media = $this->attach($post, 'legacy.png');
        $legacyBody = $this->imageBody('https://legacy.example.test/storage/'.$media->id.'/legacy.png');
        unset($legacyBody['blocks'][0]['data']['file']['media_id']);
        DB::table('posts')->where('id', $post->id)->update([
            'body' => json_encode($legacyBody, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);

        $this->assertSame(0, $this->build($site), Artisan::output());
        $this->assertFileExists($this->distMediaPath($site, $media, $media->file_name));
    }

    public function test_referencia_a_registro_media_inexistente_falla_antes_de_publicar_salida_rota(): void
    {
        $site = $this->site('registro-ausente');
        $post = $this->createPost($site, Post::STATUS_PUBLISHED, 'registro-ausente');
        $body = $this->imageBody('/site-media-selection/999999/ausente.png', 999999);
        DB::table('posts')->where('id', $post->id)->update([
            'body' => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);

        $this->assertSame(1, $this->build($site));
        $output = $this->buildOutput;
        $this->assertStringContainsString('referencia el medio inexistente [999999]', $output);
        $this->assertStringContainsString('post ['.$post->id.']', $output);
    }

    public function test_medio_requerido_mediante_symlink_sigue_siendo_rechazado(): void
    {
        if (! function_exists('symlink')) {
            $this->markTestSkipped('El sistema no soporta symlinks.');
        }

        $site = $this->site('symlink');
        $post = $this->createPost($site, Post::STATUS_PUBLISHED, 'symlink-post');
        $media = $this->attach($post, 'symlink.png');
        $this->reference($post, $media);
        $mediaDirectory = dirname($media->getPath());
        $outside = storage_path('framework/testing/site-media-symlink-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($outside);
        File::copy($media->getPath(), $outside.'/'.$media->file_name);
        File::deleteDirectory($mediaDirectory);
        symlink($outside, $mediaDirectory);

        try {
            $this->assertSame(1, $this->build($site));
            $output = $this->buildOutput;
            $this->assertStringContainsString('enlace simbolico no permitido', $output);
            $this->assertStringContainsString('media_id='.$media->id, $output);
        } finally {
            @unlink($mediaDirectory);
            File::deleteDirectory($outside);
        }
    }

    public function test_publicacion_programada_exporta_su_medio_al_publicarse(): void
    {
        Carbon::setTestNow('2026-08-10 12:00:00');
        $site = $this->site('programado-media');
        $post = $this->createPost($site, Post::STATUS_SCHEDULED, 'programado-con-media');
        $post->updateQuietly(['published_at' => now()->subMinute()]);
        $media = $this->attach($post, 'programado.png');
        $this->reference($post, $media);
        $publisher = $this->inProcessScheduledPublisher();

        $this->assertTrue($publisher->publish($post));
        $this->assertSame(Post::STATUS_PUBLISHED, $post->fresh()->status);
        $this->assertFileExists($this->distMediaPath($site, $media, $media->file_name));
        $this->assertFileExists($site->dist_path.'/'.$post->slug.'/index.html');
    }

    public function test_publicacion_programada_no_vuelve_a_scheduled_si_falta_su_medio(): void
    {
        Carbon::setTestNow('2026-08-10 12:00:00');
        $site = $this->site('programado-sin-media');
        $post = $this->createPost($site, Post::STATUS_SCHEDULED, 'programado-sin-media');
        $post->updateQuietly(['published_at' => now()->subMinute()]);
        $media = $this->attach($post, 'programado-faltante.png');
        $this->reference($post, $media);
        File::deleteDirectory(dirname($media->getPath()));
        $publisher = $this->inProcessScheduledPublisher();

        $this->assertTrue($publisher->publish($post));
        $this->assertSame(Post::STATUS_PUBLISHED, $post->fresh()->status);
        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'file_name' => 'programado-faltante.png',
        ]);
        $this->assertFileDoesNotExist($this->distMediaDirectory($site, $media));
        $this->assertFileExists($site->dist_path.'/'.$post->slug.'/index.html');
    }

    private function inProcessScheduledPublisher(): ScheduledPostPublisher
    {
        return new class(app(StaticBuildLauncher::class)) extends ScheduledPostPublisher
        {
            protected function runBuild(Post $post): ?string
            {
                $site = Site::query()->where('short_name', $post->site_id)->first();
                $exitCode = $site ? Artisan::call('site:build', [
                    'site_id' => $site->short_name,
                    '--post' => $post->getKey(),
                ]) : 1;

                return $exitCode === 0 ? null : Artisan::output();
            }
        };
    }

    private function site(string $shortName): Site
    {
        return Site::factory()->create([
            'short_name' => $shortName,
            'long_name' => ucfirst($shortName),
            'domain' => "https://{$shortName}.example.test",
            'dist_path' => Storage::disk('public')->path('site-media-dist/'.$shortName),
        ]);
    }

    private function createPost(Site $site, string $status, string $slug): Post
    {
        return Post::factory()->create([
            'site_id' => $site->short_name,
            'status' => $status,
            'published_at' => now(),
            'slug' => $slug,
            'type' => Post::TYPE_POST,
        ]);
    }

    private function attach(Post $post, string $name): Media
    {
        return $post
            ->addMedia(UploadedFile::fake()->image($name, 120, 80))
            ->toMediaCollection($post->editorjsMediaCollectionName());
    }

    private function reference(Post $post, Media $media): void
    {
        $post->update(['body' => $this->imageBody(
            app(MediaReferenceResolver::class)->canonicalUrl($media),
            (int) $media->getKey(),
        )]);
    }

    /** @return array<string, mixed> */
    private function imageBody(string $url, ?int $mediaId = null): array
    {
        $file = ['url' => $url];

        if ($mediaId !== null) {
            $file['media_id'] = $mediaId;
        }

        return [
            'blocks' => [[
                'type' => 'image',
                'data' => ['file' => $file, 'caption' => 'Imagen'],
            ]],
        ];
    }

    private function build(Site $site, bool $force = false, ?int $postId = null): int
    {
        $output = new BufferedOutput;
        $exitCode = Artisan::call('site:build', array_filter([
            'site_id' => $site->short_name,
            '--force' => $force ?: null,
            '--post' => $postId,
        ]), $output);
        $this->buildOutput = $output->fetch();

        return $exitCode;
    }

    private function distMediaDirectory(Site $site, Media $media): string
    {
        return $site->dist_path.'/site-media-selection/'.$media->id;
    }

    private function distMediaPath(Site $site, Media $media, string $relativePath): string
    {
        return $this->distMediaDirectory($site, $media).'/'.$relativePath;
    }

    private function conversionRelativePath(Media $media): string
    {
        return 'conversions/'.basename($media->getPathRelativeToRoot('preview'));
    }
}
