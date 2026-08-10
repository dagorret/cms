<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use App\Models\Site;
use App\Support\MediaUsageChecker;
use Generator;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class SitePublishedMediaSelector
{
    /** @return Generator<int, Media> */
    public function forSite(Site $site): Generator
    {
        $references = [];

        foreach ($this->publishedPosts($site)->lazyById(200) as $post) {
            foreach (MediaUsageChecker::referencedMediaIds($post) as $mediaId) {
                $references[$mediaId] ??= [
                    'post_id' => (int) $post->getKey(),
                    'post_title' => (string) $post->title,
                ];
            }
        }

        if ($references === []) {
            return;
        }

        ksort($references, SORT_NUMERIC);

        foreach (array_chunk(array_keys($references), 200) as $mediaIds) {
            $mediaById = Media::query()
                ->whereKey($mediaIds)
                ->orderBy('id')
                ->get()
                ->keyBy(fn (Media $media): int => (int) $media->getKey());

            foreach ($mediaIds as $mediaId) {
                $media = $mediaById->get($mediaId);

                if (! $media) {
                    $reference = $references[$mediaId];

                    throw new RuntimeException(
                        "El sitio [{$site->short_name}] referencia el medio inexistente [{$mediaId}] "
                        ."desde el post [{$reference['post_id']}] {$reference['post_title']}.",
                    );
                }

                yield $media;
            }
        }
    }

    private function publishedPosts(Site $site)
    {
        return Post::query()
            ->where(function ($query) use ($site): void {
                $query->where('site_id', $site->getKey())
                    ->orWhere('site_id', $site->short_name);

                if (Schema::hasColumn('sites', 'slug') && filled($site->getAttribute('slug'))) {
                    $query->orWhere('site_id', $site->getAttribute('slug'));
                }
            })
            ->where('status', Post::STATUS_PUBLISHED)
            ->select(['id', 'title', 'body']);
    }
}
