<?php

namespace App\Observers;

use App\Models\MenuItem;
use App\Support\StaticBuildQueue;

class MenuItemObserver
{
    public function saved(MenuItem $item): void
    {
        $this->synchronize($item);
    }

    public function deleted(MenuItem $item): void
    {
        $this->synchronize($item);
    }

    private function synchronize(MenuItem $item): void
    {
        if (config('static_cms.rebuild_on_publish') && $item->menu) {
            StaticBuildQueue::queueSiteSynchronizationQuietly((int) $item->menu->site_id);
        }
    }
}
