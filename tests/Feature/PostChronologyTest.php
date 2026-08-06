<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use App\Services\PostChronology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PostChronologyTest extends TestCase
{
    use RefreshDatabase;

    public function test_resuelve_vecinos_por_fecha_y_desempata_por_id(): void
    {
        $site = $this->site('cronologia');
        $sharedDate = Carbon::parse('2026-08-06 12:00:00');
        $older = $this->createPost($site, 'anterior', $sharedDate->copy()->subDay());
        $sameDateBefore = $this->createPost($site, 'mismo-instante-anterior', $sharedDate);
        $current = $this->createPost($site, 'actual', $sharedDate);
        $sameDateAfter = $this->createPost($site, 'mismo-instante-siguiente', $sharedDate);
        $newer = $this->createPost($site, 'siguiente', $sharedDate->copy()->addDay());

        $chronology = app(PostChronology::class)->adjacentTo($current);

        $this->assertSame($sameDateBefore->id, $chronology['previous']?->id);
        $this->assertSame($sameDateAfter->id, $chronology['next']?->id);
        $this->assertNotSame($older->id, $chronology['previous']?->id);
        $this->assertNotSame($newer->id, $chronology['next']?->id);
    }

    public function test_excluye_otros_sitios_borradores_y_paginas_sin_filtrar_por_categoria(): void
    {
        $site = $this->site('principal');
        $otherSite = $this->site('otro');
        $currentDate = Carbon::parse('2026-08-06 12:00:00');
        $firstCategory = $this->category($site, 'Primera');
        $secondCategory = $this->category($site, 'Segunda');
        $thirdCategory = $this->category($site, 'Tercera');
        $previous = $this->createPost($site, 'anterior-valido', $currentDate->copy()->subDays(2), categoryId: $firstCategory->id);
        $current = $this->createPost($site, 'actual', $currentDate, categoryId: $secondCategory->id);
        $next = $this->createPost($site, 'siguiente-valido', $currentDate->copy()->addDays(2), categoryId: $thirdCategory->id);

        $this->createPost($site, 'borrador-cercano', $currentDate->copy()->subMinute(), status: Post::STATUS_DRAFT);
        $this->createPost($site, 'pagina-cercana', $currentDate->copy()->addMinute(), type: Post::TYPE_PAGE);
        $this->createPost($otherSite, 'otro-sitio-cercano', $currentDate->copy()->addSecond());

        $chronology = app(PostChronology::class)->adjacentTo($current);

        $this->assertSame($previous->id, $chronology['previous']?->id);
        $this->assertSame($next->id, $chronology['next']?->id);
    }

    public function test_primer_y_ultimo_post_omiten_el_lado_inexistente(): void
    {
        $site = $this->site('extremos');
        $first = $this->createPost($site, 'primero', Carbon::parse('2026-01-01'));
        $last = $this->createPost($site, 'ultimo', Carbon::parse('2026-01-02'));

        $firstChronology = app(PostChronology::class)->adjacentTo($first);
        $lastChronology = app(PostChronology::class)->adjacentTo($last);

        $this->assertNull($firstChronology['previous']);
        $this->assertSame($last->id, $firstChronology['next']?->id);
        $this->assertSame($first->id, $lastChronology['previous']?->id);
        $this->assertNull($lastChronology['next']);
    }

    private function site(string $shortName): Site
    {
        return Site::query()->create([
            'short_name' => $shortName,
            'long_name' => ucfirst($shortName),
            'slogan' => 'Pruebas',
            'meta_description' => 'Sitio de prueba.',
            'domain' => "https://{$shortName}.example.test",
            'subdir' => null,
            'dist_path' => 'dist',
        ]);
    }

    private function createPost(
        Site $site,
        string $slug,
        Carbon $publishedAt,
        string $status = Post::STATUS_PUBLISHED,
        string $type = Post::TYPE_POST,
        ?int $categoryId = null,
    ): Post {
        return Post::factory()->create([
            'site_id' => $site->short_name,
            'title' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'body' => 'Contenido',
            'status' => $status,
            'type' => $type,
            'category_id' => $categoryId,
            'published_at' => $publishedAt,
        ]);
    }

    private function category(Site $site, string $name): Category
    {
        return Category::query()->create([
            'site_id' => $site->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
        ]);
    }
}
