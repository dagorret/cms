<?php

namespace App\Observers;

use App\Models\Menu;
use App\Support\StaticBuildQueue;

class MenuObserver
{
    public function saved(Menu $menu): void
    {
        $this->synchronize($menu);
    }

    public function deleted(Menu $menu): void
    {
        $this->synchronize($menu);
    }

    private function synchronize(Menu $menu): void
    {
        if (config('static_cms.rebuild_on_publish')) {
            StaticBuildQueue::queueSiteSynchronizationQuietly((int) $menu->site_id);
        }
    }
}
