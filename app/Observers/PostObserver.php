<?php

namespace App\Observers;

use App\Models\Post;
use App\Support\StaticBuildQueue;

class PostObserver
{
    private const BUILD_RELEVANT_ATTRIBUTES = [
        'title',
        'slug',
        'body',
        'keywords',
        'type',
        'status',
        'site_id',
        'has_math',
        'category',
        'published_at',
        'created_at',
    ];

    public function created(Post $post): void
    {
        $this->rebuildSite($post);
    }

    public function updated(Post $post): void
    {
        if (! $post->wasChanged(self::BUILD_RELEVANT_ATTRIBUTES)) {
            return;
        }

        $this->rebuildSite($post);
    }

    /**
     * Gatillo cuando el post se elimina.
     */
    public function deleted(Post $post): void
    {
        $this->rebuildSite($post);
    }

    /**
     * Gatillo cuando se restaura un post si el modelo usa SoftDeletes.
     */
    public function restored(Post $post): void
    {
        $this->rebuildSite($post);
    }

    /**
     * Encola el rebuild para no bloquear Filament.
     */
    protected function rebuildSite(Post $post): void
    {
        if (! config('static_cms.rebuild_on_publish')) {
            return;
        }

        StaticBuildQueue::queuePostSynchronizationQuietly($post);
    }
}
