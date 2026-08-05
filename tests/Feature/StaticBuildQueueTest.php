<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Site;
use App\Support\StaticBuildQueue;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

class StaticBuildQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('static_cms.rebuild_on_publish', true);
        Queue::fake();
        Storage::fake('public');
    }

    public function test_published_a_draft_encola_el_build_incremental_del_post_correcto(): void
    {
        $site = $this->createSite('transicion');
        $post = $this->createPostWithoutEvents($site, Post::STATUS_PUBLISHED);

        $post->update(['status' => Post::STATUS_DRAFT]);

        $this->assertPostBuildQueued($site, $post);
    }

    public function test_draft_creado_desde_cero_no_encola_trabajo(): void
    {
        $site = $this->createSite('draft-nuevo');

        Post::factory()->create([
            'site_id' => $site->short_name,
            'status' => Post::STATUS_DRAFT,
            'type' => 'post',
        ]);

        Queue::assertNothingPushed();
    }

    public function test_draft_sin_salida_previa_actualizado_no_encola_trabajo(): void
    {
        $site = $this->createSite('draft-sin-salida');
        $post = $this->createPostWithoutEvents($site, Post::STATUS_DRAFT);

        $post->update(['title' => 'Sigue siendo borrador']);

        Queue::assertNothingPushed();
    }

    public function test_post_publicado_actualizado_sigue_encolando_trabajo(): void
    {
        $site = $this->createSite('publicado-editado');
        $post = $this->createPostWithoutEvents($site, Post::STATUS_PUBLISHED);

        $post->update(['title' => 'Titulo actualizado']);

        $this->assertPostBuildQueued($site, $post);
    }

    public function test_post_eliminado_con_salida_previa_encola_la_limpieza_incremental(): void
    {
        $site = $this->createSite('eliminado');
        $post = $this->createPostWithoutEvents($site, Post::STATUS_DRAFT, [
            'static_built_at' => now()->subMinute(),
        ]);
        $postId = (int) $post->getKey();

        $post->delete();

        $this->assertPostBuildQueued($site, $post, $postId);
    }

    public function test_post_sin_un_sitio_existente_no_se_encola(): void
    {
        $post = Post::withoutEvents(fn (): Post => Post::factory()->create([
            'site_id' => 'sitio-inexistente',
            'status' => Post::STATUS_DRAFT,
        ]));

        $post->update(['status' => Post::STATUS_PUBLISHED]);

        Queue::assertNothingPushed();
    }

    public function test_no_encola_un_post_con_una_relacion_de_otro_sitio(): void
    {
        $ownSite = $this->createSite('sitio-propio');
        $otherSite = $this->createSite('sitio-ajeno');
        $post = $this->createPostWithoutEvents($ownSite, Post::STATUS_PUBLISHED);
        $post->setRelation('site', $otherSite);

        $this->assertFalse(StaticBuildQueue::queuePostSynchronization($post));
        Queue::assertNothingPushed();
    }

    public function test_actualizar_solo_static_built_at_no_vuelve_a_encolar(): void
    {
        $site = $this->createSite('sin-bucle');
        $post = $this->createPostWithoutEvents($site, Post::STATUS_PUBLISHED);

        $post->update(['static_built_at' => now()]);

        $this->assertSame(['static_built_at'], array_keys($post->getChanges()));
        Queue::assertNothingPushed();
    }

    public function test_los_tests_de_cola_no_apuntan_al_dist_real(): void
    {
        $site = $this->createSite('dist-aislado');

        $this->assertStringStartsWith(storage_path('framework/testing/'), (string) $site->dist_path);
        $this->assertNotSame('/var/www/dist', $site->dist_path);
        Queue::assertNothingPushed();
    }

    private function assertPostBuildQueued(Site $site, Post $post, ?int $postId = null): void
    {
        Queue::assertPushed(QueuedCommand::class, function (QueuedCommand $job) use ($site, $post, $postId): bool {
            $data = $this->queuedCommandData($job);

            return ($data[0] ?? null) === 'site:build'
                && ($data[1]['site_id'] ?? null) === $site->short_name
                && ($data[1]['--post'] ?? null) === ($postId ?? $post->getKey())
                && ($data[1]['--resource'] ?? null) === true;
        });
    }

    private function createSite(string $shortName): Site
    {
        return Site::query()->create([
            'short_name' => $shortName,
            'long_name' => ucfirst($shortName),
            'slogan' => 'CMS FARO',
            'meta_description' => 'Sitio de prueba',
            'domain' => "https://{$shortName}.example.test",
            'subdir' => null,
            'dist_path' => Storage::disk('public')->path("{$shortName}-static"),
        ]);
    }

    private function createPostWithoutEvents(Site $site, string $status, array $attributes = []): Post
    {
        return Post::withoutEvents(fn (): Post => Post::factory()->create(array_merge([
                'site_id' => $site->short_name,
                'status' => $status,
                'type' => 'post',
            ], $attributes)));
    }

    private function queuedCommandData(QueuedCommand $job): array
    {
        $reflection = new ReflectionClass($job);
        $property = $reflection->getProperty('data');
        $property->setAccessible(true);

        return $property->getValue($job);
    }
}
