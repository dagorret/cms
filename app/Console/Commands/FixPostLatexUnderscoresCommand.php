<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Post;
use App\Support\LatexUnderscoreFixer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPostLatexUnderscoresCommand extends Command
{
    protected $signature = 'posts:fix-latex-underscores
        {--dry-run : Solo audita y reporta, no escribe en la base de datos}';

    protected $description = 'Corrige guiones bajos escapados (\_) dentro de expresiones LaTeX en el body de los posts';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $totalReplacements = 0;
        $affectedPosts = 0;

        Post::query()
            ->select(['id', 'title', 'body'])
            ->orderBy('id')
            ->chunkById(200, function ($posts) use ($dryRun, &$totalReplacements, &$affectedPosts): void {
                foreach ($posts as $post) {
                    $raw = (string) $post->getRawOriginal('body');
                    $result = LatexUnderscoreFixer::fixRawBody($raw);

                    if ($result['count'] === 0) {
                        continue;
                    }

                    $affectedPosts++;
                    $totalReplacements += $result['count'];

                    $label = $result['count'] === 1 ? 'reemplazo' : 'reemplazos';
                    $this->line("Post {$post->id} — \"{$post->title}\" — {$result['count']} {$label} — [{$result['type']}]");

                    if ($dryRun) {
                        continue;
                    }

                    // Update directo por query builder: evita el observer de Post (rebuild
                    // estático) y el hook de EditorjsComponent que podría podar media, ya que
                    // esta corrección solo reescribe texto dentro de bloques math existentes.
                    DB::table('posts')->where('id', $post->id)->update(['body' => $result['raw']]);
                }
            });

        $this->newLine();

        $totalLabel = $totalReplacements === 1 ? 'reemplazo' : 'reemplazos';
        $postsLabel = $affectedPosts === 1 ? 'post' : 'posts';
        $this->info("Total: {$totalReplacements} {$totalLabel} en {$affectedPosts} {$postsLabel}.");

        if ($dryRun) {
            $this->comment('Modo --dry-run: no se escribió nada en la base de datos.');
        }

        return Command::SUCCESS;
    }
}
