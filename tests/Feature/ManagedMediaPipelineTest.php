<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Forms\Components\FaroEditorjsTextField;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use App\Services\PostPreviewService;
use App\Support\MediaReferenceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class ManagedMediaPipelineTest extends TestCase
{
    use RefreshDatabase;

    private string $distRoot;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config()->set('static_cms.rebuild_on_publish', false);
        config()->set('static_cms.media.optimize', false);
        config()->set('static_cms.media.type_storage', 'copy');
        config()->set('static_cms.vite.build_path', public_path('build'));
        $this->distRoot = storage_path('framework/testing/managed-media-'.bin2hex(random_bytes(5)));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->distRoot);

        parent::tearDown();
    }

    public function test_uploader_de_editorjs_devuelve_referencia_neutral_y_el_cms_la_sirve(): void
    {
        $post = Post::factory()->create();
        config()->set('livewire.temporary_file_upload.disk', 'public');
        $temporaryPath = FileUploadConfiguration::storeTemporaryFile(
            UploadedFile::fake()->image('faro-cms.png', 120, 80),
            'public',
        );
        $mediaUuid = $post->editJsSaveImageFromTempFile(
            TemporaryUploadedFile::createFromLivewire(basename($temporaryPath)),
        )->uuid;
        $response = json_decode(
            FaroEditorjsTextField::make('body')->handleUploadedAttachmentUrlRetrieval($mediaUuid),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $media = Media::query()->findOrFail($response['id']);

        $this->assertDatabaseHas('media', ['id' => $media->id, 'file_name' => 'faro-cms.png']);
        $this->assertSame($media->id, $response['id']);
        $this->assertStringStartsWith('/assets/media/'.$media->id.'/', $response['url']);
        $this->assertStringNotContainsString('example.test', $response['url']);
        $this->assertStringNotContainsString((string) config('app.url'), $response['url']);

        $this->actingAs(User::factory()->create())
            ->get($response['url'])
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_json_editorial_preview_y_conversion_usan_el_contrato_neutral(): void
    {
        $post = Post::factory()->create();
        $media = $this->attachImage($post, 'preview-inmediata.png');
        $canonical = app(MediaReferenceResolver::class)->canonicalUrl($media);
        $external = 'https://example.com/photo.jpg';
        $body = $this->imagePayload($media, $canonical, $external);

        $post->update(['body' => $body]);

        $rawBody = (string) $post->fresh()->getRawOriginal('body');
        $this->assertStringContainsString($canonical, $rawBody);
        $this->assertStringContainsString($external, $rawBody);
        $this->assertStringNotContainsString((string) config('app.url'), $rawBody);

        $previewDirectory = storage_path('app/testing/managed-media-previews-'.bin2hex(random_bytes(4)));

        try {
            $html = (new PostPreviewService($previewDirectory))->generate(1, [
                'title' => 'Preview con imagen',
                'body' => $post->fresh()->body,
            ]);
            $conversionUrl = app(MediaReferenceResolver::class)->canonicalUrl($media, 'preview');

            $this->assertStringContainsString('src="'.$conversionUrl.'"', $html);
            $this->assertStringContainsString('src="'.$external.'"', $html);
            $this->assertFileExists($media->getPath('preview'));

            $this->actingAs(User::factory()->create())
                ->get($conversionUrl)
                ->assertOk();
        } finally {
            File::deleteDirectory($previewDirectory);
        }
    }

    public function test_legacy_interno_se_normaliza_y_url_externa_permanece_intacta(): void
    {
        $site = Site::factory()->create(['domain' => 'https://sitio-publico.example.test']);
        $post = Post::factory()->create(['site_id' => $site->short_name]);
        $media = $this->attachImage($post, 'legacy.png');
        $legacy = 'https://sitio-publico.example.test/storage/'.$media->id.'/legacy.png';
        $external = 'https://example.com/storage/'.$media->id.'/legacy.png';
        $body = $this->imagePayload($media, $legacy, $external);
        unset($body['blocks'][0]['data']['file']['media_id']);

        DB::table('posts')->where('id', $post->id)->update([
            'body' => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);

        $normalized = $post->fresh()->body;
        $canonical = app(MediaReferenceResolver::class)->canonicalUrl($media);

        $this->assertSame($canonical, data_get($normalized, 'blocks.0.data.file.url'));
        $this->assertSame($media->id, data_get($normalized, 'blocks.0.data.file.media_id'));
        $this->assertSame($external, data_get($normalized, 'blocks.1.data.file.url'));
        $this->assertStringContainsString($legacy, (string) $post->fresh()->getRawOriginal('body'));

        $post->update(['body' => $normalized]);

        $this->assertStringContainsString($canonical, (string) $post->fresh()->getRawOriginal('body'));
        $this->assertStringNotContainsString($legacy, (string) $post->fresh()->getRawOriginal('body'));
    }

    public function test_build_y_build_individual_copian_original_conversion_y_generan_html_neutral_en_dos_sitios(): void
    {
        $siteOne = $this->site('sitio-uno', 'https://uno.example.test', $this->distRoot.'/uno');
        $siteTwo = $this->site('sitio-dos', 'https://dos.example.test', $this->distRoot.'/dos');
        $postOne = $this->publishedPost($siteOne, 'imagen-uno');
        $media = $this->attachImage($postOne, 'multisitio.png');
        $canonical = app(MediaReferenceResolver::class)->canonicalUrl($media);
        $postOne->update(['body' => $this->imagePayload($media, $canonical)]);
        $postTwo = $this->publishedPost($siteTwo, 'imagen-dos');
        $postTwo->update(['body' => $this->imagePayload($media, $canonical)]);

        foreach ([$siteOne, $siteTwo] as $site) {
            $this->assertSame(0, Artisan::call('site:build', [
                'site_id' => $site->short_name,
                '--force' => true,
            ]), Artisan::output());
        }

        $conversionUrl = app(MediaReferenceResolver::class)->canonicalUrl($media, 'preview');
        $conversionRelative = ltrim($conversionUrl, '/');
        $originalRelative = ltrim($canonical, '/');

        foreach ([[$siteOne, $postOne], [$siteTwo, $postTwo]] as [$site, $post]) {
            $dist = (string) $site->dist_path;
            $html = File::get($dist.'/'.$post->slug.'/index.html');

            $this->assertStringContainsString('src="'.$conversionUrl.'"', $html);
            $this->assertStringNotContainsString('src="https://uno.example.test', $html);
            $this->assertStringNotContainsString('src="https://dos.example.test', $html);
            $this->assertFileExists($dist.'/'.$originalRelative);
            $this->assertFileExists($dist.'/'.$conversionRelative);
        }

        File::delete($siteOne->dist_path.'/'.$conversionRelative);
        $this->assertSame(0, Artisan::call('site:build', [
            'site_id' => $siteOne->short_name,
            '--post' => $postOne->id,
        ]), Artisan::output());
        $this->assertFileExists($siteOne->dist_path.'/'.$conversionRelative);
    }

    public function test_rechaza_sufijos_de_media_con_traversal(): void
    {
        $resolver = app(MediaReferenceResolver::class);

        $this->assertFalse($resolver->isSafeSuffix('../secreto.txt'));
        $this->assertFalse($resolver->isSafeSuffix('conversions/../../secreto.txt'));
        $this->assertTrue($resolver->isSafeSuffix('conversions/preview.webp'));
    }

    private function attachImage(Post $post, string $name): Media
    {
        return $post
            ->addMedia(UploadedFile::fake()->image($name, 120, 80))
            ->toMediaCollection($post->editorjsMediaCollectionName());
    }

    /** @return array<string, mixed> */
    private function imagePayload(Media $media, string $url, ?string $external = null): array
    {
        $blocks = [[
            'type' => 'image',
            'data' => [
                'file' => ['url' => $url, 'media_id' => $media->id],
                'caption' => 'Imagen interna',
            ],
        ]];

        if ($external !== null) {
            $blocks[] = [
                'type' => 'image',
                'data' => [
                    'file' => ['url' => $external],
                    'caption' => 'Imagen externa',
                ],
            ];
        }

        return ['blocks' => $blocks];
    }

    private function site(string $shortName, string $domain, string $distPath): Site
    {
        return Site::factory()->create([
            'short_name' => $shortName,
            'domain' => $domain,
            'dist_path' => $distPath,
        ]);
    }

    private function publishedPost(Site $site, string $slug): Post
    {
        return Post::factory()->create([
            'site_id' => $site->short_name,
            'slug' => $slug,
            'status' => Post::STATUS_PUBLISHED,
            'type' => Post::TYPE_POST,
            'published_at' => now(),
        ]);
    }
}
