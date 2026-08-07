<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FixPostLatexUnderscoresCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reporta_pero_no_modifica_la_base_de_datos(): void
    {
        $post = Post::factory()->create([
            'has_math' => false,
            'body' => [
                'time' => 1_700_000_000_000,
                'version' => '2.28.2',
                'blocks' => [
                    [
                        'id' => 'block-1',
                        'type' => 'markdown',
                        'data' => ['source' => 'La energía $E\_0$ en reposo.'],
                    ],
                ],
            ],
        ]);

        $rawBefore = $post->getRawOriginal('body');

        $this->artisan('posts:fix-latex-underscores', ['--dry-run' => true])
            ->expectsOutputToContain("Post {$post->id}")
            ->expectsOutputToContain('1 reemplazo')
            ->assertExitCode(0);

        $post->refresh();
        $this->assertSame($rawBefore, $post->getRawOriginal('body'));
        $this->assertSame('La energía $E\_0$ en reposo.', data_get($post->body, 'blocks.0.data.source'));
    }

    public function test_modo_real_corrige_solo_los_posts_afectados_sin_tocar_updated_at_ni_static_built_at(): void
    {
        $affected = Post::factory()->create([
            'title' => 'Post con LaTeX roto',
            'has_math' => false,
            'static_built_at' => now()->subDays(10),
            'body' => [
                'time' => 1_700_000_000_000,
                'version' => '2.28.2',
                'blocks' => [
                    [
                        'id' => 'block-1',
                        'type' => 'markdown',
                        'data' => ['source' => 'La velocidad $v\_*$ límite.'],
                    ],
                ],
            ],
        ]);

        $unaffected = Post::factory()->create([
            'title' => 'Post sin problemas',
            'body' => [
                'time' => 1_700_000_000_000,
                'version' => '2.28.2',
                'blocks' => [
                    [
                        'id' => 'block-1',
                        'type' => 'markdown',
                        'data' => ['source' => 'Texto normal sin LaTeX.'],
                    ],
                ],
            ],
        ]);

        $affectedUpdatedAtBefore = DB::table('posts')->where('id', $affected->id)->value('updated_at');
        $affectedStaticBuiltAtBefore = DB::table('posts')->where('id', $affected->id)->value('static_built_at');
        $unaffectedRawBefore = $unaffected->getRawOriginal('body');

        $this->artisan('posts:fix-latex-underscores')
            ->expectsOutputToContain('Total: 1 reemplazo en 1 post.')
            ->assertExitCode(0);

        $affected->refresh();
        $unaffected->refresh();

        $this->assertSame('La velocidad $v_*$ límite.', data_get($affected->body, 'blocks.0.data.source'));
        $this->assertSame($unaffectedRawBefore, $unaffected->getRawOriginal('body'));

        // El fix usa un update directo por query builder: no debe tocar timestamps de build.
        $this->assertSame(
            (string) $affectedUpdatedAtBefore,
            (string) DB::table('posts')->where('id', $affected->id)->value('updated_at'),
        );
        $this->assertSame(
            (string) $affectedStaticBuiltAtBefore,
            (string) DB::table('posts')->where('id', $affected->id)->value('static_built_at'),
        );
    }

    public function test_no_guarda_si_el_post_no_tiene_cambios(): void
    {
        $post = Post::factory()->create([
            'body' => [
                'time' => 1_700_000_000_000,
                'version' => '2.28.2',
                'blocks' => [
                    [
                        'id' => 'block-1',
                        'type' => 'markdown',
                        'data' => ['source' => 'Ya está bien: $d_i$.'],
                    ],
                ],
            ],
        ]);

        $rawBefore = $post->getRawOriginal('body');

        $this->artisan('posts:fix-latex-underscores')
            ->doesntExpectOutputToContain("Post {$post->id}")
            ->expectsOutputToContain('Total: 0 reemplazos en 0 posts.')
            ->assertExitCode(0);

        $post->refresh();
        $this->assertSame($rawBefore, $post->getRawOriginal('body'));
    }

    public function test_corrige_posts_sin_has_math_marcado_porque_el_flag_no_es_confiable(): void
    {
        $post = Post::factory()->create([
            'has_math' => false,
            'body' => [
                'time' => 1_700_000_000_000,
                'version' => '2.28.2',
                'blocks' => [
                    [
                        'id' => 'block-1',
                        'type' => 'markdown',
                        'data' => ['source' => 'Con flag apagado: $k\_B$.'],
                    ],
                ],
            ],
        ]);

        $this->artisan('posts:fix-latex-underscores')
            ->expectsOutputToContain("Post {$post->id}")
            ->assertExitCode(0);

        $post->refresh();
        $this->assertSame('Con flag apagado: $k_B$.', data_get($post->body, 'blocks.0.data.source'));
    }
}
