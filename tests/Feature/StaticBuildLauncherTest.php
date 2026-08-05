<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Actions\StaticBuildAction;
use App\Models\Post;
use App\Models\Site;
use App\Support\StaticBuildLauncher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

class StaticBuildLauncherTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private StaticBuildLauncher $launcher;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('static_cms.build.php_binary', '/test/php');
        Process::fake();
        Process::preventStrayProcesses();

        $this->site = $this->createSite('ensayos');
        $this->launcher = app(StaticBuildLauncher::class);
    }

    public function test_sitio_normal_ejecuta_compilacion_completa_sin_flags_adicionales(): void
    {
        $this->launcher->launch(
            $this->site,
            StaticBuildLauncher::SCOPE_SITE,
            StaticBuildLauncher::MODE_NORMAL,
        );

        $this->assertCommandRan(['/test/php', 'artisan', 'site:build', 'ensayos']);
    }

    public function test_sitio_forzado_agrega_force_a_la_compilacion_completa(): void
    {
        $this->launcher->launch(
            $this->site,
            StaticBuildLauncher::SCOPE_SITE,
            StaticBuildLauncher::MODE_FORCE,
        );

        $this->assertCommandRan(['/test/php', 'artisan', 'site:build', 'ensayos', '--force']);
    }

    public function test_post_normal_agrega_unicamente_el_id_del_post(): void
    {
        $post = $this->createPost($this->site, Post::STATUS_PUBLISHED);

        $this->launcher->launch(
            $this->site,
            StaticBuildLauncher::SCOPE_POST,
            StaticBuildLauncher::MODE_NORMAL,
            $post->id,
        );

        $this->assertCommandRan(['/test/php', 'artisan', 'site:build', 'ensayos', "--post={$post->id}"]);
    }

    public function test_post_forzado_agrega_post_y_force(): void
    {
        $post = $this->createPost($this->site, Post::STATUS_DRAFT);

        $this->launcher->launch(
            $this->site,
            StaticBuildLauncher::SCOPE_POST,
            StaticBuildLauncher::MODE_FORCE,
            $post->id,
        );

        $this->assertCommandRan(['/test/php', 'artisan', 'site:build', 'ensayos', "--post={$post->id}", '--force']);
    }

    public function test_rechaza_un_post_que_no_pertenece_al_sitio(): void
    {
        $otherSite = $this->createSite('otro');
        $post = $this->createPost($otherSite, Post::STATUS_PUBLISHED);

        try {
            $this->launcher->launch(
                $this->site,
                StaticBuildLauncher::SCOPE_POST,
                StaticBuildLauncher::MODE_NORMAL,
                $post->id,
            );

            $this->fail('Se esperaba una excepcion por sitio incorrecto.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('no pertenece al sitio', $exception->getMessage());
        }

        Process::assertNothingRan();
    }

    public function test_requiere_post_para_el_alcance_individual(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Debes seleccionar un post');

        $this->launcher->launch(
            $this->site,
            StaticBuildLauncher::SCOPE_POST,
            StaticBuildLauncher::MODE_NORMAL,
        );
    }

    public function test_las_opciones_de_posts_solo_incluyen_entradas_del_sitio(): void
    {
        $ownPost = $this->createPost($this->site, Post::STATUS_PUBLISHED);
        $otherPost = $this->createPost($this->createSite('otro'), Post::STATUS_PUBLISHED);

        $options = $this->launcher->postOptions($this->site);

        $this->assertArrayHasKey((string) $ownPost->id, $options);
        $this->assertArrayNotHasKey((string) $otherPost->id, $options);
    }

    public function test_la_accion_compartida_usa_el_modal_y_submit_unificados(): void
    {
        $action = StaticBuildAction::make();

        $this->assertSame('Lanzar Orquestador NASA', $action->getModalHeading());
        $this->assertSame('Iniciar compilación', $action->getModalSubmitActionLabel());
    }

    public function test_los_botones_de_sites_y_posts_usan_la_misma_accion_compartida(): void
    {
        $consumers = [
            app_path('Filament/Resources/Posts/Pages/ListPosts.php'),
            app_path('Filament/Resources/Posts/Tables/PostsTable.php'),
            app_path('Filament/Resources/Sites/Pages/ListSites.php'),
            app_path('Filament/Resources/Sites/Tables/SitesTable.php'),
        ];

        foreach ($consumers as $consumer) {
            $source = File::get($consumer);

            $this->assertStringContainsString('use App\\Filament\\Actions\\StaticBuildAction;', $source);
            $this->assertStringContainsString('StaticBuildAction::make(', $source);
            $this->assertStringNotContainsString('StaticBuildProcess::', $source);
            $this->assertStringNotContainsString("Artisan::", $source);
        }
    }

    public function test_desde_un_post_preselecciona_sitio_post_alcance_individual_y_modo_normal(): void
    {
        $post = $this->createPost($this->site, Post::STATUS_PUBLISHED);

        $this->assertSame([
            'site_id' => $this->site->id,
            'post_id' => $post->id,
            'scope' => StaticBuildLauncher::SCOPE_POST,
            'mode' => StaticBuildLauncher::MODE_NORMAL,
        ], StaticBuildAction::defaultsFor($post));
    }

    public function test_desde_un_sitio_preselecciona_sitio_alcance_completo_y_modo_normal(): void
    {
        $this->assertSame([
            'site_id' => $this->site->id,
            'post_id' => null,
            'scope' => StaticBuildLauncher::SCOPE_SITE,
            'mode' => StaticBuildLauncher::MODE_NORMAL,
        ], StaticBuildAction::defaultsFor($this->site));
    }

    private function assertCommandRan(array $expected): void
    {
        Process::assertRan(fn (PendingProcess $process): bool => $process->command === $expected
            && $process->path === base_path()
            && $process->timeout === 600);
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
            'dist_path' => storage_path("framework/testing/{$shortName}"),
        ]);
    }

    private function createPost(Site $site, string $status): Post
    {
        return Post::factory()->create([
            'site_id' => $site->short_name,
            'status' => $status,
            'type' => 'post',
        ]);
    }
}
