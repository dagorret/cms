<?php

namespace App\Observers;

use App\Models\Post;
use App\Support\StaticBuildQueue;

class PostObserver
{
    /**
     * Gatillo cuando el post se crea, se edita o se publica.
     */
    public function saved(Post $post): void
    {
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

        StaticBuildQueue::queuePostQuietly($post);
    }
}
