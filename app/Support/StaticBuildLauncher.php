<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Post;
use App\Models\Site;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

final class StaticBuildLauncher
{
    public const SCOPE_POST = 'post';

    public const SCOPE_SITE = 'site';

    public const MODE_NORMAL = 'normal';

    public const MODE_FORCE = 'force';

    public function launch(Site $site, string $scope, string $mode, ?int $postId = null): ProcessResult
    {
        if (! in_array($scope, [self::SCOPE_POST, self::SCOPE_SITE], true)) {
            throw new RuntimeException("Alcance de compilacion invalido [{$scope}].");
        }

        if (! in_array($mode, [self::MODE_NORMAL, self::MODE_FORCE], true)) {
            throw new RuntimeException("Modo de compilacion invalido [{$mode}].");
        }

        if (! $site->exists) {
            throw new RuntimeException('El sitio seleccionado no existe.');
        }

        $post = null;

        if ($scope === self::SCOPE_POST) {
            if ($postId === null || $postId < 1) {
                throw new RuntimeException('Debes seleccionar un post para compilar solo ese post.');
            }

            $post = $this->sitePosts($site)->whereKey($postId)->first();

            if (! $post) {
                throw new RuntimeException("El post [{$postId}] no pertenece al sitio [{$site->short_name}].");
            }
        }

        return StaticBuildProcess::runBuild(
            siteIdentifier: (string) $site->short_name,
            postId: $post ? (int) $post->getKey() : null,
            force: $mode === self::MODE_FORCE,
        );
    }

    public function postOptions(Site $site): array
    {
        return $this->sitePosts($site)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('title', 'id')
            ->mapWithKeys(fn (mixed $title, mixed $id): array => [
                (string) $id => "#{$id} · {$title}",
            ])
            ->all();
    }

    public function resolveSiteForPost(Post $post): ?Site
    {
        if ($post->relationLoaded('site') && $post->site) {
            return $post->site;
        }

        if (! filled($post->site_id)) {
            return null;
        }

        return Site::query()
            ->whereKey($post->site_id)
            ->orWhere('short_name', $post->site_id)
            ->first();
    }

    private function sitePosts(Site $site): Builder
    {
        $siteTokens = array_filter([
            (string) $site->getKey(),
            (string) $site->short_name,
            (string) $site->getAttribute('slug'),
        ]);

        return Post::query()->whereIn('site_id', array_values(array_unique($siteTokens)));
    }
}
