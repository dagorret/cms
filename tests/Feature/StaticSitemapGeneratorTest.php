<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use App\Services\StaticSitemapGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class StaticSitemapGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private string $dist;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('static_cms.rebuild_on_publish', false);
        $this->dist = storage_path('framework/testing/sitemap-'.bin2hex(random_bytes(5)));
        File::ensureDirectoryExists($this->dist);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dist);
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_corta_posts_exactamente_en_el_limite_y_al_agregar_la_url_1001(): void
    {
        config()->set('static_cms.sitemap_urls_per_file', 1000);
        $site = Site::factory()->create(['domain' => 'localhost', 'subdir' => null]);
        $this->insertPosts($site, 1000);

        $first = (new StaticSitemapGenerator)->generate($site, $this->dist);
        $this->assertFileExists($this->dist.'/sitemaps/posts-1.xml');
        $this->assertFileDoesNotExist($this->dist.'/sitemaps/posts-2.xml');
        $this->assertSame(1000, $this->urlCount($this->dist.'/sitemaps/posts-1.xml'));
        $this->assertStringContainsString('http://localhost/sitemaps/posts-1.xml', File::get($this->dist.'/sitemap.xml'));
        $this->assertLessThanOrEqual(1000, $first['peak_buffer']);

        $this->insertPosts($site, 1, 1001);
        File::put($this->dist.'/sitemaps/posts-99.xml', '<stale/>');
        $second = (new StaticSitemapGenerator)->generate($site, $this->dist);

        $this->assertSame(1000, $this->urlCount($this->dist.'/sitemaps/posts-1.xml'));
        $this->assertSame(1, $this->urlCount($this->dist.'/sitemaps/posts-2.xml'));
        $this->assertFileDoesNotExist($this->dist.'/sitemaps/posts-99.xml');
        $this->assertLessThanOrEqual(1000, $second['peak_buffer']);
    }

    public function test_genera_tipos_canonicos_paginacion_subdirectorio_lastmod_y_robots(): void
    {
        Carbon::setTestNow('2026-08-05 12:00:00');
        config()->set('static_cms.sitemap_urls_per_file', 2);
        config()->set('static_cms.home_first_page_posts', 2);
        config()->set('static_cms.posts_per_home_page', 2);
        $site = Site::factory()->create(['domain' => 'https://example.test', 'subdir' => 'blog']);
        $category = Category::factory()->create(['site_id' => $site->id, 'slug' => 'ensayo', 'is_visible' => true]);

        foreach (range(1, 5) as $number) {
            Post::factory()->create([
                'site_id' => $site->short_name,
                'category_id' => $category->id,
                'slug' => "publicado-{$number}",
                'status' => Post::STATUS_PUBLISHED,
                'type' => Post::TYPE_POST,
                'published_at' => Carbon::parse('2026-08-01'),
                'updated_at' => Carbon::parse('2026-08-0'.min($number, 5)),
            ]);
        }
        Post::factory()->create(['site_id' => $site->short_name, 'slug' => 'pagina', 'type' => Post::TYPE_PAGE, 'status' => Post::STATUS_PUBLISHED]);
        Post::factory()->create(['site_id' => $site->short_name, 'slug' => 'borrador', 'status' => Post::STATUS_DRAFT]);
        Post::factory()->create(['site_id' => $site->short_name, 'slug' => 'futuro', 'status' => Post::STATUS_PUBLISHED, 'published_at' => now()->addDay()]);

        File::put($this->dist.'/robots.txt', "User-agent: *\nAllow: /\nSitemap: https://old.test/sitemap.xml\n");
        $stats = (new StaticSitemapGenerator)->generate($site, $this->dist);
        $all = collect(File::files($this->dist.'/sitemaps'))->map(fn ($file) => File::get($file->getPathname()))->join("\n");

        $this->assertGreaterThan(5, $stats['files']);
        $this->assertStringContainsString('<sitemapindex', File::get($this->dist.'/sitemap.xml'));
        $this->assertStringContainsString('https://example.test/blog/sitemaps/posts-1.xml', File::get($this->dist.'/sitemap.xml'));
        $this->assertStringContainsString('https://example.test/blog/page/2/', $all);
        $this->assertStringContainsString('https://example.test/blog/page/3/', $all);
        $this->assertStringContainsString('https://example.test/blog/category/ensayo/page/2/', $all);
        $this->assertStringContainsString('https://example.test/blog/category/ensayo/page/3/', $all);
        $this->assertStringContainsString('<lastmod>2026-08-05</lastmod>', $all);
        $this->assertStringNotContainsString('.json', $all);
        $this->assertStringNotContainsString('?page=', $all);
        $this->assertStringNotContainsString('borrador', $all);
        $this->assertStringNotContainsString('futuro', $all);
        $robots = File::get($this->dist.'/robots.txt');
        $this->assertSame(1, substr_count($robots, 'Sitemap:'));
        $this->assertStringContainsString('Sitemap: https://example.test/blog/sitemap.xml', $robots);
    }

    private function insertPosts(Site $site, int $count, int $start = 1): void
    {
        $now = now();
        foreach (array_chunk(range($start, $start + $count - 1), 250) as $numbers) {
            Post::query()->insert(array_map(fn (int $number): array => [
                'site_id' => $site->short_name,
                'title' => "Post {$number}",
                'slug' => "post-{$number}",
                'body' => 'Texto',
                'type' => Post::TYPE_POST,
                'status' => Post::STATUS_PUBLISHED,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $numbers));
        }
    }

    private function urlCount(string $path): int
    {
        return substr_count(File::get($path), '<url>');
    }
}
