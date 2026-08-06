<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;

class PostChronology
{
    /**
     * @return array{previous: Post|null, next: Post|null}
     */
    public function adjacentTo(Post $post): array
    {
        if (
            $post->type !== Post::TYPE_POST
            || $post->status !== Post::STATUS_PUBLISHED
            || $post->published_at === null
        ) {
            return ['previous' => null, 'next' => null];
        }

        $previous = (clone $this->eligiblePosts($post))
            ->where(function (Builder $query) use ($post): void {
                $query->where('published_at', '<', $post->published_at)
                    ->orWhere(function (Builder $query) use ($post): void {
                        $query->where('published_at', '=', $post->published_at)
                            ->where('id', '<', $post->getKey());
                    });
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first($this->navigationColumns());

        $next = (clone $this->eligiblePosts($post))
            ->where(function (Builder $query) use ($post): void {
                $query->where('published_at', '>', $post->published_at)
                    ->orWhere(function (Builder $query) use ($post): void {
                        $query->where('published_at', '=', $post->published_at)
                            ->where('id', '>', $post->getKey());
                    });
            })
            ->orderBy('published_at')
            ->orderBy('id')
            ->first($this->navigationColumns());

        return compact('previous', 'next');
    }

    private function eligiblePosts(Post $post): Builder
    {
        return Post::query()
            ->where('site_id', $post->site_id)
            ->where('status', Post::STATUS_PUBLISHED)
            ->where('type', Post::TYPE_POST)
            ->whereNotNull('published_at');
    }

    /** @return list<string> */
    private function navigationColumns(): array
    {
        return ['id', 'site_id', 'slug', 'title', 'type', 'status', 'published_at'];
    }
}
