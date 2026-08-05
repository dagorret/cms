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

    /** @var array<string, true> */
    private static array $queuedSites = [];

    public static function queuePost(Post $post): bool
    {
        if (! $post->isPublished()) {
            return false;
        }

        return self::enqueuePostBuild($post);
    }

    public static function queuePostSynchronization(Post $post): bool
    {
        $wasPublished = ($post->getPrevious()['status'] ?? null) === Post::STATUS_PUBLISHED;
        $hasPreviousStaticOutput = filled($post->getRawOriginal('static_built_at'));

        if (! $post->isPublished() && ! $wasPublished && ! $hasPreviousStaticOutput) {
            return false;
        }

        return self::enqueuePostBuild($post);
    }

    private static function enqueuePostBuild(Post $post): bool
    {
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

    public static function queuePostSynchronizationQuietly(Post $post): bool
    {
        try {
            return self::queuePostSynchronization($post);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public static function queueSiteSynchronization(int|string $siteIdentifier): bool
    {
        $site = Site::query()
            ->whereKey($siteIdentifier)
            ->orWhere('short_name', $siteIdentifier)
            ->first();

        if (! $site || isset(self::$queuedSites[(string) $site->short_name])) {
            return $site !== null;
        }

        Artisan::queue('site:build', [
            'site_id' => $site->short_name,
            '--force' => true,
            '--resource' => true,
        ]);
        self::$queuedSites[(string) $site->short_name] = true;

        return true;
    }

    public static function queueSiteSynchronizationQuietly(int|string $siteIdentifier): bool
    {
        try {
            return self::queueSiteSynchronization($siteIdentifier);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public static function resolveSiteCode(Post $post): ?string
    {
        if (! $post->site_id) {
            return null;
        }

        $site = $post->relationLoaded('site') ? $post->site : null;

        if (! $site) {
            $site = Site::query()
                ->where('short_name', $post->site_id)
                ->orWhere('id', $post->site_id)
                ->first();
        }

        if (! $site) {
            return null;
        }

        $siteTokens = array_map('strval', array_filter([
            $site->getKey(),
            $site->short_name,
            $site->getAttribute('slug'),
        ], fn (mixed $value): bool => filled($value)));

        return in_array((string) $post->site_id, $siteTokens, true)
            ? (string) $site->short_name
            : null;
    }
}
