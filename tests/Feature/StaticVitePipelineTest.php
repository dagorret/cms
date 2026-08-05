<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

class StaticVitePipelineTest extends TestCase
{
    use RefreshDatabase;

    private string $buildPath;

    private string $distRelativePath;

    private string $distPath;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = bin2hex(random_bytes(5));
        $this->buildPath = storage_path("framework/testing/static-vite-build-{$suffix}");
        $this->distRelativePath = "storage/framework/testing/static-vite-dist-{$suffix}";
        $this->distPath = base_path($this->distRelativePath);

        config()->set('static_cms.rebuild_on_publish', false);
        config()->set('static_cms.media.base_path', 'static-vite-pipeline-media');
        config()->set('static_cms.media.optimize', false);
        config()->set('static_cms.vite.build_path', $this->buildPath);

        $this->site = Site::query()->create([
            'short_name' => 'vite-pipeline',
            'long_name' => 'Vite Pipeline',
            'slogan' => 'Pruebas',
            'meta_description' => 'Prueba de assets estaticos.',
            'domain' => 'https://example.test',
            'subdir' => null,
            'dist_path' => $this->distRelativePath,
        ]);

        Post::factory()->create([
            'site_id' => $this->site->short_name,
            'status' => Post::STATUS_PUBLISHED,
            'slug' => 'entrada-profunda',
            'title' => 'Entrada profunda',
            'body' => 'Contenido de prueba',
            'type' => Post::TYPE_POST,
            'category_id' => null,
            'published_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->buildPath);
        File::deleteDirectory($this->distPath);

        parent::tearDown();
    }

    public function test_el_comando_falla_antes_de_crear_dist_si_falta_el_manifest(): void
    {
        File::ensureDirectoryExists($this->buildPath);

        $this->assertSame(1, $this->build());
        $this->assertStringContainsString('npm run build', Artisan::output());
        $this->assertFileDoesNotExist($this->distPath);
    }

    public function test_publica_build_reemplaza_hashes_viejos_y_genera_urls_portables(): void
    {
        $this->writeViteBuild();
        File::ensureDirectoryExists($this->distPath.'/build/assets');
        File::put($this->distPath.'/build/assets/app-OLD.css', 'viejo');
        File::put($this->distPath.'/robots.txt', "User-agent: *\n");
        File::ensureDirectoryExists($this->distPath.'/carpeta-no-administrada');
        File::put($this->distPath.'/carpeta-no-administrada/index.html', 'externo');

        $this->assertSame(0, $this->build(), Artisan::output());

        $this->assertFileExists($this->distPath.'/build/manifest.json');
        $this->assertFileExists($this->distPath.'/build/assets/app-HASH.css');
        $this->assertFileExists($this->distPath.'/build/assets/app-HASH.js');
        $this->assertFileDoesNotExist($this->distPath.'/build/assets/app-OLD.css');
        $this->assertFileExists($this->distPath.'/robots.txt');
        $this->assertFileExists($this->distPath.'/carpeta-no-administrada/index.html');

        foreach (['index.html', 'entrada-profunda/index.html', 'archive/index.html', '404.html'] as $htmlPath) {
            $html = File::get($this->distPath.'/'.$htmlPath);
            $this->assertStringContainsString('href="/build/assets/app-HASH.css"', $html);
            $this->assertStringContainsString('src="/build/assets/app-HASH.js"', $html);
            $this->assertStringNotContainsString(base_path(), $html);
            $this->assertStringNotContainsString($this->distPath, $html);
            $this->assertStringNotContainsString('@vite', $html);
        }
    }

    public function test_exporta_sin_script_module_si_el_manifest_solo_produce_css(): void
    {
        $this->writeViteBuild(withJavaScript: false);

        $this->assertSame(0, $this->build(), Artisan::output());

        $html = File::get($this->distPath.'/entrada-profunda/index.html');
        $this->assertStringContainsString('href="/build/assets/app-HASH.css"', $html);
        $this->assertStringNotContainsString('<script type="module"', $html);
    }

    public function test_un_cambio_de_hash_fuerza_el_render_de_entradas_limpias(): void
    {
        $this->writeViteBuild(hash: 'PRIMERO');
        $this->assertSame(0, $this->build(), Artisan::output());

        $post = Post::query()->where('slug', 'entrada-profunda')->firstOrFail();
        $builtAt = $post->fresh()->static_built_at;

        $this->writeViteBuild(hash: 'SEGUNDO');
        $this->assertSame(0, $this->build(), Artisan::output());

        $html = File::get($this->distPath.'/entrada-profunda/index.html');
        $this->assertStringContainsString('/build/assets/app-SEGUNDO.css', $html);
        $this->assertStringNotContainsString('/build/assets/app-PRIMERO.css', $html);
        $this->assertFileExists($this->distPath.'/build/assets/app-SEGUNDO.css');
        $this->assertFileDoesNotExist($this->distPath.'/build/assets/app-PRIMERO.css');
        $this->assertGreaterThanOrEqual($builtAt, $post->fresh()->static_built_at);
    }

    public function test_un_cambio_de_configuracion_de_paginacion_invalida_el_html_estatico(): void
    {
        $this->writeViteBuild();
        $this->assertSame(0, $this->build(), Artisan::output());

        File::put($this->distPath.'/entrada-profunda/index.html', 'html obsoleto');
        config()->set('static_cms.home_first_page_posts', 3);

        $this->assertSame(0, $this->build(), Artisan::output());
        $this->assertStringNotContainsString(
            'html obsoleto',
            File::get($this->distPath.'/entrada-profunda/index.html'),
        );
    }

    public function test_una_sola_pagina_no_renderiza_controles_innecesarios(): void
    {
        $this->writeViteBuild();
        config()->set('static_cms.home_first_page_posts', 10);

        $this->assertSame(0, $this->build(), Artisan::output());

        $html = File::get($this->distPath.'/index.html');
        $this->assertStringNotContainsString('id="pagination-nav"', $html);
        $this->assertFileDoesNotExist($this->distPath.'/data/page-2.json');
    }

    public function test_portada_y_categoria_generan_y_navegan_multiples_paginas_json(): void
    {
        $this->writeViteBuild();
        config()->set('static_cms.home_first_page_posts', 2);
        config()->set('static_cms.posts_per_home_page', 2);

        Post::query()->delete();
        $category = Category::query()->create([
            'site_id' => $this->site->id,
            'name' => 'Paginada',
            'slug' => 'paginada',
        ]);

        foreach (range(1, 5) as $number) {
            Post::factory()->create([
                'site_id' => $this->site->short_name,
                'category_id' => $category->id,
                'status' => Post::STATUS_PUBLISHED,
                'slug' => "paginado-{$number}",
                'title' => "Paginado {$number}",
                'type' => Post::TYPE_POST,
                'published_at' => now(),
                'created_at' => now()->addSeconds($number),
            ]);
        }

        $this->assertSame(0, $this->build(), Artisan::output());

        $html = File::get($this->distPath.'/index.html');
        $homePageTwo = json_decode(File::get($this->distPath.'/data/page-2.json'), true, flags: JSON_THROW_ON_ERROR);
        $categoryPageTwo = json_decode(File::get($this->distPath.'/data/categories/paginada/page-2.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('href="/page/2/"', $html);
        $this->assertStringContainsString('data-json-url="/data/page-2.json"', $html);
        $this->assertStringContainsString('aria-label="Página siguiente"', $html);
        $this->assertStringContainsString('focus-visible:outline-[#0f4c5c]', $html);
        $this->assertFileExists($this->distPath.'/page/2/index.html');
        $this->assertFileExists($this->distPath.'/page/3/index.html');
        $this->assertStringContainsString('aria-current="page"', File::get($this->distPath.'/page/2/index.html'));
        $this->assertSame(2, $homePageTwo['currentPage']);
        $this->assertSame(3, $homePageTwo['totalPages']);
        $this->assertCount(2, $homePageTwo['posts']);
        $this->assertSame('paginada', $categoryPageTwo['category']);
        $this->assertSame(2, $categoryPageTwo['currentPage']);
        $this->assertSame(3, $categoryPageTwo['totalPages']);
        $this->assertCount(2, $categoryPageTwo['posts']);
        $this->assertFileExists($this->distPath.'/category/paginada/page/2/index.html');
    }

    public function test_build_incremental_regenera_la_paginacion_al_cambiar_el_total(): void
    {
        $this->writeViteBuild();
        config()->set('static_cms.home_first_page_posts', 2);
        config()->set('static_cms.posts_per_home_page', 2);

        Post::factory()->create([
            'site_id' => $this->site->short_name,
            'status' => Post::STATUS_PUBLISHED,
            'slug' => 'segunda-entrada',
            'type' => Post::TYPE_POST,
            'published_at' => now(),
        ]);
        $this->assertSame(0, $this->build(), Artisan::output());
        $this->assertStringNotContainsString('id="pagination-nav"', File::get($this->distPath.'/index.html'));

        Post::factory()->create([
            'site_id' => $this->site->short_name,
            'status' => Post::STATUS_PUBLISHED,
            'slug' => 'tercera-entrada',
            'type' => Post::TYPE_POST,
            'published_at' => now(),
        ]);
        $this->assertSame(0, $this->build(), Artisan::output());

        $this->assertStringContainsString('href="/page/2/"', File::get($this->distPath.'/index.html'));
        $this->assertFileExists($this->distPath.'/page/2/index.html');
        $this->assertFileExists($this->distPath.'/data/page-2.json');
    }

    public function test_rechaza_un_directorio_estructural_simbolico_sin_seguirlo(): void
    {
        $this->writeViteBuild();
        $outside = storage_path('framework/testing/static-vite-outside-'.bin2hex(random_bytes(5)));
        File::ensureDirectoryExists($outside);
        File::put($outside.'/sentinel.txt', 'no borrar');
        File::ensureDirectoryExists($this->distPath);
        symlink($outside, $this->distPath.'/archive');

        try {
            $this->assertSame(1, $this->build(), Artisan::output());
            $this->assertSame('no borrar', File::get($outside.'/sentinel.txt'));
        } finally {
            if (is_link($this->distPath.'/archive')) {
                unlink($this->distPath.'/archive');
            }

            File::deleteDirectory($outside);
        }
    }

    public function test_build_forzado_preserva_robots_y_republica_build(): void
    {
        $this->writeViteBuild();
        File::ensureDirectoryExists($this->distPath.'/build/assets');
        File::put($this->distPath.'/build/assets/preexistente.css', 'anterior');
        File::put($this->distPath.'/robots.txt', "User-agent: *\nDisallow:\n");
        File::ensureDirectoryExists($this->distPath.'/carpeta-no-administrada');
        File::put($this->distPath.'/carpeta-no-administrada/index.html', 'externo');

        $this->assertSame(0, $this->build(force: true), Artisan::output());

        $robots = File::get($this->distPath.'/robots.txt');
        $this->assertStringContainsString("User-agent: *\nDisallow:", $robots);
        $this->assertSame(1, substr_count($robots, 'Sitemap: https://example.test/sitemap.xml'));
        $this->assertFileExists($this->distPath.'/feed.xml');
        $this->assertFileExists($this->distPath.'/sitemap.xml');
        $this->assertFileExists($this->distPath.'/build/assets/app-HASH.css');
        $this->assertFileDoesNotExist($this->distPath.'/build/assets/preexistente.css');
        $this->assertSame('externo', File::get($this->distPath.'/carpeta-no-administrada/index.html'));
    }

    public function test_render_dinamico_sigue_usando_vite_y_no_las_etiquetas_estaticas(): void
    {
        $this->app->instance(Vite::class, new class
        {
            public function __invoke(array|string $entrypoints, ?string $buildDirectory = null): Htmlable
            {
                return new HtmlString('<link data-dynamic-vite href="/hot/app.css">');
            }
        });

        $html = view('site.layouts.app', [
            'site' => $this->site,
            'subdirUrl' => '',
            'generatedMenu' => '',
        ])->render();

        $this->assertStringContainsString('data-dynamic-vite', $html);
        $this->assertStringNotContainsString('/build/assets/app-HASH.css', $html);
    }

    public function test_javascript_implementa_json_primero_fallback_html_popstate_y_tema_tolerante(): void
    {
        $javascript = File::get(resource_path('js/app.js'));

        $this->assertStringContainsString("event.target.closest('a[data-json-url]')", $javascript);
        $this->assertStringContainsString('window.location.assign(htmlUrl)', $javascript);
        $this->assertStringContainsString("window.addEventListener('popstate'", $javascript);
        $this->assertStringContainsString("history.pushState({}, '', htmlUrl)", $javascript);
        $this->assertStringContainsString('/data/categories/', $javascript);
        $this->assertStringNotContainsString('/data/tags/', $javascript);
        $this->assertStringContainsString('!event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey', $javascript);
        $this->assertStringContainsString('localStorage.setItem(storageKey, theme)', $javascript);
        $this->assertStringContainsString("toggle.setAttribute('aria-pressed'", $javascript);
        $this->assertStringContainsString("toggle.setAttribute('aria-label'", $javascript);
    }

    public function test_single_no_contiene_el_texto_de_depuracion_y_header_no_duplica_github_o_rss(): void
    {
        $this->writeViteBuild();
        $this->assertSame(0, $this->build(), Artisan::output());

        $single = File::get($this->distPath.'/entrada-profunda/index.html');
        $this->assertDoesNotMatchRegularExpression('/>\s*hola\s*</i', $single);

        $layout = File::get(resource_path('views/site/layouts/app.blade.php'));
        preg_match('/<header\b.*?<\/header>/s', $layout, $header);
        preg_match('/<footer\b.*?<\/footer>/s', $layout, $footer);
        $this->assertStringNotContainsString('GitHub', $header[0]);
        $this->assertStringNotContainsString('RSS', $header[0]);
        $this->assertStringContainsString('GitHub', $footer[0]);
        $this->assertStringContainsString('RSS', $footer[0]);
    }

    public function test_menu_json_conserva_jerarquia_y_un_cambio_invalida_los_html(): void
    {
        $this->writeViteBuild();
        $menu = Menu::query()->create([
            'site_id' => $this->site->id,
            'name' => 'Principal',
            'location' => 'primary',
            'is_active' => true,
        ]);
        $parent = MenuItem::query()->create([
            'menu_id' => $menu->id,
            'label' => 'Secciones',
            'type' => 'internal_url',
            'url' => '/',
            'target' => '_self',
            'sort_order' => 1,
        ]);
        $child = MenuItem::query()->create([
            'menu_id' => $menu->id,
            'parent_id' => $parent->id,
            'label' => 'Entrada profunda',
            'type' => 'post',
            'post_id' => Post::query()->where('slug', 'entrada-profunda')->value('id'),
            'target' => '_self',
            'sort_order' => 1,
        ]);

        $this->assertSame(0, $this->build(), Artisan::output());
        $json = json_decode(File::get($this->distPath.'/menu.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Secciones', $json[0]['label']);
        $this->assertSame('Entrada profunda', $json[0]['children'][0]['label']);
        $this->assertStringContainsString('Entrada profunda', File::get($this->distPath.'/entrada-profunda/index.html'));

        File::put($this->distPath.'/entrada-profunda/index.html', 'html obsoleto');
        $child->update(['label' => 'Entrada actualizada']);
        $this->assertSame(0, $this->build(), Artisan::output());

        $this->assertStringNotContainsString('html obsoleto', File::get($this->distPath.'/entrada-profunda/index.html'));
        $this->assertStringContainsString('Entrada actualizada', File::get($this->distPath.'/entrada-profunda/index.html'));
        $this->assertStringContainsString('Entrada actualizada', File::get($this->distPath.'/menu.json'));
    }

    private function build(bool $force = false): int
    {
        return Artisan::call('site:build', array_filter([
            'site_id' => $this->site->short_name,
            '--force' => $force ?: null,
        ]));
    }

    private function writeViteBuild(bool $withJavaScript = true, string $hash = 'HASH'): void
    {
        File::deleteDirectory($this->buildPath);
        File::ensureDirectoryExists($this->buildPath.'/assets');
        File::put($this->buildPath."/assets/app-{$hash}.css", 'body{color:black}');

        $manifest = [
            'resources/css/app.css' => [
                'file' => "assets/app-{$hash}.css",
                'isEntry' => true,
            ],
        ];

        if ($withJavaScript) {
            File::put($this->buildPath."/assets/app-{$hash}.js", 'export default {}');
            $manifest['resources/js/app.js'] = [
                'file' => "assets/app-{$hash}.js",
                'isEntry' => true,
            ];
        }

        File::put(
            $this->buildPath.'/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }
}
