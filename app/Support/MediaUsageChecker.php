<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Post;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class MediaUsageChecker
{
    /**
     * Returns the Post that references this Media inside its EditorJS body, or null
     * when the media isn't referenced by any block (orphaned) or its owner isn't a Post.
     */
    public static function referencingPost(Media $media): ?Post
    {
        $owner = $media->model;

        if (! $owner instanceof Post) {
            return null;
        }

        return in_array((int) $media->getKey(), self::referencedMediaIds($owner), true)
            ? $owner
            : null;
    }

    public static function isInUse(Media $media): bool
    {
        return self::referencingPost($media) !== null;
    }

    /**
     * @return array<int>
     */
    public static function referencedMediaIds(Post $post): array
    {
        $body = $post->getRawOriginal('body');

        if (is_string($body)) {
            $body = json_decode($body, true);
        }

        if (! is_array($body)) {
            return [];
        }

        $ids = [];

        foreach (data_get($body, 'blocks', []) as $block) {
            if (! is_array($block) || data_get($block, 'type') !== 'image') {
                continue;
            }

            $mediaId = filter_var(data_get($block, 'data.file.media_id'), FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($mediaId === false) {
                $url = data_get($block, 'data.file.url');
                $normalized = is_string($url)
                    ? app(MediaReferenceResolver::class)->normalizeUrl($url)
                    : null;
                $mediaId = $normalized['media_id'] ?? false;
            }

            if ($mediaId !== false) {
                $ids[(int) $mediaId] = true;
            }
        }

        return array_keys($ids);
    }
}
