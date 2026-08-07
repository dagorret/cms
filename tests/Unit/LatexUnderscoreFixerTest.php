<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LatexUnderscoreFixer;
use Tests\TestCase;

class LatexUnderscoreFixerTest extends TestCase
{
    public function test_corrige_guion_bajo_escapado_en_math_inline_simple(): void
    {
        $result = LatexUnderscoreFixer::fixRawBody('Fórmula: $d\_i$ listo.');

        $this->assertSame(1, $result['count']);
        $this->assertSame('Fórmula: $d_i$ listo.', $result['raw']);
    }

    public function test_corrige_guiones_bajos_escapados_en_math_de_bloque(): void
    {
        $result = LatexUnderscoreFixer::fixRawBody('Antes.\n\n$$p\_i + k\_B$$\n\nDespués.');

        $this->assertSame(2, $result['count']);
        $this->assertSame('Antes.\n\n$$p_i + k_B$$\n\nDespués.', $result['raw']);
    }

    public function test_corrige_guion_bajo_escapado_en_delimitador_parentesis(): void
    {
        $result = LatexUnderscoreFixer::fixRawBody("Vale \\(v\\_*'\\) acá.");

        $this->assertSame(1, $result['count']);
        $this->assertSame("Vale \\(v_*'\\) acá.", $result['raw']);
    }

    public function test_corrige_guiones_bajos_escapados_en_delimitador_corchetes(): void
    {
        $result = LatexUnderscoreFixer::fixRawBody('\\[x\_1 + x\_2\\]');

        $this->assertSame(2, $result['count']);
        $this->assertSame('\\[x_1 + x_2\\]', $result['raw']);
    }

    public function test_no_toca_guion_bajo_escapado_fuera_de_math(): void
    {
        $text = 'Ejecuta foo\_bar antes de continuar.';
        $result = LatexUnderscoreFixer::fixRawBody($text);

        $this->assertSame(0, $result['count']);
        $this->assertSame($text, $result['raw']);
    }

    public function test_no_toca_guion_bajo_escapado_dentro_de_codigo_inline(): void
    {
        // El patrón parece math ($..$) pero está dentro de un code span: el code span
        // tiene prioridad y nunca debe interpretarse como una expresión LaTeX real.
        $text = 'Usa `$foo\_bar$` como variable, no es matemática.';
        $result = LatexUnderscoreFixer::fixRawBody($text);

        $this->assertSame(0, $result['count']);
        $this->assertSame($text, $result['raw']);
    }

    public function test_no_toca_guion_bajo_escapado_dentro_de_bloque_de_codigo_fenced(): void
    {
        $text = "Texto.\n\n```\n\$x\_1\$ no es math acá, es código.\n```\n\nFin.";
        $result = LatexUnderscoreFixer::fixRawBody($text);

        $this->assertSame(0, $result['count']);
        $this->assertSame($text, $result['raw']);
    }

    public function test_documento_mixto_conserva_todo_salvo_el_reemplazo_esperado(): void
    {
        $text = "# Título\n\n"
            ."Código: `foo\_bar` no cambia.\n\n"
            ."```\n\$k\_B\$ tampoco, es código.\n```\n\n"
            .'La fórmula $d\_i$ sí cambia.';

        $expected = "# Título\n\n"
            ."Código: `foo\_bar` no cambia.\n\n"
            ."```\n\$k\_B\$ tampoco, es código.\n```\n\n"
            .'La fórmula $d_i$ sí cambia.';

        $result = LatexUnderscoreFixer::fixRawBody($text);

        $this->assertSame(1, $result['count']);
        $this->assertSame($expected, $result['raw']);
    }

    public function test_payload_editorjs_corrige_solo_el_source_del_bloque_markdown(): void
    {
        $payload = [
            'time' => 1_700_000_000_000,
            'version' => '2.28.2',
            'blocks' => [
                [
                    'id' => 'block-1',
                    'type' => 'markdown',
                    'data' => ['source' => 'La masa $m\_e$ del electrón.'],
                ],
                [
                    'id' => 'block-2',
                    'type' => 'image',
                    'data' => ['file' => ['media_id' => 7, 'url' => '/storage/7/foo.png'], 'caption' => 'sin cambios \_'],
                ],
                [
                    'id' => 'block-3',
                    'type' => 'code',
                    'data' => ['code' => 'x\_1 = 1 # no tocar'],
                ],
            ],
        ];

        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $result = LatexUnderscoreFixer::fixRawBody($raw);

        $this->assertSame(1, $result['count']);
        $this->assertSame('editorjs', $result['type']);

        $decoded = json_decode($result['raw'], true);
        $this->assertSame('La masa $m_e$ del electrón.', $decoded['blocks'][0]['data']['source']);
        // Bloques no-markdown quedan intactos, incluido un \_ fuera de contexto math.
        $this->assertSame($payload['blocks'][1], $decoded['blocks'][1]);
        $this->assertSame($payload['blocks'][2], $decoded['blocks'][2]);
    }

    public function test_payload_editorjs_sin_cambios_devuelve_el_raw_original_intacto(): void
    {
        $payload = [
            'time' => 1_700_000_000_000,
            'version' => '2.28.2',
            'blocks' => [
                [
                    'id' => 'block-1',
                    'type' => 'markdown',
                    'data' => ['source' => 'Sin math escapada acá, $d_i$ ya está bien.'],
                ],
            ],
        ];

        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $result = LatexUnderscoreFixer::fixRawBody($raw);

        $this->assertSame(0, $result['count']);
        $this->assertSame($raw, $result['raw']);
    }
}
