<?php

namespace App\Observers;

use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\Artisan;

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
        if (!config('static_cms.rebuild_on_publish')) {
            return;
        }

        $siteCode = $this->resolveSiteCode($post);

        if (!$siteCode) {
            return;
        }

        Artisan::queue('site:build', [
            'site_code' => $siteCode,
            '--force' => true,
        ]);
    }

    protected function resolveSiteCode(Post $post): ?string
    {
        if ($post->relationLoaded('site') && $post->site) {
            return $post->site->code
                ?? $post->site->short_name
                ?? null;
        }

        if (!$post->site_id) {
            return null;
        }

        return Site::query()
            ->where('short_name', $post->site_id)
            ->orWhere('id', $post->site_id)
            ->value('short_name') ?: $post->site_id;
    }
}
