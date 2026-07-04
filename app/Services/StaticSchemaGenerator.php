<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class StaticSchemaGenerator
{
    public function __construct(
        protected Command $command,
        protected Site $site,
        protected string $targetFolder
    ) {}

    public function build($posts, $pages, $allEntriesLight)
    {
        $subdir = $this->site->subdir ? '/' . trim($this->site->subdir, '/') : '';
        $baseUrl = $this->site->url ?: config('app.url', 'http://localhost');
        $fullBaseUrl = rtrim($baseUrl, '/') . $subdir;

        // ===================================================================
        // 1. 📦 ÍNDICES JSON MAESTROS Y SEGMENTACIÓN (ARCHIVE & CATEGORIES)
        // ===================================================================
        $this->command->comment('   📦 Generando metadatos en esquemas JSON inteligentes...');

        // A) Indexar Categorías Únicas con Fallback robusto por tipo
        $categoriesList = $allEntriesLight->map(fn($e) => $e->category ?: $e->type)
        ->filter()
        ->unique()
        ->values()
        ->map(fn($cat) => [
            'slug' => str($cat)->slug()->toString(),
              'name' => ucfirst($cat)
        ]);

        File::put($this->targetFolder . '/categories.json', $categoriesList->toJson(JSON_PRETTY_PRINT));

        // B) Archivo Histórico Estático: /archive/{YYYY}/{MM}/{DD}/index.html
        $archiveRoot = $this->targetFolder . '/archive';
        $archivePosts = $allEntriesLight
            ->filter(fn($e) => ($e->type ?? 'post') !== 'page' && !empty($e->slug) && $e->created_at)
            ->values();

        if (File::exists($archiveRoot)) {
            File::deleteDirectory($archiveRoot);
        }

        File::makeDirectory($archiveRoot, 0755, true);

        $groupedArchive = $archivePosts
            ->groupBy(fn($e) => $e->created_at->format('Y'))
            ->sortKeysDesc();

        $this->putHtml($archiveRoot . '/index.html', view('site.archive.index', [
            'years' => $groupedArchive->keys()->values(),
            'site' => $this->site,
            'subdir' => $subdir,
            'subdirUrl' => $subdir,
        ])->render());

        foreach ($groupedArchive as $year => $yearEntries) {
            $yearPath = "{$archiveRoot}/{$year}";
            File::makeDirectory($yearPath, 0755, true);

            $groupedMonths = $yearEntries
                ->groupBy(fn($e) => $e->created_at->format('m'))
                ->sortKeysDesc();

            $this->putHtml($yearPath . '/index.html', view('site.archive.year', [
                'year' => $year,
                'months' => $groupedMonths->keys()->values(),
                'site' => $this->site,
                'subdir' => $subdir,
                'subdirUrl' => $subdir,
            ])->render());

            foreach ($groupedMonths as $month => $monthEntries) {
                $monthPath = "{$yearPath}/{$month}";
                File::makeDirectory($monthPath, 0755, true);

                $groupedDays = $monthEntries
                    ->groupBy(fn($e) => $e->created_at->format('d'))
                    ->sortKeysDesc();

                $this->putHtml($monthPath . '/index.html', view('site.archive.month', [
                    'year' => $year,
                    'month' => $month,
                    'days' => $groupedDays->map(fn($entries) => $entries->count()),
                    'site' => $this->site,
                    'subdir' => $subdir,
                    'subdirUrl' => $subdir,
                ])->render());

                foreach ($groupedDays as $day => $dayEntries) {
                    $dayPosts = $dayEntries->values();
                    $dayPath = "{$monthPath}/{$day}";
                    File::makeDirectory($dayPath, 0755, true);

                    $this->putHtml($dayPath . '/index.html', view('site.archive.day', [
                        'year' => $year,
                        'month' => $month,
                        'day' => $day,
                        'posts' => $dayPosts,
                        'totalPosts' => $dayPosts->count(),
                        'site' => $this->site,
                        'subdir' => $subdir,
                        'subdirUrl' => $subdir,
                    ])->render());
                }
            }
        }

        // C) Índices dinámicos de Categorías paginados en JSON puro: /category/{slug}/page-{n}.json
        $homeFirstPagePosts = max((int) config('static_cms.home_first_page_posts', 10), 1);
        $postsPerPage = max((int) config('static_cms.posts_per_home_page', 20), 1);
        $maxHomePages = max((int) config('static_cms.max_home_pages', 20), 1);
        $dataRoot = $this->targetFolder . '/data';
        $tagsDataRoot = $dataRoot . '/tags';
        $allPostsForData = $allEntriesLight
            ->filter(fn($e) => ($e->type ?? 'post') !== 'page' && !empty($e->slug))
            ->values();
        $groupedByCategory = $allPostsForData->groupBy(fn($e) => $e->type ?? 'post');
        $serializePost = fn($e) => [
            'id' => $e->id,
            'title' => $e->title,
            'slug' => $e->slug,
            'url' => "{$subdir}/{$e->slug}/",
            'type' => $e->type ?? 'post',
            'category' => $e->category ?? null,
            'keywords' => $e->keywords,
            'excerpt' => trim(strip_tags($e->excerpt ?? '')),
            'date' => $e->created_at->format('Y-m-d'),
        ];

        if (File::exists($dataRoot)) {
            File::deleteDirectory($dataRoot);
        }

        File::makeDirectory($tagsDataRoot, 0755, true);

        $typeLabels = collect(config('static_cms.types', []));
        $menuItems = $typeLabels
            ->map(function ($label, $type) use ($groupedByCategory, $postsPerPage) {
                $entries = $groupedByCategory->get($type, collect());

                if ($entries->isEmpty()) {
                    return null;
                }

                $slug = str($type)->slug()->toString();

                return [
                    'title' => $label,
                    'name' => $label,
                    'slug' => $slug,
                    'tag' => $slug,
                    'count' => $entries->count(),
                    'total_pages' => (int) ceil($entries->count() / $postsPerPage),
                ];
            })
            ->filter()
            ->values();

        $extraMenuItems = $groupedByCategory
            ->reject(fn($entries, $type) => $typeLabels->has($type))
            ->filter(fn($entries, $type) => !empty($type) && $entries->isNotEmpty())
            ->map(function ($entries, $type) use ($postsPerPage) {
                $slug = str($type)->slug()->toString();

                return [
                    'title' => ucfirst($type),
                    'name' => ucfirst($type),
                    'slug' => $slug,
                    'tag' => $slug,
                    'count' => $entries->count(),
                    'total_pages' => (int) ceil($entries->count() / $postsPerPage),
                ];
            })
            ->values();

        $menuItems = $menuItems->concat($extraMenuItems)->values();

        foreach ($groupedByCategory as $categoryName => $catEntries) {
            if (empty($categoryName)) continue;

            $catSlug = str($categoryName)->slug()->toString();
            $catFolder = $this->targetFolder . "/category/{$catSlug}";
            $tagDataFolder = "{$tagsDataRoot}/{$catSlug}";

            if (!File::exists($catFolder)) {
                File::makeDirectory($catFolder, 0755, true);
            }

            File::makeDirectory($tagDataFolder, 0755, true);

            $catChunks = $catEntries->chunk($postsPerPage);
            $catTotalPages = $catChunks->count();

            foreach ($catChunks as $index => $chunk) {
                $pageNum = $index + 1;
                $postsPayload = $chunk->map($serializePost)->values();
                $chunkData = $postsPayload->toJson();
                $tagPayload = [
                    'tag' => $catSlug,
                    'current_page' => $pageNum,
                    'total_pages' => $catTotalPages,
                    'posts' => $postsPayload,
                ];

                File::put($catFolder . "/page-{$pageNum}.json", $chunkData);
                File::put($tagDataFolder . "/page-{$pageNum}.json", json_encode($tagPayload));
            }
        }

        // Limpieza de residuos de arquitecturas viejas
        if (File::exists($this->targetFolder . '/archive.json')) {
            File::delete($this->targetFolder . '/archive.json');
        }

        // ===================================================================
        // 2. GENERAR MENÚ ESTÁTICO HTML Y JSON MAESTRO
        // ===================================================================
        $this->command->comment('   🍴 Generando menús de navegación estáticos...');
        $menuHtml = view('site.menu', [
            'items' => $menuItems,
            'pages' => $pages,
            'site' => $this->site,
            'subdirUrl' => $subdir,
        ])->render();
        $this->putHtml($this->targetFolder . '/menu.html', $menuHtml);

        File::put($this->targetFolder . '/menu.json', $menuItems->toJson(JSON_PRETTY_PRINT));

        // ===================================================================
        // 3. 📄 PORTADA PRINCIPAL (HTML) Y PAGINACIÓN SUBSIGUIENTE (JSON PURO)
        // ===================================================================
        $this->command->comment('   📄 Generando Portada index.html y listados JSON para la SPA...');

        // El universo completo de posts ordenados para la portada
        $allPosts = $allEntriesLight->filter(fn($e) => $e->type !== 'page')->values();
        $firstPagePosts = $allPosts->take($homeFirstPagePosts);
        $paginatedPosts = $allPosts
            ->slice($homeFirstPagePosts)
            ->chunk($postsPerPage)
            ->take($maxHomePages - 1);

        // Página 1 liviana: 10 posts. Páginas siguientes: 20 posts, hasta el límite reciente.
        $pagesToRender = $firstPagePosts->isEmpty()
            ? collect()
            : collect([$firstPagePosts])->concat($paginatedPosts)->values();
        $totalPages = $pagesToRender->count();

        // Si existía la estructura vieja de carpetas HTML para las páginas, la limpiamos
        if (File::exists($this->targetFolder . '/page')) {
            File::deleteDirectory($this->targetFolder . '/page');
        }

        foreach (File::glob($this->targetFolder . '/page-*.json') ?: [] as $stalePageFile) {
            File::delete($stalePageFile);
        }

        foreach ($pagesToRender as $index => $chunkPosts) {
            $currentPage = $index + 1;
            $postsPayload = $chunkPosts->map($serializePost)->values();
            $pagePayload = [
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'posts' => $postsPayload,
            ];

            File::put($dataRoot . "/page-{$currentPage}.json", json_encode($pagePayload));

            if ($currentPage === 1) {
                // La página 1 de la Home siempre es un HTML real masticado para el landing inicial
                $indexHtml = view('site.index', [
                    'posts' => $chunkPosts,
                    'site' => $this->site,
                    'currentPage' => 1,
                    'totalPages' => $totalPages,
                    'subdirUrl' => $subdir
                ])->render();

                $this->putHtml($this->targetFolder . '/index.html', $indexHtml);
            } else {
                // 🚀 De la página 2 en adelante: JSON puros en la raíz de dist para consumo SPA instantáneo
                File::put($this->targetFolder . "/page-{$currentPage}.json", $postsPayload->toJson());
            }
        }

        // ===================================================================
        // 4. 📡 GENERAR FEED RSS (SEGURO SOBRE COLECCIÓN LIGERA)
        // ===================================================================
        $this->command->comment('   📡 Generando feed.xml...');

        $maxFeedItems = max((int) config('static_cms.max_feed_items', 50), 1);
        $feedPosts = $allEntriesLight
            ->filter(fn($e) => ($e->type ?? 'post') !== 'page' && !empty($e->slug) && $e->created_at)
            ->sortByDesc(fn($e) => $e->created_at->timestamp)
            ->take($maxFeedItems);

        $feedPath = $this->targetFolder . '/feed.xml';
        $feedFile = fopen($feedPath, 'w');
        $siteTitle = htmlspecialchars($this->site->long_name ?? $this->site->name ?? config('app.name'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $feedLink = htmlspecialchars("{$fullBaseUrl}/", ENT_XML1 | ENT_QUOTES, 'UTF-8');

        fwrite($feedFile, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<rss version="2.0">' . PHP_EOL . '  <channel>' . PHP_EOL);
        fwrite($feedFile, "    <title>{$siteTitle}</title>" . PHP_EOL);
        fwrite($feedFile, "    <link>{$feedLink}</link>" . PHP_EOL);

        foreach ($feedPosts as $post) {
            $url = htmlspecialchars("{$fullBaseUrl}/{$post->slug}/", ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars($post->title, ENT_XML1 | ENT_QUOTES, 'UTF-8');

            fwrite($feedFile, '    <item>' . PHP_EOL);
            fwrite($feedFile, "      <title>{$title}</title>" . PHP_EOL);
            fwrite($feedFile, "      <link>{$url}</link>" . PHP_EOL);
            fwrite($feedFile, "      <guid>{$url}</guid>" . PHP_EOL);
            fwrite($feedFile, "      <pubDate>" . $post->created_at->toRssString() . "</pubDate>" . PHP_EOL);
            fwrite($feedFile, '    </item>' . PHP_EOL);
        }

        fwrite($feedFile, '  </channel>' . PHP_EOL . '</rss>' . PHP_EOL);
        fclose($feedFile);

        // ===================================================================
        // 5. 🗺️ SITEMAP MÚLTIPLE (SITEMAP INDEX + CHUNKS SECUENCIALES)
        // ===================================================================
        $this->command->comment('   🗺️ Generando Sitemap Index y fragmentos masivos...');

        $sitemapsPath = $this->targetFolder . '/sitemaps';
        $sitemapPerPage = max((int) config('static_cms.sitemap_per_page', 1000), 1);
        $sitemapEntries = $allEntriesLight
            ->filter(fn($entry) => !empty($entry->slug) && $entry->updated_at)
            ->values();
        $sitemapChunks = $sitemapEntries->chunk($sitemapPerPage);
        $sitemapFilesCreated = [];

        if (File::exists($sitemapsPath)) {
            File::deleteDirectory($sitemapsPath);
        }

        File::makeDirectory($sitemapsPath, 0755, true);

        if (File::exists($this->targetFolder . '/sitemap.xml')) {
            File::delete($this->targetFolder . '/sitemap.xml');
        }

        foreach (File::glob($this->targetFolder . '/sitemap-*.xml') ?: [] as $staleSitemapFile) {
            File::delete($staleSitemapFile);
        }

        if ($sitemapChunks->isEmpty()) {
            $sitemapChunks = collect([collect()]);
        }

        foreach ($sitemapChunks as $chunkIndex => $chunkEntries) {
            $partNumber = $chunkIndex + 1;
            $fileName = "page-{$partNumber}.xml";
            $partPath = $sitemapsPath . '/' . $fileName;

            $partFile = fopen($partPath, 'w');
            fwrite($partFile, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);

            if ($partNumber === 1) {
                $homeUrl = htmlspecialchars("{$fullBaseUrl}/", ENT_XML1 | ENT_QUOTES, 'UTF-8');
                fwrite($partFile, "    <url><loc>{$homeUrl}</loc><priority>1.0</priority></url>" . PHP_EOL);
            }

            foreach ($chunkEntries as $entry) {
                $url = htmlspecialchars("{$fullBaseUrl}/{$entry->slug}/", ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $lastMod = htmlspecialchars($entry->updated_at->toIso8601String(), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                fwrite($partFile, "    <url><loc>{$url}</loc><lastmod>{$lastMod}</lastmod><priority>0.8</priority></url>" . PHP_EOL);
            }

            fwrite($partFile, '</urlset>' . PHP_EOL);
            fclose($partFile);

            $sitemapFilesCreated[] = [
                'name' => $fileName,
                'lastmod' => now()->toIso8601String(),
            ];
        }

        $indexPath = $sitemapsPath . '/sitemap-index.xml';
        $indexFile = fopen($indexPath, 'w');

        fwrite($indexFile, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);

        foreach ($sitemapFilesCreated as $sitemapFile) {
            $sitemapUrl = htmlspecialchars("{$fullBaseUrl}/sitemaps/{$sitemapFile['name']}", ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $lastMod = htmlspecialchars($sitemapFile['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8');

            fwrite($indexFile, '  <sitemap>' . PHP_EOL);
            fwrite($indexFile, "    <loc>{$sitemapUrl}</loc>" . PHP_EOL);
            fwrite($indexFile, "    <lastmod>{$lastMod}</lastmod>" . PHP_EOL);
            fwrite($indexFile, '  </sitemap>' . PHP_EOL);
        }

        fwrite($indexFile, '</sitemapindex>' . PHP_EOL);
        fclose($indexFile);

        // ===================================================================
        // 6. 🚧 404 ESTÁTICO DESACOPLADO
        // ===================================================================
        $this->command->comment('   🚧 Generando 404.html estático con rutas absolutas...');

        $this->putHtml($this->targetFolder . '/404.html', view('site.404', [
            'site' => $this->site,
            'subdir' => $subdir,
            'subdirUrl' => $fullBaseUrl,
            'fullBaseUrl' => $fullBaseUrl,
            'useAbsoluteUrls' => true,
        ])->render());

        $this->command->info('   ✔️ Arquitectura unificada: SPA JSON Ready, Sitemaps indexados y 404 estático listos.');
    }

    protected function putHtml(string $path, string $html): void
    {
        File::put($path, StaticHtmlCleaner::clean($html));
    }
}
