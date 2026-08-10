<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\ScheduledPostPublisher;
use Illuminate\Console\Command;
use Throwable;

final class PublishScheduledPostsCommand extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Publica posts programados vencidos y reconstruye su salida estatica';

    public function handle(ScheduledPostPublisher $publisher): int
    {
        $postIds = Post::query()
            ->dueForPublication()
            ->orderBy('published_at')
            ->orderBy('id')
            ->pluck('id');

        if ($postIds->isEmpty()) {
            $this->info('No hay publicaciones programadas vencidas.');

            return self::SUCCESS;
        }

        $published = 0;
        $failed = 0;

        foreach ($postIds as $postId) {
            $post = Post::query()->find($postId);

            if (! $post) {
                continue;
            }

            try {
                if ($publisher->publish($post)) {
                    $published++;
                    $this->info("Publicado y compilado: #{$post->getKey()} {$post->title}");
                }
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
                $this->error($exception->getMessage());
            }
        }

        $this->comment("Resultado: {$published} publicados, {$failed} fallidos.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
