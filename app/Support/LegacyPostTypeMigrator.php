<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class LegacyPostTypeMigrator
{
    public function migrate(): void
    {
        $sites = DB::table('sites')->get(['id', 'short_name']);
        $sitesById = $sites->keyBy(fn (object $site): string => (string) $site->id);
        $sitesByShortName = $sites->keyBy(fn (object $site): string => (string) $site->short_name);

        DB::table('posts')
            ->select(['id', 'site_id', 'type'])
            ->orderBy('id')
            ->chunkById(1000, function ($posts) use ($sitesById, $sitesByShortName): void {
                foreach ($posts as $post) {
                    $legacyType = trim((string) $post->type);
                    $normalizedType = strtolower($legacyType);
                    $siteToken = (string) $post->site_id;
                    $site = $sitesById->get($siteToken) ?? $sitesByShortName->get($siteToken);

                    if (! $site) {
                        throw new RuntimeException("No se puede migrar posts.type [{$legacyType}] del post [{$post->id}]: site_id [{$siteToken}] no existe.");
                    }

                    if ($normalizedType === 'page') {
                        DB::table('posts')->where('id', $post->id)->update([
                            'site_id' => $site->short_name,
                            'type' => 'page',
                            'category_id' => null,
                        ]);

                        continue;
                    }

                    if ($legacyType === '' || $normalizedType === 'post') {
                        DB::table('posts')->where('id', $post->id)->update([
                            'site_id' => $site->short_name,
                            'type' => 'post',
                        ]);

                        continue;
                    }

                    $categorySlug = Str::slug($legacyType) ?: 'sin-categoria';
                    $categoryId = DB::table('categories')
                        ->where('site_id', $site->id)
                        ->where('slug', $categorySlug)
                        ->value('id');

                    if (! $categoryId) {
                        $categoryId = DB::table('categories')->insertGetId([
                            'site_id' => $site->id,
                            'parent_id' => null,
                            'name' => Str::ucfirst($legacyType),
                            'slug' => $categorySlug,
                            'description' => null,
                            'sort_order' => 0,
                            'is_visible' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('posts')->where('id', $post->id)->update([
                        'site_id' => $site->short_name,
                        'type' => 'post',
                        'category_id' => $categoryId,
                    ]);
                }
            });
    }
}
