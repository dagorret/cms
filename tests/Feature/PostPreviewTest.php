<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Services\PostPreviewService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class PostPreviewTest extends TestCase
{
    use RefreshDatabase;

    private string $previewDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'local');
        config()->set('static_cms.rebuild_on_publish', false);
        $this->withoutVite();
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->previewDirectory = storage_path('app/testing/post-previews-'.bin2hex(random_bytes(6)));
        $this->app->instance(PostPreviewService::class, new PostPreviewService($this->previewDirectory));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->previewDirectory);

        parent::tearDown();
    }

    public function test_invitado_no_puede_generar_ni_ver_previews(): void
    {
        $this->postJson(route('filament.dash.post-preview.store'), [
            'title' => 'No autorizado',
        ])->assertUnauthorized();

        $this->get(route('filament.dash.post-preview.show'))
            ->assertRedirect(route('filament.dash.auth.login'));
    }

    public function test_usuario_genera_y_ve_solo_su_preview_con_cabeceras_seguras(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $source = <<<'MARKDOWN'
## Preview sin guardar

> **Cita:** este contenido todavía no está en SQLite.

1. Uno
2. Dos

| Columna | Valor |
|---|---|
| A | B |

$$
A x = b
$$

<details>
<summary>Detalle</summary>

Contenido adicional Córdoba 🚀.

</details>
MARKDOWN;
        $body = [
            'blocks' => [
                ['type' => 'paragraph', 'data' => ['text' => 'Bloque visual']],
                ['type' => 'markdown', 'data' => ['source' => $source]],
                ['type' => 'code', 'data' => ['code' => "echo 'FARO';", 'languageCode' => 'php']],
            ],
        ];

        $response = $this->actingAs($userA)->postJson(route('filament.dash.post-preview.store'), [
            'title' => 'Título actual no guardado',
            'body' => $body,
            'type' => Post::TYPE_POST,
            'keywords' => 'preview, Unicode',
            'user_id' => $userB->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('url', fn (string $url): bool => str_contains($url, '/dash/post-preview?t='));

        $expectedPath = $this->previewDirectory.'/post-preview-'.$userA->id.'.html';
        $this->assertFileExists($expectedPath);
        $this->assertFileDoesNotExist($this->previewDirectory.'/post-preview-'.$userB->id.'.html');
        $this->assertFileDoesNotExist(public_path('post-preview-'.$userA->id.'.html'));
        $this->assertFileDoesNotExist(base_path('dist/post-preview-'.$userA->id.'.html'));

        $html = File::get($expectedPath);
        $this->assertStringContainsString('Título actual no guardado', $html);
        $this->assertStringContainsString('<h2>Preview sin guardar</h2>', $html);
        $this->assertStringContainsString('<blockquote>', $html);
        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<div class="table-wrapper"', $html);
        $this->assertStringContainsString('<pre', $html);
        $this->assertStringContainsString('<details>', $html);
        $this->assertStringContainsString('A x = b', $html);
        $this->assertStringContainsString('Córdoba 🚀', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow">', $html);
        $this->assertStringNotContainsString('<header class="border-b border-[#d8d0c3] bg-[#f7f3eb]/95', $html);
        $this->assertStringNotContainsString('menu.json', $html);

        $get = $this->get(route('filament.dash.post-preview.show', ['t' => 123]));
        $get->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Título actual no guardado');
        $cacheControl = (string) $get->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);

        $this->actingAs($userB)
            ->get(route('filament.dash.post-preview.show'))
            ->assertNotFound();
    }

    public function test_segunda_generacion_sobrescribe_el_mismo_archivo_sin_restos_temporales(): void
    {
        $user = User::factory()->create();
        $url = route('filament.dash.post-preview.store');

        $this->actingAs($user)->postJson($url, ['title' => 'Primera'])->assertOk();
        $path = $this->previewDirectory.'/post-preview-'.$user->id.'.html';
        $firstInode = fileinode($path);

        $this->postJson($url, ['title' => 'Segunda'])->assertOk();

        $this->assertStringContainsString('Segunda', File::get($path));
        $this->assertStringNotContainsString('Primera', File::get($path));
        $this->assertNotSame($firstInode, fileinode($path));
        $this->assertSame([], glob($this->previewDirectory.'/.post-preview-*') ?: []);
    }

    public function test_preview_no_crea_ni_actualiza_posts_ni_ejecuta_el_build(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'title' => 'Persistido',
            'static_built_at' => now()->subDay(),
        ]);
        $originalUpdatedAt = $post->updated_at->format('Y-m-d H:i:s.u');
        $originalBuiltAt = $post->static_built_at->format('Y-m-d H:i:s.u');
        $count = Post::query()->count();

        $this->actingAs($user)->postJson(route('filament.dash.post-preview.store'), [
            'title' => 'Cambio no guardado',
            'body' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Actual']]]],
        ])->assertOk();

        $post->refresh();
        $this->assertSame($count, Post::query()->count());
        $this->assertSame('Persistido', $post->title);
        $this->assertSame($originalUpdatedAt, $post->updated_at->format('Y-m-d H:i:s.u'));
        $this->assertSame($originalBuiltAt, $post->static_built_at->format('Y-m-d H:i:s.u'));
        $this->assertFileDoesNotExist($this->previewDirectory.'/.cms-faro-build.json');
    }

    public function test_rechaza_symlink_y_no_escribe_fuera_del_directorio(): void
    {
        if (! function_exists('symlink')) {
            $this->markTestSkipped('El sistema no soporta symlinks.');
        }

        $user = User::factory()->create();
        File::ensureDirectoryExists($this->previewDirectory);
        $outside = storage_path('framework/testing/post-preview-outside-'.bin2hex(random_bytes(4)).'.html');
        File::put($outside, 'intacto');
        $link = $this->previewDirectory.'/post-preview-'.$user->id.'.html';
        symlink($outside, $link);

        try {
            $this->actingAs($user)
                ->postJson(route('filament.dash.post-preview.store'), ['title' => '../ataque'])
                ->assertServerError();

            $this->assertSame('intacto', File::get($outside));
            $this->assertTrue(is_link($link));
        } finally {
            @unlink($link);
            File::delete($outside);
        }
    }

    public function test_get_devuelve_404_si_no_hay_archivo(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('filament.dash.post-preview.show'))
            ->assertNotFound();
    }

    public function test_formulario_de_creacion_muestra_boton_y_puente_de_sincronizacion(): void
    {
        config()->set('static_cms.default_editor', 'editorjs');

        $this->actingAs(User::factory()->create())
            ->get(PostResource::getUrl('create', panel: 'dash'))
            ->assertOk()
            ->assertSee('Vista previa')
            ->assertSee('data-faro-editorjs="body"', escape: false)
            ->assertSee('faroSyncEditorJs', escape: false);
    }

    public function test_ruta_productiva_es_estable_y_derivada_solo_del_id(): void
    {
        $path = (new PostPreviewService)->pathForUser(987654321);

        $this->assertSame(
            storage_path('app/previews/post-preview-987654321.html'),
            $path,
        );
    }
}
