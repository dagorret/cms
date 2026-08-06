<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Site;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MarkdownEditorJsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $distPath;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('static_cms.rebuild_on_publish', false);
        config()->set('static_cms.media.base_path', 'markdown-editorjs-test-media');
        config()->set('static_cms.media.optimize', false);
        config()->set('static_cms.vite.build_path', public_path('build'));
        $this->distPath = storage_path('framework/testing/markdown-editorjs-'.bin2hex(random_bytes(5)));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->distPath);
        parent::tearDown();
    }

    public function test_guarda_recarga_edita_y_publica_bloques_visuales_y_markdown_mezclados(): void
    {
        $site = Site::factory()->create([
            'short_name' => 'markdown-editorjs',
            'domain' => 'https://markdown.example.test',
            'dist_path' => $this->distPath,
        ]);
        $firstSource = <<<'MARKDOWN'
## Prueba Markdown

Texto con **negrita**, *cursiva* y [enlace](https://example.com).

1. Primer elemento
2. Segundo elemento

- Elemento A
- Elemento B

- [x] Completo
- [ ] Pendiente

> Una cita.

| A | B |
|---|---|
| 1 | 2 |

~~tachado~~ y código `inline`.

![Imagen](https://example.com/imagen.jpg)

Texto con nota[^1].

[^1]: Nota de prueba.

<details>
<summary>Detalle</summary>
Contenido adicional.
</details>

La matriz $D > 0$ cumple:

$$
A x = b
$$

```php
echo 'FARO';
```

<script>alert('no')</script>
MARKDOWN;
        $secondSource = "Contenido del segundo bloque Markdown.\n\nUnicode: Córdoba 🚀";
        $payload = [
            'time' => 1_786_000_000_000,
            'version' => '2.31.0',
            'blocks' => [
                ['type' => 'header', 'data' => ['text' => 'Cabecera visual', 'level' => 2]],
                ['type' => 'paragraph', 'data' => ['text' => 'Párrafo visual anterior.']],
                ['type' => 'markdown', 'data' => ['source' => $firstSource]],
                ['type' => 'code', 'data' => ['code' => "echo 'Bloque visual';", 'languageCode' => 'php']],
                ['type' => 'markdown', 'data' => ['source' => $secondSource]],
            ],
        ];

        $post = Post::query()->create([
            'site_id' => $site->short_name,
            'slug' => 'documento-mixto',
            'title' => 'Documento mixto',
            'body' => $payload,
            'type' => Post::TYPE_POST,
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => now(),
            'has_math' => false,
        ]);

        $reloaded = Post::query()->findOrFail($post->id);
        $this->assertSame($firstSource, data_get($reloaded->body, 'blocks.2.data.source'));
        $this->assertSame($secondSource, data_get($reloaded->body, 'blocks.4.data.source'));
        $this->assertTrue($reloaded->has_math);

        $editedSource = $firstSource."\n\nEdición conservada después de recargar.";
        $editedPayload = $reloaded->body;
        data_set($editedPayload, 'blocks.2.data.source', $editedSource);
        $reloaded->update(['body' => $editedPayload]);

        $edited = Post::query()->findOrFail($post->id);
        $this->assertSame($editedSource, data_get($edited->body, 'blocks.2.data.source'));
        $this->assertSame($secondSource, data_get($edited->body, 'blocks.4.data.source'));
        $this->assertSame(0, Artisan::call('site:build', ['site_id' => $site->short_name, '--force' => true]), Artisan::output());

        $html = File::get($this->distPath.'/documento-mixto/index.html');
        $rawBody = (string) Post::query()->findOrFail($post->id)->getRawOriginal('body');
        $this->assertStringContainsString('<h2>Prueba Markdown</h2>', $html);
        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<blockquote>', $html);
        $this->assertStringContainsString('<div class="table-wrapper"', $html);
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<del>tachado</del>', $html);
        $this->assertStringContainsString('<img src="https://example.com/imagen.jpg" alt="Imagen"', $html);
        $this->assertStringContainsString('<details>', $html);
        $this->assertStringContainsString('<sup id="fnref-markdown-', $html);
        $this->assertStringContainsString('<a class="footnote-ref"', $html);
        $this->assertStringContainsString('<code class="language-php">', $html);
        $this->assertStringContainsString('$D &gt; 0$', $html);
        $this->assertStringContainsString('Edición conservada después de recargar.', $html);
        $this->assertStringContainsString('Párrafo visual anterior.', $html);
        $this->assertStringContainsString('Bloque visual', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('/vendor/katex/katex.min.js', $html);
        $this->assertStringContainsString('class="article-content min-w-0 w-full', $html);
        $this->assertStringContainsString('"type":"markdown"', $rawBody);
        $this->assertStringContainsString('## Prueba Markdown', $rawBody);
        $this->assertStringNotContainsString('<h2>Prueba Markdown</h2>', $rawBody);
        $this->assertStringNotContainsString('class="katex"', $rawBody);
    }

    public function test_documentos_anteriores_no_se_transforman_y_tool_esta_registrado(): void
    {
        $site = Site::factory()->create(['short_name' => 'editorjs-anterior']);
        $payload = [
            'blocks' => [
                ['type' => 'paragraph', 'data' => ['text' => 'Documento existente']],
                ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => ['Uno', 'Dos']]],
            ],
        ];
        $post = Post::factory()->create(['site_id' => $site->short_name, 'body' => $payload]);

        $this->assertSame($payload, $post->fresh()->body);
        $this->assertContains('markdown', config('filament-editorjs.profiles.default'));
        $this->assertNotEmpty(array_filter(
            FilamentAsset::getScripts(['faro-cms']),
            fn ($asset): bool => $asset->getId() === 'editorjs-markdown-tool',
        ));
    }

    public function test_hoja_publica_contiene_estilos_editoriales_y_defensas_de_overflow(): void
    {
        $css = File::get(resource_path('css/app.css'));

        foreach ([
            '.article-content blockquote',
            '.article-content ol',
            '.article-content ul',
            '.article-content .table-wrapper',
            '.article-content pre',
            '.article-content :not(pre) > code',
            '.article-content img',
            '.article-content details',
            '.article-content .footnotes',
            '.article-content .katex-display',
            '.dark .article-content blockquote',
        ] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }

        $this->assertStringContainsString('max-width: 100%', $css);
        $this->assertStringContainsString('overflow-x: auto', $css);
        $this->assertStringContainsString('min-width: max-content', $css);
        $this->assertStringContainsString('border-inline-start: 5px solid #8a6f2a', $css);
        $this->assertStringContainsString('.article-content blockquote p { margin-block: 1em; }', $css);
        $this->assertStringContainsString('.article-content blockquote > :first-child { margin-block-start: 0; }', $css);
        $this->assertStringContainsString('.article-content blockquote > :last-child { margin-block-end: 0; }', $css);
        $this->assertStringContainsString('.article-content blockquote .table-wrapper', $css);
        $this->assertStringContainsString('.article-content blockquote .katex-display', $css);
        $this->assertStringContainsString('.article-content blockquote pre > code { color: inherit; }', $css);
        $this->assertStringContainsString('.dark .article-content blockquote pre > code', $css);
        $this->assertStringContainsString('.article-content blockquote details { background:', $css);
        $this->assertStringContainsString('.dark .article-content blockquote details', $css);
        $this->assertStringContainsString('@media (max-width: 640px)', $css);
    }
}
