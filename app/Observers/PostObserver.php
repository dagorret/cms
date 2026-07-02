<?php

namespace App\Observers;

use App\Models\Post;
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
     * Llama al comando Artisan en segundo plano.
     */
    protected function rebuildSite(Post $post): void
    {
        $siteSlug = $post->site_id;

        if ($siteSlug) {
            // Cambiamos 'sitio' por 'site_code' ⚡
            Artisan::call('site:build', [
                'site_code' => $siteSlug
            ]);
        }
    }
}
