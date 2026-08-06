<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Post;
use App\Models\User;
use App\Services\PostPreviewService;
use Database\Seeders\PostSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

final class SeededPostEditorJsTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_seeder_crea_documentos_editorjs_hidratables_y_renderizables(): void
    {
        $this->seed(PostSeeder::class);

        $post = Post::query()->oldest('id')->firstOrFail();
        $raw = (string) $post->getRawOriginal('body');
        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($post->body);
        $this->assertIsInt($decoded['time']);
        $this->assertSame('2.28.2', $decoded['version']);
        $this->assertNotEmpty($decoded['blocks']);

        foreach ($decoded['blocks'] as $block) {
            $this->assertIsString($block['id']);
            $this->assertNotSame('', $block['id']);
            $this->assertArrayHasKey('type', $block);
            $this->assertArrayHasKey('data', $block);
        }

        $this->assertSame('markdown', data_get($decoded, 'blocks.0.type'));
        $this->assertIsString(data_get($decoded, 'blocks.0.data.source'));
        $this->assertStringStartsWith('## ', data_get($decoded, 'blocks.0.data.source'));
        $this->assertStringContainsString('<h2>', $post->renderedBodyHtml());
        $this->assertStringNotContainsString('"type":"markdown"', $post->renderedBodyHtml());
    }

    public function test_formulario_filament_hidrata_y_conserva_un_post_sembrado_sin_editarlo(): void
    {
        config()->set('static_cms.default_editor', 'editorjs');
        config()->set('static_cms.rebuild_on_publish', false);
        $this->seed(PostSeeder::class);

        $user = User::query()->where('email', 'dagorret@gmail.com')->firstOrFail();
        $post = Post::query()->oldest('id')->firstOrFail();
        $expectedBody = $post->body;
        $expectedRaw = (string) $post->getRawOriginal('body');

        Filament::setCurrentPanel(Filament::getPanel('dash'));

        Livewire::actingAs($user)
            ->test(EditPost::class, ['record' => $post->getRouteKey()])
            ->assertSet('data.body', $expectedBody)
            ->call('save')
            ->assertHasNoErrors();

        $post->refresh();
        $this->assertSame($expectedBody, $post->body);
        $this->assertSame($expectedRaw, $post->getRawOriginal('body'));
    }

    public function test_preview_compartida_renderiza_el_markdown_de_un_post_sembrado(): void
    {
        $this->seed(PostSeeder::class);
        $post = Post::query()->oldest('id')->firstOrFail();
        $directory = storage_path('app/testing/seeded-preview-'.bin2hex(random_bytes(5)));

        try {
            $service = new PostPreviewService($directory);
            $service->generate(1, [
                'title' => $post->title,
                'body' => $post->body,
                'type' => $post->type,
                'keywords' => $post->keywords,
            ]);

            $html = File::get($service->pathForUser(1));
            $this->assertStringContainsString('<h2>', $html);
            $this->assertStringContainsString(e($post->title), $html);
            $this->assertStringNotContainsString('"blocks"', $html);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_asset_del_boton_preview_esta_publicado(): void
    {
        $asset = public_path('js/faro-cms/post-preview.js');

        $this->assertFileExists($asset, 'Ejecute php artisan filament:assets para publicar los assets propios de Filament.');
        $this->assertStringContainsString('FaroPostPreview', File::get($asset));
    }
}
