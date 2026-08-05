<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Menu;
use App\Models\Post;
use App\Models\Site;

final class MenuRenderer
{
    /** @return list<array<string, mixed>> */
    public function structure(Site $site, string $location = 'primary', string $publicPath = ''): array
    {
        $menu = Menu::query()
            ->where('site_id', $site->getKey())
            ->where('location', $location)
            ->where('is_active', true)
            ->with(['items' => fn ($query) => $query
                ->where('is_active', true)
                ->with(['category', 'post.site'])])
            ->first();

        if (! $menu) {
            return $this->legacyStructure($site, $publicPath);
        }

        $items = $menu->items
            ->filter(fn ($item): bool => match ($item->type) {
                'category' => $item->category !== null,
                'post', 'page' => $item->post !== null,
                default => true,
            });
        $byParent = $items->groupBy(fn ($item) => $item->parent_id ? (int) $item->parent_id : 0);
        $maxDepth = max((int) config('static_cms.menu_max_depth', 3), 1);

        $append = function (int $parentId, int $depth) use (&$append, $byParent, $publicPath, $maxDepth): array {
            if ($depth > $maxDepth) {
                return [];
            }

            return $byParent->get($parentId, collect())
                ->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->label,
                    'type' => $item->type,
                    'url' => $item->resolvedUrl($publicPath),
                    'target' => $item->target,
                    'rel' => $item->rel,
                    'category' => $item->type === 'category' ? $item->category?->slug : null,
                    'json_url' => $item->type === 'category' && $item->category
                        ? rtrim($publicPath, '/').'/data/categories/'.$item->category->slug.'/page-1.json'
                        : null,
                    'children' => $append((int) $item->id, $depth + 1),
                ])
                ->values()
                ->all();
        };

        return $append(0, 1);
    }

    public function render(Site $site, string $location = 'primary', string $publicPath = ''): string
    {
        return $this->renderStructure(
            $this->structure($site, $location, $publicPath),
            request()->getPathInfo(),
        );
    }

    /** @param list<array<string, mixed>> $items */
    public function renderStructure(array $items, ?string $currentPath = null): string
    {
        return view('site.partials.menu-tree', [
            'items' => $items,
            'currentPath' => $currentPath,
        ])->render();
    }

    /** @return list<array<string, mixed>> */
    private function legacyStructure(Site $site, string $publicPath): array
    {
        $prefix = rtrim($publicPath, '/');
        $items = [[
            'label' => 'Inicio',
            'type' => 'internal_url',
            'url' => $prefix === '' ? '/' : $prefix.'/',
            'target' => '_self',
            'rel' => null,
            'category' => null,
            'json_url' => null,
            'children' => [],
        ]];

        $categories = $site->categories()
            ->where('is_visible', true)
            ->whereHas('posts', fn ($query) => $query
                ->where('status', Post::STATUS_PUBLISHED)
                ->where('type', Post::TYPE_POST))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($categories as $category) {
            $items[] = [
                'label' => $category->name,
                'type' => 'category',
                'url' => $prefix.'/category/'.$category->slug.'/',
                'target' => '_self',
                'rel' => null,
                'category' => $category->slug,
                'json_url' => $prefix.'/data/categories/'.$category->slug.'/page-1.json',
                'children' => [],
            ];
        }

        $pages = $site->posts()
            ->where('status', Post::STATUS_PUBLISHED)
            ->where('type', Post::TYPE_PAGE)
            ->orderBy('title')
            ->get(['slug', 'title']);

        foreach ($pages as $page) {
            $items[] = [
                'label' => $page->title,
                'type' => 'page',
                'url' => $prefix.'/'.$page->slug.'/',
                'target' => '_self',
                'rel' => null,
                'category' => null,
                'json_url' => null,
                'children' => [],
            ];
        }

        return $items;
    }
}
