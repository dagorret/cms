<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use App\Support\PostBodyRenderer;
use App\Support\StaticViteAssets;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class StaticSchemaGenerator
{
    private const GENERATED_DIRECTORIES = ['archive', 'category', 'data', 'page', 'sitemaps'];

    protected array $categoryPaths = [];

    protected array $menuStructure = [];

    public function __construct(
        protected Command $command,
        protected Site $site,
        protected string $targetFolder,
        protected StaticViteAssets $staticAssets,
    ) {}

    public function build($posts, $pages, $allEntriesLight)
    {
        $this->categoryPaths = $this->buildCategoryPaths();
        $publicPath = $this->publicPath();
        $baseUrl = $this->baseUrl();
        $fullBaseUrl = $baseUrl.$publicPath;
        $menuRenderer = new MenuRenderer;
        $this->menuStructure = $menuRenderer->structure($this->site, 'primary', $publicPath);
        $menuHtml = $menuRenderer->renderStructure($this->menuStructure, $publicPath ?: '/');
        $this->putHtml($this->targetFolder.'/menu.html', $menuHtml);
        File::put($this->targetFolder.'/menu.json', json_encode($this->menuStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        // ===================================================================
        // 1. 📦 ÍNDICES JSON MAESTROS Y SEGMENTACIÓN (ARCHIVE & CATEGORIES)
        // ===================================================================
        $this->command->comment('   📦 Generando metadatos en esquemas JSON inteligentes...');

        // A) Categorías editoriales dinámicas. type queda reservado a post/page.
        $categoriesList = $allEntriesLight
            ->pluck('category')
            ->filter()
            ->unique('id')
            ->sortBy(fn ($category) => [$category->sort_order, $category->name])
            ->values()
            ->map(fn ($category) => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'slug' => $category->slug,
                'name' => $category->name,
                'path' => $this->categoryPath($category),
            ]);

        File::put($this->targetFolder.'/categories.json', $categoriesList->toJson(JSON_PRETTY_PRINT));

        // B) Archivo Histórico Estático: /archive/{YYYY}/{MM}/{DD}/index.html
        $archiveRoot = $this->targetFolder.'/archive';
        $archivePosts = $allEntriesLight
            ->filter(fn ($e) => $e->type === Post::TYPE_POST && ! empty($e->slug) && $e->created_at)
            ->values();

        $this->resetGeneratedDirectory($archiveRoot);

        $groupedArchive = $archivePosts
            ->groupBy(fn ($e) => $e->created_at->format('Y'))
            ->sortKeysDesc();

        $this->putHtml($archiveRoot.'/index.html', view('site.archive.index', [
            'years' => $groupedArchive->keys()->values(),
            'site' => $this->site,
            'subdir' => $publicPath,
            'subdirUrl' => $publicPath,
            'staticAssets' => $this->staticAssets,
            'generatedMenu' => $this->menuHtml($this->joinPublicPath($publicPath, 'archive/')),
        ])->render());

        foreach ($groupedArchive as $year => $yearEntries) {
            $yearPath = "{$archiveRoot}/{$year}";
            File::makeDirectory($yearPath, 0755, true);

            $groupedMonths = $yearEntries
                ->groupBy(fn ($e) => $e->created_at->format('m'))
                ->sortKeysDesc();

            $this->putHtml($yearPath.'/index.html', view('site.archive.year', [
                'year' => $year,
                'months' => $groupedMonths->keys()->values(),
                'site' => $this->site,
                'subdir' => $publicPath,
                'subdirUrl' => $publicPath,
                'staticAssets' => $this->staticAssets,
                'generatedMenu' => $this->menuHtml($this->joinPublicPath($publicPath, "archive/{$year}/")),
            ])->render());

            foreach ($groupedMonths as $month => $monthEntries) {
                $monthPath = "{$yearPath}/{$month}";
                File::makeDirectory($monthPath, 0755, true);

                $groupedDays = $monthEntries
                    ->groupBy(fn ($e) => $e->created_at->format('d'))
                    ->sortKeysDesc();

                $this->putHtml($monthPath.'/index.html', view('site.archive.month', [
                    'year' => $year,
                    'month' => $month,
                    'days' => $groupedDays->map(fn ($entries) => $entries->count()),
                    'site' => $this->site,
                    'subdir' => $publicPath,
                    'subdirUrl' => $publicPath,
                    'staticAssets' => $this->staticAssets,
                    'generatedMenu' => $this->menuHtml($this->joinPublicPath($publicPath, "archive/{$year}/{$month}/")),
                ])->render());

                foreach ($groupedDays as $day => $dayEntries) {
                    $dayPosts = $dayEntries->values();
                    $dayPath = "{$monthPath}/{$day}";
                    File::makeDirectory($dayPath, 0755, true);

                    $this->putHtml($dayPath.'/index.html', view('site.archive.day', [
                        'year' => $year,
                        'month' => $month,
                        'day' => $day,
                        'posts' => $dayPosts,
                        'totalPosts' => $dayPosts->count(),
                        'site' => $this->site,
                        'subdir' => $publicPath,
                        'subdirUrl' => $publicPath,
                        'staticAssets' => $this->staticAssets,
                        'generatedMenu' => $this->menuHtml($this->joinPublicPath($publicPath, "archive/{$year}/{$month}/{$day}/")),
                    ])->render());
                }
            }
        }

        // C) Categorías con HTML canónico y JSON de aceleración.
        $homeFirstPagePosts = max((int) config('static_cms.home_first_page_posts', 10), 1);
        $postsPerPage = max((int) config('static_cms.posts_per_home_page', 20), 1);
        $maxHomePages = max((int) config('static_cms.max_home_pages', 20), 1);
        $dataRoot = $this->targetFolder.'/data';
        $categoriesDataRoot = $dataRoot.'/categories';
        $categoryRoot = $this->targetFolder.'/category';
        $allPostsForData = $allEntriesLight
            ->filter(fn ($e) => $e->type === Post::TYPE_POST && ! empty($e->slug))
            ->values();
        $groupedByCategory = $allPostsForData
            ->filter(fn ($entry) => $entry->category !== null)
            ->groupBy(fn ($entry) => (int) $entry->category_id);
        $serializePost = fn ($e) => $this->serializePost($e, $publicPath);

        $this->resetGeneratedDirectory($dataRoot);
        $this->resetGeneratedDirectory($categoryRoot);
        File::makeDirectory($categoriesDataRoot, 0755, true);

        foreach ($groupedByCategory as $categoryId => $catEntries) {
            $category = $catEntries->first()->category;
            $catSlug = $category->slug;
            $catFolder = $this->targetFolder."/category/{$catSlug}";
            $categoryDataFolder = "{$categoriesDataRoot}/{$catSlug}";

            if (! File::exists($catFolder)) {
                File::makeDirectory($catFolder, 0755, true);
            }

            File::makeDirectory($categoryDataFolder, 0755, true);

            $catChunks = $catEntries->chunk($postsPerPage);
            $catTotalPages = $catChunks->count();

            foreach ($catChunks as $index => $chunk) {
                $pageNum = $index + 1;
                $postsPayload = $chunk->map($serializePost)->values();
                $categoryBaseUrl = $this->joinPublicPath($publicPath, "category/{$catSlug}");
                $categoryJsonBaseUrl = $this->joinPublicPath($publicPath, "data/categories/{$catSlug}");
                $viewData = [
                    'category' => $category,
                    'categoryPath' => $this->categoryPath($category),
                    'posts' => $postsPayload,
                    'site' => $this->site,
                    'currentPage' => $pageNum,
                    'totalPages' => $catTotalPages,
                    'subdirUrl' => $publicPath,
                    'paginationBaseUrl' => $categoryBaseUrl,
                    'paginationJsonBaseUrl' => $categoryJsonBaseUrl,
                    'staticAssets' => $this->staticAssets,
                    'generatedMenu' => $this->menuHtml($pageNum === 1 ? $categoryBaseUrl.'/' : $categoryBaseUrl."/page/{$pageNum}/"),
                ];
                $listingHtml = view('site.partials.listing', $viewData + [
                    'listingTitle' => $this->categoryPath($category),
                    'listingKind' => 'category',
                    'listingDescription' => $category->description,
                ])->render();
                $payload = [
                    'category' => $catSlug,
                    'currentPage' => $pageNum,
                    'totalPages' => $catTotalPages,
                    'canonicalUrl' => $pageNum === 1 ? $categoryBaseUrl.'/' : $categoryBaseUrl."/page/{$pageNum}/",
                    'title' => $category->name.' — '.($this->site->long_name ?? config('app.name')),
                    'posts' => $postsPayload,
                    'html' => StaticHtmlCleaner::clean($listingHtml),
                ];

                File::put($categoryDataFolder."/page-{$pageNum}.json", json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

                $htmlPath = $pageNum === 1 ? $catFolder.'/index.html' : $catFolder."/page/{$pageNum}/index.html";
                File::ensureDirectoryExists(dirname($htmlPath));
                $this->putHtml($htmlPath, view('site.category', $viewData)->render());
            }

        }

        // Limpieza de residuos de arquitecturas viejas
        if (File::exists($this->targetFolder.'/archive.json')) {
            File::delete($this->targetFolder.'/archive.json');
        }

        // ===================================================================
        // 2. 📄 PORTADA CON HTML CANÓNICO Y JSON DE ACELERACIÓN
        // ===================================================================
        $this->command->comment('   📄 Generando portada HTML y JSON de navegación progresiva...');

        // El universo completo de posts ordenados para la portada
        $allPosts = $allEntriesLight->filter(fn ($e) => $e->type === Post::TYPE_POST)->values();
        $firstPagePosts = $allPosts->take($homeFirstPagePosts);
        $paginatedPosts = $allPosts
            ->slice($homeFirstPagePosts)
            ->chunk($postsPerPage)
            ->take($maxHomePages - 1);

        // Página 1 liviana: 10 posts. Páginas siguientes: 20 posts, hasta el límite reciente.
        $pagesToRender = collect([$firstPagePosts])->concat($paginatedPosts)->values();
        $totalPages = $pagesToRender->count();

        if (File::exists($this->targetFolder.'/page')) {
            $this->deleteGeneratedDirectory($this->targetFolder.'/page');
        }

        foreach (File::glob($this->targetFolder.'/page-*.json') ?: [] as $stalePageFile) {
            File::delete($stalePageFile);
        }

        foreach ($pagesToRender as $index => $chunkPosts) {
            $currentPage = $index + 1;
            $postsPayload = $chunkPosts->map($serializePost)->values();
            $homeBaseUrl = $publicPath;
            $homeJsonBaseUrl = $this->joinPublicPath($publicPath, 'data');
            $viewData = [
                'posts' => $postsPayload,
                'site' => $this->site,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'subdirUrl' => $publicPath,
                'paginationBaseUrl' => $homeBaseUrl,
                'paginationJsonBaseUrl' => $homeJsonBaseUrl,
                'staticAssets' => $this->staticAssets,
                'generatedMenu' => $this->menuHtml($currentPage === 1 ? ($publicPath ?: '/') : $this->joinPublicPath($publicPath, "page/{$currentPage}/")),
            ];
            $listingHtml = view('site.partials.listing', $viewData + [
                'listingTitle' => 'Últimos artículos',
                'listingKind' => 'home',
            ])->render();
            $pagePayload = [
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'canonicalUrl' => $currentPage === 1 ? ($publicPath ?: '/').($publicPath ? '/' : '') : $this->joinPublicPath($publicPath, "page/{$currentPage}/"),
                'title' => ($this->site->long_name ?? config('app.name')).' — Carlos Dagorret',
                'posts' => $postsPayload,
                'html' => StaticHtmlCleaner::clean($listingHtml),
            ];

            File::put($dataRoot."/page-{$currentPage}.json", json_encode($pagePayload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            if ($currentPage === 1) {
                $this->putHtml($this->targetFolder.'/index.html', view('site.index', $viewData)->render());
            } else {
                $htmlPath = $this->targetFolder."/page/{$currentPage}/index.html";
                File::ensureDirectoryExists(dirname($htmlPath));
                $this->putHtml($htmlPath, view('site.index', $viewData)->render());
            }
        }

        // ===================================================================
        // 4. 📡 GENERAR FEED RSS (SEGURO SOBRE COLECCIÓN LIGERA)
        // ===================================================================
        $this->command->comment('   📡 Generando feed.xml...');

        $maxFeedItems = max((int) config('static_cms.max_feed_items', 50), 1);
        $feedPosts = $allEntriesLight
            ->filter(fn ($e) => $e->type === Post::TYPE_POST && ! empty($e->slug) && $e->created_at)
            ->sortByDesc(fn ($e) => $e->created_at->timestamp)
            ->take($maxFeedItems);

        $feedPath = $this->targetFolder.'/feed.xml';
        $feedFile = fopen($feedPath, 'w');
        $siteTitle = htmlspecialchars($this->site->long_name ?? $this->site->name ?? config('app.name'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $feedLink = htmlspecialchars("{$fullBaseUrl}/", ENT_XML1 | ENT_QUOTES, 'UTF-8');

        fwrite($feedFile, '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL.'<rss version="2.0">'.PHP_EOL.'  <channel>'.PHP_EOL);
        fwrite($feedFile, "    <title>{$siteTitle}</title>".PHP_EOL);
        fwrite($feedFile, "    <link>{$feedLink}</link>".PHP_EOL);

        foreach ($feedPosts as $post) {
            $url = htmlspecialchars("{$fullBaseUrl}/{$post->slug}/", ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars($post->title, ENT_XML1 | ENT_QUOTES, 'UTF-8');

            fwrite($feedFile, '    <item>'.PHP_EOL);
            fwrite($feedFile, "      <title>{$title}</title>".PHP_EOL);
            fwrite($feedFile, "      <link>{$url}</link>".PHP_EOL);
            fwrite($feedFile, "      <guid>{$url}</guid>".PHP_EOL);
            fwrite($feedFile, '      <pubDate>'.$post->created_at->toRssString().'</pubDate>'.PHP_EOL);
            if ($post->category) {
                $categoryName = htmlspecialchars($post->category->name, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                fwrite($feedFile, "      <category>{$categoryName}</category>".PHP_EOL);
            }
            fwrite($feedFile, '    </item>'.PHP_EOL);
        }

        fwrite($feedFile, '  </channel>'.PHP_EOL.'</rss>'.PHP_EOL);
        fclose($feedFile);

        // ===================================================================
        // 5. 🗺️ SITEMAP INDEX + ARCHIVOS POR TIPO Y LOTES
        // ===================================================================
        $this->command->comment('   🗺️ Generando sitemap compuesto por tipos...');
        $sitemap = (new StaticSitemapGenerator)->generate($this->site, $this->targetFolder);
        $this->command->comment("   🗺️ {$sitemap['urls']} URLs en {$sitemap['files']} archivos (buffer máximo: {$sitemap['peak_buffer']}).");

        // ===================================================================
        // 6. 🚧 404 ESTÁTICO DESACOPLADO
        // ===================================================================
        $this->command->comment('   🚧 Generando 404.html estático con rutas absolutas...');

        $this->putHtml($this->targetFolder.'/404.html', view('site.404', [
            'site' => $this->site,
            'subdir' => $publicPath,
            'subdirUrl' => $publicPath,
            'staticAssets' => $this->staticAssets,
            'generatedMenu' => $this->menuHtml($this->joinPublicPath($publicPath, '404.html')),
        ])->render());

        $this->command->info('   ✔️ Navegación progresiva, sitemap indexado y 404 estático listos.');
    }

    protected function putHtml(string $path, string $html): void
    {
        File::put($path, StaticHtmlCleaner::clean($html));
    }

    protected function menuHtml(string $currentPath): string
    {
        return (new MenuRenderer)->renderStructure($this->menuStructure, $currentPath);
    }

    protected function baseUrl(): string
    {
        $domain = rtrim(trim((string) $this->site->domain), '/');

        if (! preg_match('#^https?://#i', $domain)) {
            $host = strtolower(strtok($domain, '/'));
            $scheme = $host === 'localhost' || str_starts_with($host, '127.') || str_starts_with($host, '[')
                ? 'http://'
                : 'https://';
            $domain = $scheme.$domain;
        }

        return $domain;
    }

    protected function resetGeneratedDirectory(string $path): void
    {
        if (File::exists($path) || is_link($path)) {
            $this->deleteGeneratedDirectory($path);
        }

        if (! File::makeDirectory($path, 0755, true) && ! File::isDirectory($path)) {
            throw new \RuntimeException("No se pudo crear el directorio generado [{$path}].");
        }
    }

    protected function deleteGeneratedDirectory(string $path): void
    {
        $root = realpath($this->targetFolder);
        $normalizedPath = rtrim(str_replace('\\', '/', $path), '/');

        if ($root === false) {
            throw new \RuntimeException("No se pudo resolver el dist_path [{$this->targetFolder}].");
        }

        $root = rtrim(str_replace('\\', '/', $root), '/');

        if (is_link($path)
            || dirname($normalizedPath) !== $root
            || ! in_array(basename($normalizedPath), self::GENERATED_DIRECTORIES, true)) {
            throw new \RuntimeException("Se rechazo la limpieza del directorio generado [{$path}].");
        }

        $canonicalPath = realpath($path);

        if ($canonicalPath === false
            || dirname(rtrim(str_replace('\\', '/', $canonicalPath), '/')) !== $root
            || ! File::deleteDirectory($canonicalPath)) {
            throw new \RuntimeException("No se pudo limpiar de forma segura el directorio generado [{$path}].");
        }
    }

    protected function serializePost($entry, string $publicPath): array
    {
        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'url' => $this->joinPublicPath($publicPath, "{$entry->slug}/"),
            'type' => $entry->type,
            'typeLabel' => $entry->type === Post::TYPE_PAGE ? 'Página' : 'Post',
            'category' => $entry->category ? [
                'id' => $entry->category->id,
                'name' => $entry->category->name,
                'slug' => $entry->category->slug,
                'parent_id' => $entry->category->parent_id,
                'path' => $this->categoryPath($entry->category),
            ] : null,
            'keywords' => collect(explode(',', (string) $entry->keywords))
                ->map(fn ($keyword) => trim($keyword))
                ->filter()
                ->values()
                ->all(),
            'excerpt' => method_exists($entry, 'getExcerpt')
                ? $entry->getExcerpt(30)
                : PostBodyRenderer::excerpt($entry->body ?? '', 30),
            'date' => $entry->created_at?->format('Y-m-d'),
        ];
    }

    protected function buildCategoryPaths(): array
    {
        $categories = Category::query()
            ->where('site_id', $this->site->getKey())
            ->get(['id', 'parent_id', 'name'])
            ->keyBy('id');
        $paths = [];
        $resolve = function (Category $category, array $visited = []) use (&$resolve, $categories, &$paths): string {
            if (isset($paths[$category->id])) {
                return $paths[$category->id];
            }

            if (isset($visited[$category->id])) {
                return $category->name;
            }

            $visited[$category->id] = true;
            $parent = $category->parent_id ? $categories->get($category->parent_id) : null;
            $paths[$category->id] = $parent
                ? $resolve($parent, $visited).' / '.$category->name
                : $category->name;

            return $paths[$category->id];
        };

        foreach ($categories as $category) {
            $resolve($category);
        }

        return $paths;
    }

    protected function categoryPath(Category $category): string
    {
        return $this->categoryPaths[$category->id] ?? $category->name;
    }

    protected function publicPath(): string
    {
        $path = trim((string) $this->site->subdir, '/');

        if ($path === '' || $path === 'dist') {
            return '';
        }

        return '/'.$path;
    }

    protected function joinPublicPath(string $publicPath, string $path): string
    {
        $path = trim($path, '/');

        if ($path === '') {
            return $publicPath === '' ? '/' : "{$publicPath}/";
        }

        return ($publicPath === '' ? '' : $publicPath)."/{$path}/";
    }
}
