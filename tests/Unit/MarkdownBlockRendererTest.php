<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\EditorJs\MarkdownBlockRenderer;
use App\Support\PostBodyMathDetector;
use App\Support\PostBodyRenderer;
use Tests\TestCase;

class MarkdownBlockRendererTest extends TestCase
{
    public function test_renderiza_markdown_semantico_y_conserva_formulas(): void
    {
        $source = <<<'MARKDOWN'
## Título

Texto con **negrita**, *cursiva* y [enlace](https://example.com).

1. Uno
2. Dos

- A
- B

> Cita

La matriz $D > 0$ cumple:

$$
A x = b
$$

```php
echo 'hola';
```
MARKDOWN;

        $html = (new MarkdownBlockRenderer)->render($this->block($source));
        $visibleText = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsString('<h2>Título</h2>', $html);
        $this->assertStringContainsString('<strong>negrita</strong>', $html);
        $this->assertStringContainsString('<em>cursiva</em>', $html);
        $this->assertStringContainsString('<a href="https://example.com">enlace</a>', $html);
        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<blockquote>', $html);
        $this->assertStringContainsString('<pre><code class="language-php">', $html);
        $this->assertStringContainsString('$D > 0$', $visibleText);
        $this->assertStringContainsString("$$\nA x = b\n$$", $visibleText);
    }

    public function test_renderiza_gfm_notas_al_pie_html_editorial_y_tablas_responsivas(): void
    {
        $source = <<<'MARKDOWN'
~~tachado~~

https://example.com/documento

![Descripción](https://example.com/imagen.jpg)

- [x] Completo
- [ ] Pendiente

| A | B |
|---|---|
| 1 | 2 |

Texto con nota[^1].

[^1]: Nota editorial.

<details>
<summary>Detalle</summary>
Contenido adicional.
</details>

<span class="dato-editorial">HTML confiable</span>
MARKDOWN;

        $html = (new MarkdownBlockRenderer)->render([
            'id' => 'bloque-editorial',
            ...$this->block($source),
        ]);

        $this->assertStringContainsString('<del>tachado</del>', $html);
        $this->assertStringContainsString('<a href="https://example.com/documento">https://example.com/documento</a>', $html);
        $this->assertStringContainsString('<img src="https://example.com/imagen.jpg" alt="Descripción"', $html);
        $this->assertStringContainsString('<input checked="" disabled="" type="checkbox">', $html);
        $this->assertStringContainsString('class="table-wrapper"', $html);
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<sup id="fnref-markdown-bloque-editorial-1">', $html);
        $this->assertStringContainsString('<a class="footnote-ref"', $html);
        $this->assertStringContainsString('id="fn-markdown-bloque-editorial-1"', $html);
        $this->assertStringContainsString('class="footnote-backref"', $html);
        $this->assertStringContainsString('<details>', $html);
        $this->assertStringContainsString('<summary>Detalle</summary>', $html);
        $this->assertStringContainsString('<span class="dato-editorial">HTML confiable</span>', $html);
    }

    public function test_blockquote_procesa_markdown_y_los_enlaces_markdown_inseguros_no_reciben_href(): void
    {
        $html = (new MarkdownBlockRenderer)->render($this->block(
            "> **Teorema:** Si todos los menores principales...\n\n[mal](javascript:alert(3))"
        ));

        $this->assertStringContainsString('<blockquote>', $html);
        $this->assertStringContainsString('<p><strong>Teorema:</strong> Si todos los menores principales...</p>', $html);
        $this->assertStringNotContainsString('&gt; **Teorema', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringContainsString('<a>mal</a>', $html);
    }

    public function test_fuente_vacia_no_emite_html_y_bloque_desconocido_sigue_su_fallback(): void
    {
        $this->assertSame('', (new MarkdownBlockRenderer)->render($this->block('')));

        $html = PostBodyRenderer::render([
            'blocks' => [['type' => 'todavia-desconocido', 'data' => ['value' => 'x']]],
        ]);

        $this->assertStringContainsString('Unknown block type:', $html);
    }

    public function test_notas_al_pie_de_bloques_sin_id_reciben_ids_unicos_sin_mutar_el_documento(): void
    {
        $markdown = "Texto[^1].\n\n[^1]: Nota.";
        $document = ['blocks' => [$this->block($markdown), $this->block($markdown)]];
        $original = $document;

        $html = PostBodyRenderer::render($document);

        $this->assertStringContainsString('id="fn-markdown-markdown-block-0-1"', $html);
        $this->assertStringContainsString('id="fn-markdown-markdown-block-1-1"', $html);
        $this->assertSame($original, $document);
    }

    public function test_detector_reconoce_matematica_y_omite_monedas_y_codigo_cercado(): void
    {
        $this->assertTrue(PostBodyMathDetector::containsMath(['blocks' => [$this->block('$D > 0$')]]));
        $this->assertTrue(PostBodyMathDetector::containsMath(['blocks' => [$this->block("$$\nA x = b\n$$")]]));
        $this->assertFalse(PostBodyMathDetector::containsMath(['blocks' => [$this->block('Cuesta $100 por mes')]]));
        $this->assertFalse(PostBodyMathDetector::containsMath(['blocks' => [$this->block("```text\n\$x = 1\$\n```")]]));
        $this->assertFalse(PostBodyMathDetector::containsMath(['blocks' => [['type' => 'code', 'data' => ['code' => '$x = 1$']]]]));
    }

    /** @return array{type:string,data:array{source:string}} */
    private function block(string $source): array
    {
        return ['type' => 'markdown', 'data' => ['source' => $source]];
    }
}
