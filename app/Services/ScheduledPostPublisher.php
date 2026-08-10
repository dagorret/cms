<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use App\Support\StaticBuildLauncher;
use App\Support\StaticBuildProcess;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ScheduledPostPublisher
{
    public function __construct(private readonly StaticBuildLauncher $buildLauncher) {}

    public function publish(Post $candidate): bool
    {
        $claim = $this->claim((int) $candidate->getKey());

        if ($claim === null) {
            return false;
        }

        [$post, $previousStaticBuiltAt] = $claim;

        try {
            $failure = $this->runBuild($post);

            if ($failure !== null) {
                throw new RuntimeException($failure);
            }
        } catch (Throwable $exception) {
            $this->restoreScheduledState($post, $previousStaticBuiltAt);

            throw new RuntimeException(
                "Fallo el build del post programado [{$post->getKey()}]: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        return true;
    }

    /** @return array{Post, mixed}|null */
    private function claim(int $postId): ?array
    {
        return DB::transaction(function () use ($postId): ?array {
            $post = Post::query()->lockForUpdate()->find($postId);

            if (! $post?->isDueForPublication()) {
                return null;
            }

            $previousStaticBuiltAt = $post->getRawOriginal('static_built_at');
            $post->status = Post::STATUS_PUBLISHED;
            $post->saveQuietly();

            return [$post, $previousStaticBuiltAt];
        });
    }

    protected function runBuild(Post $post): ?string
    {
        $site = $this->buildLauncher->resolveSiteForPost($post);

        if (! $site) {
            return 'No se pudo resolver el sitio asociado.';
        }

        $result = $this->buildLauncher->launch(
            $site,
            StaticBuildLauncher::SCOPE_POST,
            StaticBuildLauncher::MODE_NORMAL,
            (int) $post->getKey(),
        );

        return $result->successful() ? null : StaticBuildProcess::summary($result);
    }

    private function restoreScheduledState(Post $post, mixed $previousStaticBuiltAt): void
    {
        DB::transaction(function () use ($post, $previousStaticBuiltAt): void {
            $current = Post::query()->lockForUpdate()->find($post->getKey());

            if (! $current || $current->status !== Post::STATUS_PUBLISHED) {
                return;
            }

            $current->status = Post::STATUS_SCHEDULED;
            $current->static_built_at = $previousStaticBuiltAt;
            $current->saveQuietly();
        });
    }
}
