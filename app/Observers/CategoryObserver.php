<?php

namespace App\Observers;

use App\Models\Category;
use App\Support\StaticBuildQueue;

class CategoryObserver
{
    public function created(Category $category): void
    {
        $this->synchronizeSite($category);
    }

    public function updated(Category $category): void
    {
        if ($category->wasChanged(['site_id', 'parent_id', 'name', 'slug', 'description', 'sort_order', 'is_visible'])) {
            if ($category->wasChanged('site_id')) {
                $previousSiteId = $category->getPrevious()['site_id'] ?? null;

                if ($previousSiteId) {
                    StaticBuildQueue::queueSiteSynchronizationQuietly((int) $previousSiteId);
                }
            }

            $this->synchronizeSite($category);
        }
    }

    public function deleted(Category $category): void
    {
        $this->synchronizeSite($category);
    }

    protected function synchronizeSite(Category $category): void
    {
        if (! config('static_cms.rebuild_on_publish')) {
            return;
        }

        StaticBuildQueue::queueSiteSynchronizationQuietly((int) $category->site_id);
    }
}
