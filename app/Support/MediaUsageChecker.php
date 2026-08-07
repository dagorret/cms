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
    private static function referencedMediaIds(Post $post): array
    {
        return collect(data_get($post->body, 'blocks', []))
            ->filter(fn (array $block): bool => data_get($block, 'type') === 'image')
            ->map(fn (array $block): mixed => data_get($block, 'data.file.media_id'))
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}
