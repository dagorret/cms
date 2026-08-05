<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Site;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteBuildSinglePostTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private string $distPath;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('static_cms.rebuild_on_publish', false);
        config()->set('static_cms.media.base_path', 'site-build-single-post-test-media');
        config()->set('static_cms.media.optimize', false);
        config()->set('static_cms.home_first_page_posts', 10);

        if (! Schema::hasColumn('posts', 'category')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->string('category')->nullable();
            });
        }

        Storage::fake('public');
        $this->distPath = Storage::disk('public')->path('static-site');
        $this->site = $this->createSite('ensayos', $this->distPath);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_post_compila_unicamente_el_html_indicado(): void
    {
        $target = $this->createPost(['slug' => 'objetivo']);
        $other = $this->createPost(['slug' => 'vecino']);
        $page = $this->createPost(['slug' => 'pagina', 'type' => 'page']);

        $this->assertSame(0, $this->buildPost($target));

        $this->assertFileExists($this->path('objetivo/index.html'));
        $this->assertFileDoesNotExist($this->path('vecino/index.html'));
        $this->assertFileDoesNotExist($this->path('pagina/index.html'));
        $this->assertNotNull($target->fresh()->static_built_at);
        $this->assertNull($other->fresh()->static_built_at);
        $this->assertNull($page->fresh()->static_built_at);
    }

    public function test_build_normal_recupera_un_dist_vacio_aunque_la_bd_marque_los_posts_como_compilados(): void
    {
        $first = $this->createPost([
            'slug' => 'publicado-uno',
            'title' => 'Publicado uno',
            'static_built_at' => Carbon::parse('2027-01-01'),
        ]);
        $second = $this->createPost([
            'slug' => 'publicado-dos',
            'title' => 'Publicado dos',
            'static_built_at' => Carbon::parse('2027-01-01'),
        ]);
        $draft = $this->createPost([
            'slug' => 'borrador',
            'title' => 'Borrador oculto',
            'status' => Post::STATUS_DRAFT,
            'static_built_at' => Carbon::parse('2027-01-01'),
        ]);

        $this->assertStringStartsWith(storage_path('framework/testing/'), $this->distPath);
        $this->assertNotSame('/var/www/dist', $this->distPath);
        $this->assertFileDoesNotExist($this->distPath);

        $this->assertSame(0, $this->buildNormal(), Artisan::output());

        $this->assertFileExists($this->path("{$first->slug}/index.html"));
        $this->assertFileExists($this->path("{$second->slug}/index.html"));
        $this->assertFileDoesNotExist($this->path("{$draft->slug}/index.html"));
        $this->assertFileExists($this->path('index.html'));
        $this->assertFileExists($this->path('feed.xml'));
        $this->assertFileExists($this->path('sitemaps/sitemap-index.xml'));
        $this->assertStringContainsString('Publicado uno', File::get($this->path('index.html')));
        $this->assertStringContainsString('publicado-dos', File::get($this->path('feed.xml')));
        $this->assertStringContainsString('/publicado-uno/', File::get($this->path('sitemaps/page-1.xml')));
        $this->assertStringNotContainsString('Borrador oculto', File::get($this->path('index.html')));
    }

    public function test_build_forzado_recompila_todos_los_posts_publicados(): void
    {
        $first = $this->createPost(['slug' => 'forzado-uno', 'title' => 'Forzado uno']);
        $second = $this->createPost(['slug' => 'forzado-dos', 'title' => 'Forzado dos']);
        $draft = $this->createPost([
            'slug' => 'forzado-borrador',
            'status' => Post::STATUS_DRAFT,
        ]);
        $this->buildAll();

        File::put($this->path("{$first->slug}/index.html"), 'marcador anterior uno');
        File::put($this->path("{$second->slug}/index.html"), 'marcador anterior dos');

        $this->assertSame(0, $this->buildAll(), Artisan::output());

        $this->assertStringContainsString('Forzado uno', File::get($this->path("{$first->slug}/index.html")));
        $this->assertStringContainsString('Forzado dos', File::get($this->path("{$second->slug}/index.html")));
        $this->assertFileDoesNotExist($this->path("{$draft->slug}/index.html"));
    }

    public function test_post_actualiza_solo_su_html_sin_tocar_contenido_ni_mtime_del_vecino_y_regenera_globales(): void
    {
        $target = $this->createPost([
            'slug' => 'objetivo',
            'title' => 'Objetivo original',
            'body' => 'Contenido original del objetivo',
        ]);
        $other = $this->createPost([
            'slug' => 'vecino',
            'title' => 'Vecino intacto',
            'body' => 'Contenido que no debe cambiar',
        ]);
        $page = $this->createPost([
            'slug' => 'pagina-intacta',
            'title' => 'Pagina intacta',
            'body' => 'Contenido de pagina que no debe cambiar',
            'type' => 'page',
        ]);
        $this->buildAll();

        $targetHtmlPath = $this->path('objetivo/index.html');
        $otherHtmlPath = $this->path('vecino/index.html');
        $pageHtmlPath = $this->path('pagina-intacta/index.html');
        $targetHtmlBefore = File::get($targetHtmlPath);
        $otherHtmlBefore = File::get($otherHtmlPath);
        $pageHtmlBefore = File::get($pageHtmlPath);
        $homeBefore = File::get($this->path('index.html'));
        $feedBefore = File::get($this->path('feed.xml'));
        $targetMtimeBefore = 1_600_000_000;
        $otherMtimeBefore = 1_600_000_100;
        $pageMtimeBefore = 1_600_000_200;

        $this->assertTrue(touch($targetHtmlPath, $targetMtimeBefore));
        $this->assertTrue(touch($otherHtmlPath, $otherMtimeBefore));
        $this->assertTrue(touch($pageHtmlPath, $pageMtimeBefore));

        $target->update([
            'title' => 'Objetivo actualizado',
            'body' => 'Contenido actualizado del objetivo',
        ]);

        $this->assertSame(0, $this->buildPost($target));

        clearstatcache(true, $targetHtmlPath);
        clearstatcache(true, $otherHtmlPath);
        clearstatcache(true, $pageHtmlPath);

        $targetHtmlAfter = File::get($targetHtmlPath);
        $otherHtmlAfter = File::get($otherHtmlPath);
        $pageHtmlAfter = File::get($pageHtmlPath);
        $homeAfter = File::get($this->path('index.html'));
        $feedAfter = File::get($this->path('feed.xml'));

        $this->assertNotSame($targetHtmlBefore, $targetHtmlAfter);
        $this->assertStringContainsString('Objetivo actualizado', $targetHtmlAfter);
        $this->assertStringContainsString('Contenido actualizado del objetivo', $targetHtmlAfter);
        $this->assertGreaterThan($targetMtimeBefore, File::lastModified($targetHtmlPath));

        $this->assertSame($otherHtmlBefore, $otherHtmlAfter);
        $this->assertSame($otherMtimeBefore, File::lastModified($otherHtmlPath));
        $this->assertSame($pageHtmlBefore, $pageHtmlAfter);
        $this->assertSame($pageMtimeBefore, File::lastModified($pageHtmlPath));

        $this->assertNotSame($homeBefore, $homeAfter);
        $this->assertStringContainsString('Objetivo actualizado', $homeAfter);
        $this->assertNotSame($feedBefore, $feedAfter);
        $this->assertStringContainsString('Objetivo actualizado', $feedAfter);
    }

    public function test_post_regenera_la_portada(): void
    {
        $post = $this->createPost(['title' => 'Titulo inicial']);
        $this->buildAll();

        $post->update(['title' => 'Titulo nuevo en portada']);

        $this->assertSame(0, $this->buildPost($post));
        $this->assertStringContainsString('Titulo nuevo en portada', File::get($this->path('index.html')));
        $this->assertStringNotContainsString('Titulo inicial', File::get($this->path('index.html')));
    }

    public function test_post_regenera_el_feed(): void
    {
        $post = $this->createPost(['title' => 'Feed inicial']);
        $this->buildAll();

        $post->update(['title' => 'Feed actualizado']);

        $this->assertSame(0, $this->buildPost($post));
        $this->assertStringContainsString('Feed actualizado', File::get($this->path('feed.xml')));
        $this->assertStringNotContainsString('Feed inicial', File::get($this->path('feed.xml')));
    }

    public function test_post_regenera_el_sitemap(): void
    {
        $post = $this->createPost(['slug' => 'slug-inicial']);
        $this->buildAll();

        $post->update(['slug' => 'slug-actualizado']);

        $this->assertSame(0, $this->buildPost($post));
        $sitemap = File::get($this->path('sitemaps/page-1.xml'));
        $this->assertStringContainsString('/slug-actualizado/', $sitemap);
        $this->assertStringNotContainsString('/slug-inicial/', $sitemap);
    }

    public function test_post_regenera_el_archivo_cronologico(): void
    {
        $post = $this->createPost([
            'title' => 'Archivo inicial',
            'created_at' => Carbon::parse('2026-01-10 12:00:00'),
        ]);
        $this->buildAll();

        $post->update(['title' => 'Archivo actualizado']);

        $this->assertSame(0, $this->buildPost($post));
        $archive = File::get($this->path('archive/2026/01/10/index.html'));
        $this->assertStringContainsString('Archivo actualizado', $archive);
        $this->assertStringNotContainsString('Archivo inicial', $archive);
    }

    public function test_post_regenera_el_indice_de_categoria(): void
    {
        $post = $this->createPost(['category' => 'Historia', 'title' => 'Categoria inicial']);
        $this->buildAll();

        $post->update(['title' => 'Categoria actualizada']);

        $this->assertSame(0, $this->buildPost($post));
        $payload = File::get($this->path('category/historia/page-1.json'));
        $this->assertStringContainsString('Categoria actualizada', $payload);
    }

    public function test_post_regenera_los_indices_de_tags_implementados(): void
    {
        $post = $this->createPost(['category' => 'Tecnologia', 'keywords' => 'php, laravel']);
        $this->buildAll();

        $post->update(['title' => 'Tag actualizado']);

        $this->assertSame(0, $this->buildPost($post));
        $payload = File::get($this->path('data/tags/tecnologia/page-1.json'));
        $this->assertStringContainsString('Tag actualizado', $payload);
        $this->assertStringContainsString('php', $payload);
    }

    public function test_cambio_de_titulo_aparece_en_portada_y_feed(): void
    {
        $post = $this->createPost(['title' => 'Antes del cambio']);
        $this->buildAll();

        $post->update(['title' => 'Despues del cambio']);
        $this->buildPost($post);

        $this->assertStringContainsString('Despues del cambio', File::get($this->path('index.html')));
        $this->assertStringContainsString('Despues del cambio', File::get($this->path('feed.xml')));
    }

    public function test_cambio_de_fecha_altera_el_orden_global(): void
    {
        $older = $this->createPost([
            'slug' => 'antes-antiguo',
            'created_at' => Carbon::parse('2025-01-01'),
        ]);
        $newer = $this->createPost([
            'slug' => 'antes-nuevo',
            'created_at' => Carbon::parse('2026-01-01'),
        ]);
        $this->buildAll();

        Post::query()->whereKey($older->id)->update(['created_at' => Carbon::parse('2027-01-01')]);

        $this->assertSame(0, $this->buildPost($older));
        $posts = data_get(json_decode(File::get($this->path('data/page-1.json')), true), 'posts');
        $this->assertSame([$older->id, $newer->id], array_column($posts, 'id'));
    }

    public function test_cambio_de_categoria_mueve_el_post_entre_indices(): void
    {
        $post = $this->createPost(['category' => 'Vieja']);
        $this->buildAll();
        $this->assertFileExists($this->path('category/vieja/page-1.json'));

        Post::query()->whereKey($post->id)->update(['category' => 'Nueva']);

        $this->assertSame(0, $this->buildPost($post));
        $this->assertFileDoesNotExist($this->path('category/vieja/page-1.json'));
        $this->assertFileExists($this->path('category/nueva/page-1.json'));
        $this->assertFileDoesNotExist($this->path('data/tags/vieja/page-1.json'));
        $this->assertFileExists($this->path('data/tags/nueva/page-1.json'));
    }

    public function test_cambio_de_slug_elimina_salida_anterior_y_actualiza_portada_y_sitemap(): void
    {
        $post = $this->createPost(['slug' => 'slug-viejo']);
        $this->buildAll();
        $this->assertFileExists($this->path('slug-viejo/.cms-faro-entry.json'));

        $post->update(['slug' => 'slug-nuevo']);

        $this->assertSame(0, $this->buildPost($post));
        $this->assertFileExists($this->path('slug-nuevo/index.html'));
        $this->assertFileDoesNotExist($this->path('slug-viejo'));
        $this->assertStringContainsString('/slug-nuevo/', File::get($this->path('index.html')));
        $this->assertStringContainsString('/slug-nuevo/', File::get($this->path('sitemaps/page-1.xml')));
    }

    public function test_published_a_draft_elimina_html_y_estructuras_globales(): void
    {
        $post = $this->createPost(['slug' => 'deja-de-publicarse', 'title' => 'Ya no visible']);
        $this->buildAll();

        $post->update(['status' => Post::STATUS_DRAFT]);

        $this->assertSame(0, $this->buildPost($post));
        $this->assertFileDoesNotExist($this->path('deja-de-publicarse'));
        $this->assertStringNotContainsString('Ya no visible', File::get($this->path('index.html')));
        $this->assertStringNotContainsString('deja-de-publicarse', File::get($this->path('feed.xml')));
        $this->assertStringNotContainsString('deja-de-publicarse', File::get($this->path('sitemaps/page-1.xml')));
    }

    public function test_draft_a_published_crea_html_y_estructuras_globales(): void
    {
        $post = $this->createPost([
            'slug' => 'se-publica',
            'title' => 'Ahora publicado',
            'status' => Post::STATUS_DRAFT,
        ]);
        $this->assertSame(0, $this->buildPost($post));
        $this->assertFileDoesNotExist($this->path('se-publica/index.html'));

        $post->update(['status' => Post::STATUS_PUBLISHED]);

        $this->assertSame(0, $this->buildPost($post));
        $this->assertFileExists($this->path('se-publica/index.html'));
        $this->assertStringContainsString('Ahora publicado', File::get($this->path('index.html')));
        $this->assertStringContainsString('se-publica', File::get($this->path('feed.xml')));
    }

    public function test_post_eliminado_limpia_su_salida_y_regenera_globales_usando_el_manifiesto(): void
    {
        $post = $this->createPost(['slug' => 'sera-eliminado', 'title' => 'Sera eliminado']);
        $postId = $post->id;
        $this->buildAll();
        $post->delete();

        $this->assertSame(0, $this->buildPostId($postId));
        $this->assertFileDoesNotExist($this->path('sera-eliminado'));
        $this->assertStringNotContainsString('Sera eliminado', File::get($this->path('index.html')));
        $this->assertStringNotContainsString('sera-eliminado', File::get($this->path('feed.xml')));
    }

    public function test_post_inexistente_devuelve_failure(): void
    {
        $this->assertSame(1, $this->buildPostId(999999));
    }

    public function test_post_de_otro_sitio_no_se_compila(): void
    {
        $otherDist = Storage::disk('public')->path('other-static-site');
        $otherSite = $this->createSite('otro', $otherDist);
        $post = $this->createPost(['site_id' => $otherSite->short_name, 'slug' => 'sitio-ajeno']);

        $this->assertSame(1, $this->buildPost($post));
        $this->assertFileDoesNotExist($this->path('sitio-ajeno/index.html'));
        $this->assertFileDoesNotExist($otherDist.'/sitio-ajeno/index.html');
    }

    public function test_static_built_at_se_actualiza_solo_para_el_post_objetivo(): void
    {
        Carbon::setTestNow('2026-08-05 10:00:00');
        $target = $this->createPost(['static_built_at' => null]);
        $other = $this->createPost(['static_built_at' => null]);

        $this->assertSame(0, $this->buildPost($target));
        $this->assertSame('2026-08-05 10:00:00', $target->fresh()->getRawOriginal('static_built_at'));
        $this->assertNull($other->fresh()->static_built_at);
    }

    public function test_los_otros_posts_conservan_su_static_built_at(): void
    {
        $target = $this->createPost();
        $other = $this->createPost(['static_built_at' => Carbon::parse('2025-03-04 05:06:07')]);

        Carbon::setTestNow('2026-08-05 11:00:00');
        $this->assertSame(0, $this->buildPost($target));

        $this->assertSame('2025-03-04 05:06:07', $other->fresh()->getRawOriginal('static_built_at'));
    }

    public function test_comando_post_termina_con_success_si_el_sitio_queda_coherente(): void
    {
        $post = $this->createPost(['title' => 'Compilacion coherente']);

        $this->assertSame(0, $this->buildPost($post), Artisan::output());
        $this->assertFileExists($this->path('index.html'));
        $this->assertFileExists($this->path('feed.xml'));
        $this->assertFileExists($this->path('sitemaps/sitemap-index.xml'));
        $this->assertFileExists($this->path('archive/index.html'));
    }

    private function createSite(string $shortName, string $distPath): Site
    {
        return Site::query()->create([
            'short_name' => $shortName,
            'long_name' => ucfirst($shortName).' de prueba',
            'slogan' => 'CMS FARO',
            'meta_description' => 'Sitio de pruebas.',
            'domain' => "https://{$shortName}.example.test",
            'subdir' => null,
            'dist_path' => $distPath,
        ]);
    }

    private function createPost(array $attributes = []): Post
    {
        static $sequence = 0;
        $sequence++;

        return Post::factory()->create(array_merge([
            'site_id' => $this->site->short_name,
            'status' => Post::STATUS_PUBLISHED,
            'slug' => "post-{$sequence}",
            'title' => "Post {$sequence}",
            'body' => "Contenido {$sequence}",
            'type' => 'post',
            'category' => 'General',
            'keywords' => 'prueba, incremental',
            'published_at' => Carbon::parse('2026-01-01'),
            'created_at' => Carbon::parse('2026-01-01')->addSeconds($sequence),
            'updated_at' => Carbon::parse('2026-01-01')->addSeconds($sequence),
        ], $attributes));
    }

    private function buildAll(): int
    {
        return Artisan::call('site:build', [
            'site_id' => $this->site->short_name,
            '--force' => true,
        ]);
    }

    private function buildNormal(): int
    {
        return Artisan::call('site:build', [
            'site_id' => $this->site->short_name,
        ]);
    }

    private function buildPost(Post $post): int
    {
        return $this->buildPostId((int) $post->getKey());
    }

    private function buildPostId(int $postId): int
    {
        return Artisan::call('site:build', [
            'site_id' => $this->site->short_name,
            '--post' => $postId,
        ]);
    }

    private function path(string $relativePath): string
    {
        return $this->distPath.'/'.ltrim($relativePath, '/');
    }
}
