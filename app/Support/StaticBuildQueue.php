<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\Artisan;
use Throwable;

final class StaticBuildQueue
{
    /** @var array<string, true> */
    private static array $queuedPosts = [];

    public static function queuePost(Post $post): bool
    {
        if (! $post->isPublished()) {
            return false;
        }

        $siteCode = self::resolveSiteCode($post);

        if ($siteCode === null) {
            return false;
        }

        $queueKey = $siteCode.':'.$post->getKey();

        if (isset(self::$queuedPosts[$queueKey])) {
            return true;
        }

        Artisan::queue('site:build', [
            'site_id' => $siteCode,
            '--post' => $post->getKey(),
            '--resource' => true,
        ]);

        self::$queuedPosts[$queueKey] = true;

        return true;
    }

    public static function queuePostQuietly(Post $post): bool
    {
        try {
            return self::queuePost($post);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public static function resolveSiteCode(Post $post): ?string
    {
        if ($post->relationLoaded('site') && $post->site) {
            return $post->site->short_name;
        }

        if (! $post->site_id) {
            return null;
        }

        $siteCode = Site::query()
            ->where('short_name', $post->site_id)
            ->orWhere('id', $post->site_id)
            ->value('short_name');

        return $siteCode ?: (string) $post->site_id;
    }
}
