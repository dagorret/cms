<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaticSeoTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private string $distPath;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('static_cms.rebuild_on_publish', false);
        config()->set('static_cms.media.base_path', 'static-seo-test-media');
        config()->set('static_cms.media.optimize', false);
        config()->set('static_cms.home_first_page_posts', 2);
        config()->set('static_cms.posts_per_home_page', 2);

        Storage::fake('public');
        $this->distPath = Storage::disk('public')->path('static-site');

        $this->site = Site::query()->create([
            'short_name' => 'ensayos',
            'long_name' => 'Bitácora de Ensayos',
            'slogan' => 'Notas sobre tecnología y sociedad',
            'meta_description' => 'Ensayos, análisis y reflexiones sobre tecnología, ciencia y sociedad.',
            'domain' => 'https://dagorret.com.ar',
            'subdir' => null,
            'dist_path' => $this->distPath,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_home_tiene_title_description_canonical_y_jsonld_correctos(): void
    {
        $this->createPost(['slug' => 'unico-post']);

        $this->assertSame(0, $this->buildAll(), Artisan::output());

        $html = File::get($this->path('index.html'));
        $this->assertStringContainsString('<title>Bitácora de Ensayos — Carlos Dagorret</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Ensayos, análisis y reflexiones sobre tecnología, ciencia y sociedad.">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://dagorret.com.ar/">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertStringContainsString('"@type":"WebSite"', $html);
        $this->assertStringContainsString('"@type":"Person"', $html);
    }

    public function test_post_tiene_canonical_absoluto_og_article_y_metadata_de_articulo(): void
    {
        $category = $this->category('Sistemas');
        $post = $this->createPost([
            'slug' => 'el-secreto-matematico',
            'title' => 'El secreto matemático',
            'category_id' => $category->id,
            'published_at' => Carbon::parse('2026-01-10 12:00:00'),
            'updated_at' => Carbon::parse('2026-01-12 09:00:00'),
        ]);

        $this->assertSame(0, $this->buildAll(), Artisan::output());

        $html = File::get($this->path("{$post->slug}/index.html"));
        $this->assertStringContainsString('<title>El secreto matemático — Carlos Dagorret</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://dagorret.com.ar/el-secreto-matematico/">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="https://dagorret.com.ar/el-secreto-matematico/">', $html);
        $this->assertStringContainsString('<meta property="article:published_time" content="2026-01-10T12:00:00+00:00">', $html);
        $this->assertStringContainsString('<meta property="article:section" content="Sistemas">', $html);
        $this->assertStringContainsString('"@type":"Article"', $html);
        $this->assertStringNotContainsString('http://127.0.0.1', $html);
    }

    public function test_page_tiene_title_y_canonical_propios_sin_duplicar_sufijo(): void
    {
        $page = $this->createPost([
            'slug' => 'about',
            'title' => 'Acerca de Carlos Dagorret',
            'type' => Post::TYPE_PAGE,
            'category_id' => null,
        ]);

        $this->assertSame(0, $this->buildAll(), Artisan::output());

        $html = File::get($this->path("{$page->slug}/index.html"));
        $this->assertStringContainsString('<title>Acerca de Carlos Dagorret — Carlos Dagorret</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://dagorret.com.ar/about/">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
        $this->assertStringContainsString('"@type":"WebPage"', $html);
        // El título no debe repetirse (bug detectado: "X | Sitio — Carlos Dagorret").
        $this->assertSame(1, substr_count($html, 'Carlos Dagorret</title>'));
    }

    public function test_page_no_aparece_en_home_ni_en_feed_pero_si_en_sitemap(): void
    {
        $this->createPost(['slug' => 'post-normal']);
        $this->createPost([
            'slug' => 'politica-de-privacidad',
            'title' => 'Política de privacidad',
            'type' => Post::TYPE_PAGE,
            'category_id' => null,
        ]);

        $this->assertSame(0, $this->buildAll(), Artisan::output());

        // La page puede legítimamente aparecer en el menú de navegación (política deseada:
        // "menús cuando corresponda"), así que se verifica el listado real de "Últimos
        // artículos" vía el JSON que alimenta esa sección, no el HTML completo de la home.
        $homeListing = json_decode(File::get($this->path('data/page-1.json')), true, flags: JSON_THROW_ON_ERROR);
        $listedSlugs = array_column($homeListing['posts'], 'slug');
        $this->assertNotContains('politica-de-privacidad', $listedSlugs);
        $this->assertContains('post-normal', $listedSlugs);

        $feed = File::get($this->path('feed.xml'));
        $this->assertStringNotContainsString('politica-de-privacidad', $feed);

        $sitemapFiles = collect(File::files($this->path('sitemaps')))
            ->map(fn ($file) => File::get($file->getPathname()))
            ->join("\n");
        $this->assertStringContainsString('https://dagorret.com.ar/politica-de-privacidad/', $sitemapFiles);
    }

    public function test_404_tiene_noindex_y_no_incluye_canonical_enganosa(): void
    {
        $this->createPost(['slug' => 'cualquier-post']);

        $this->assertSame(0, $this->buildAll(), Artisan::output());

        $html = File::get($this->path('404.html'));
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        $this->assertStringNotContainsString('rel="canonical"', $html);
        $this->assertStringNotContainsString('og:url', $html);
    }

    public function test_app_url_administrativo_no_contamina_urls_publicas(): void
    {
        config()->set('app.url', 'http://127.0.0.1:8000');
        $post = $this->createPost(['slug' => 'post-contra-app-url']);

        $this->assertSame(0, $this->buildAll(), Artisan::output());

        $html = File::get($this->path("{$post->slug}/index.html"));
        $this->assertStringNotContainsString('127.0.0.1', $html);
        $this->assertStringNotContainsString('8000', $html);
        $this->assertStringContainsString('https://dagorret.com.ar/post-contra-app-url/', $html);
    }

    public function test_subdir_publico_se_respeta_en_canonical_y_sitemap(): void
    {
        $this->site->update(['subdir' => 'blog']);
        $post = $this->createPost(['slug' => 'post-con-subdir']);

        $this->assertSame(0, $this->buildAll(), Artisan::output());

        $html = File::get($this->path("{$post->slug}/index.html"));
        $this->assertStringContainsString('<link rel="canonical" href="https://dagorret.com.ar/blog/post-con-subdir/">', $html);

        $sitemapIndex = File::get($this->path('sitemap.xml'));
        $this->assertStringContainsString('https://dagorret.com.ar/blog/sitemaps/', $sitemapIndex);
    }

    public function test_description_meta_no_contiene_html_ni_markdown(): void
    {
        $post = $this->createPost([
            'slug' => 'post-con-formato',
            'body' => "## Encabezado\n\nTexto con **negrita** y [un enlace](https://example.test) de prueba.",
        ]);

        $this->assertSame(0, $this->buildAll(), Artisan::output());

        $html = File::get($this->path("{$post->slug}/index.html"));
        preg_match('/<meta name="description" content="([^"]*)">/', $html, $matches);
        $this->assertNotEmpty($matches);
        $description = $matches[1];
        $this->assertStringNotContainsString('<', $description);
        $this->assertStringNotContainsString('**', $description);
        $this->assertStringNotContainsString('##', $description);
    }

    public function test_twitter_card_es_summary_sin_imagen_por_no_existir_fuente_confiable(): void
    {
        $post = $this->createPost(['slug' => 'post-sin-imagen']);

        $this->assertSame(0, $this->buildAll(), Artisan::output());

        $html = File::get($this->path("{$post->slug}/index.html"));
        $this->assertStringContainsString('<meta name="twitter:card" content="summary">', $html);
        $this->assertStringNotContainsString('og:image', $html);
        $this->assertStringNotContainsString('twitter:image', $html);
    }

    public function test_paginacion_no_canonicaliza_pagina_2_hacia_pagina_1(): void
    {
        $this->createPost(['slug' => 'post-1', 'published_at' => Carbon::parse('2026-01-01')]);
        $this->createPost(['slug' => 'post-2', 'published_at' => Carbon::parse('2026-01-02')]);
        $this->createPost(['slug' => 'post-3', 'published_at' => Carbon::parse('2026-01-03')]);

        $this->assertSame(0, $this->buildAll(), Artisan::output());

        $page2 = File::get($this->path('page/2/index.html'));
        $this->assertStringContainsString('<link rel="canonical" href="https://dagorret.com.ar/page/2/">', $page2);
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $page2);
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
            'body' => "Contenido de prueba número {$sequence}.",
            'type' => Post::TYPE_POST,
            'category_id' => null,
            'keywords' => 'prueba',
            'published_at' => Carbon::parse('2026-01-01')->addSeconds($sequence),
            'created_at' => Carbon::parse('2026-01-01')->addSeconds($sequence),
            'updated_at' => Carbon::parse('2026-01-01')->addSeconds($sequence),
        ], $attributes));
    }

    private function category(string $name): Category
    {
        return Category::query()->firstOrCreate([
            'site_id' => $this->site->id,
            'slug' => str($name)->slug()->toString(),
        ], [
            'name' => $name,
            'is_visible' => true,
        ]);
    }

    private function buildAll(): int
    {
        return Artisan::call('site:build', [
            'site_id' => $this->site->short_name,
            '--force' => true,
        ]);
    }

    private function path(string $relativePath): string
    {
        return $this->distPath.'/'.ltrim($relativePath, '/');
    }
}
