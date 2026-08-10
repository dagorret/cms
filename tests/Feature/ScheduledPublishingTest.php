<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Posts\Schemas\PostForm;
use App\Models\Post;
use App\Models\Site;
use App\Services\PostPreviewService;
use App\Services\ScheduledPostPublisher;
use App\Support\StaticBuildLauncher;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ScheduledPublishingTest extends TestCase
{
    use RefreshDatabase;

    private string $originalPhpTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalPhpTimezone = date_default_timezone_get();
        config()->set('static_cms.rebuild_on_publish', false);
        config()->set('static_cms.build.php_binary', '/test/php');
        config()->set('static_cms.media.base_path', 'scheduled-publishing-media');
        config()->set('static_cms.media.optimize', false);
        Storage::fake('public');
        Process::fake();
        Process::preventStrayProcesses();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set($this->originalPhpTimezone);

        parent::tearDown();
    }

    public function test_selector_de_publicacion_usa_24_horas_sin_segundos_y_timezone_de_la_app(): void
    {
        config()->set('app.timezone', 'America/Argentina/Cordoba');
        $picker = PostForm::publicationDatePicker();

        $this->assertFalse($picker->isNative());
        $this->assertSame('d/m/Y H:i', $picker->getDisplayFormat());
        $this->assertFalse($picker->hasSeconds());
        $this->assertSame('America/Argentina/Cordoba', $picker->getTimezone());
    }

    public function test_scheduler_lo_ejecuta_cada_minuto_sin_superposicion(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'posts:publish-scheduled'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(15, $event->expiresAt);
    }

    public function test_post_futuro_y_borrador_vencido_no_se_publican(): void
    {
        Carbon::setTestNow('2026-08-10 12:00:00');
        $site = $this->site('futuro');
        $future = $this->createPost($site, Post::STATUS_SCHEDULED, now()->addDay(), 'mañana');
        $draft = $this->createPost($site, Post::STATUS_DRAFT, now()->subMinute(), 'borrador');

        $this->assertSame(0, Artisan::call('posts:publish-scheduled'));
        $this->assertSame(Post::STATUS_SCHEDULED, $future->fresh()->status);
        $this->assertSame(Post::STATUS_DRAFT, $draft->fresh()->status);
        Process::assertNothingRan();
    }

    public function test_fecha_modificada_y_cancelacion_se_respetan(): void
    {
        Carbon::setTestNow('2026-08-10 12:00:00');
        $site = $this->site('cambios');
        $rescheduled = $this->createPost($site, Post::STATUS_SCHEDULED, now()->addHour(), 'reprogramado');
        $cancelled = $this->createPost($site, Post::STATUS_SCHEDULED, now()->addMinute(), 'cancelado');
        $rescheduled->update(['published_at' => now()->addDays(2)]);
        $cancelled->update(['status' => Post::STATUS_DRAFT]);
        Carbon::setTestNow(now()->addDay());

        $this->assertSame(0, Artisan::call('posts:publish-scheduled'));
        $this->assertSame(Post::STATUS_SCHEDULED, $rescheduled->fresh()->status);
        $this->assertSame(Post::STATUS_DRAFT, $cancelled->fresh()->status);
        Process::assertNothingRan();
    }

    public function test_post_vencido_se_publica_dispara_build_incremental_y_es_idempotente(): void
    {
        Carbon::setTestNow('2026-08-10 12:00:00');
        $site = $this->site('vencido');
        $post = $this->createPost($site, Post::STATUS_SCHEDULED, now()->subMinutes(5), 'ya-es-hora');

        $this->assertSame(0, Artisan::call('posts:publish-scheduled'));
        $this->assertSame(Post::STATUS_PUBLISHED, $post->fresh()->status);
        $this->assertSame(0, Artisan::call('posts:publish-scheduled'));

        Process::assertRanTimes(
            fn (PendingProcess $process): bool => $process->command === [
                '/test/php', 'artisan', 'site:build', $site->short_name, "--post={$post->id}",
            ],
            1,
        );
    }

    public function test_fallo_del_build_restaura_programado_y_la_siguiente_ejecucion_reintenta(): void
    {
        Carbon::setTestNow('2026-08-10 12:00:00');
        $site = $this->site('reintento');
        $post = $this->createPost($site, Post::STATUS_SCHEDULED, now()->subMinute(), 'reintentable');
        $originalBuiltAt = now()->subDay();
        $post->updateQuietly(['static_built_at' => $originalBuiltAt]);
        $sequence = Process::sequence()
            ->push(Process::result(errorOutput: 'fallo controlado', exitCode: 1))
            ->push(Process::result(output: 'build correcto'));
        Process::fake(fn () => $sequence());

        $this->assertSame(1, Artisan::call('posts:publish-scheduled'));
        $this->assertSame(Post::STATUS_SCHEDULED, $post->fresh()->status);
        $this->assertTrue($post->fresh()->static_built_at->equalTo($originalBuiltAt));

        $this->assertSame(0, Artisan::call('posts:publish-scheduled'));
        $this->assertSame(Post::STATUS_PUBLISHED, $post->fresh()->status);
        Process::assertRanTimes(
            fn (PendingProcess $process): bool => in_array("--post={$post->id}", $process->command, true),
            2,
        );
    }

    public function test_timezone_configurada_define_el_instante_de_vencimiento_sin_desfase(): void
    {
        config()->set('app.timezone', 'America/Argentina/Cordoba');
        date_default_timezone_set('America/Argentina/Cordoba');
        Carbon::setTestNow(Carbon::parse('2026-08-10 22:59:00', 'America/Argentina/Cordoba'));
        $site = $this->site('timezone');
        $post = $this->createPost(
            $site,
            Post::STATUS_SCHEDULED,
            Carbon::parse('2026-08-10 23:00:00', 'America/Argentina/Cordoba'),
            'sin-desfase',
        );

        $this->assertSame(0, Artisan::call('posts:publish-scheduled'));
        $this->assertSame(Post::STATUS_SCHEDULED, $post->fresh()->status);
        Carbon::setTestNow(Carbon::parse('2026-08-10 23:00:00', 'America/Argentina/Cordoba'));
        $this->assertSame(0, Artisan::call('posts:publish-scheduled'));
        $this->assertSame(Post::STATUS_PUBLISHED, $post->fresh()->status);
        Process::assertRanTimes(fn (): bool => true, 1);
    }

    public function test_build_real_publica_en_dos_distintos_y_regenera_estructuras_globales(): void
    {
        Carbon::setTestNow('2026-08-10 12:00:00');
        $siteOne = $this->site('sitio-a');
        $siteTwo = $this->site('sitio-b');
        $postOne = $this->createPost($siteOne, Post::STATUS_SCHEDULED, now()->subMinute(), 'programado-a');
        $postTwo = $this->createPost($siteTwo, Post::STATUS_SCHEDULED, now()->subMinute(), 'programado-b');
        $this->useInProcessBuilds();

        $this->assertSame(0, Artisan::call('posts:publish-scheduled'), Artisan::output());

        foreach ([[$siteOne, $postOne], [$siteTwo, $postTwo]] as [$site, $post]) {
            $dist = (string) $site->dist_path;
            $this->assertSame(Post::STATUS_PUBLISHED, $post->fresh()->status);
            $this->assertFileExists($dist.'/'.$post->slug.'/index.html');
            $this->assertStringContainsString($post->title, File::get($dist.'/index.html'));
            $this->assertStringContainsString($post->slug, File::get($dist.'/feed.xml'));
            $this->assertStringContainsString('/'.$post->slug.'/', File::get($dist.'/sitemaps/posts-1.xml'));
        }

        $this->assertFileDoesNotExist($siteOne->dist_path.'/'.$postTwo->slug.'/index.html');
        $this->assertFileDoesNotExist($siteTwo->dist_path.'/'.$postOne->slug.'/index.html');
    }

    public function test_preview_de_post_programado_futuro_sigue_disponible_sin_publicarlo(): void
    {
        $site = $this->site('preview');
        $post = $this->createPost($site, Post::STATUS_SCHEDULED, now()->addDay(), 'preview-futuro');
        $directory = storage_path('app/testing/scheduled-preview-'.bin2hex(random_bytes(4)));

        try {
            $html = (new PostPreviewService($directory))->generate(1, $post->toArray());

            $this->assertStringContainsString($post->title, $html);
            $this->assertSame(Post::STATUS_SCHEDULED, $post->fresh()->status);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function useInProcessBuilds(): void
    {
        $publisher = new class(app(StaticBuildLauncher::class)) extends ScheduledPostPublisher
        {
            protected function runBuild(Post $post): ?string
            {
                $site = Site::query()
                    ->where('short_name', $post->site_id)
                    ->orWhere('id', $post->site_id)
                    ->first();

                if (! $site) {
                    return 'No se pudo resolver el sitio de prueba.';
                }

                $exitCode = Artisan::call('site:build', [
                    'site_id' => $site->short_name,
                    '--post' => $post->getKey(),
                ]);

                return $exitCode === 0 ? null : Artisan::output();
            }
        };

        $this->app->instance(ScheduledPostPublisher::class, $publisher);
    }

    private function site(string $shortName): Site
    {
        return Site::factory()->create([
            'short_name' => $shortName,
            'long_name' => ucfirst($shortName),
            'domain' => "https://{$shortName}.example.test",
            'dist_path' => Storage::disk('public')->path('scheduled-sites/'.$shortName),
        ]);
    }

    private function createPost(Site $site, string $status, Carbon $publishedAt, string $slug): Post
    {
        return Post::factory()->create([
            'site_id' => $site->short_name,
            'status' => $status,
            'published_at' => $publishedAt,
            'slug' => $slug,
            'title' => 'Publicación '.$slug,
            'type' => Post::TYPE_POST,
        ]);
    }
}
