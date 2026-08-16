<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use App\Services\MenuRenderer;
use App\Support\PostBodyRenderer;
use App\Services\StaticHtmlCleaner;
use App\Support\StaticViteAssets;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

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

    public function build(): void
    {
        $this->categoryPaths = $this->buildCategoryPaths();
        $publicPath = $this->publicPath();
        $fullBaseUrl = $this->baseUrl().$publicPath;
        $menuRenderer = new MenuRenderer;
        $this->menuStructure = $menuRenderer->structure($this->site, 'primary', $publicPath);
        $this->putHtml($this->targetFolder.'/menu.html', $menuRenderer->renderStructure($this->menuStructure, $publicPath ?: '/'));
        File::put($this->targetFolder.'/menu.json', json_encode($this->menuStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $this->command->comment('   📦 Generando metadatos en esquemas JSON inteligentes...');
        $this->generateCategoriesJson();
        $this->generateArchives($publicPath);
        $this->resourceSnapshot('indices');

        $this->generateCategoryListings($publicPath);
        $this->resourceSnapshot('categorias');

        $this->command->comment('   📄 Generando portada HTML y JSON de navegación progresiva...');
        $this->generateHome($publicPath);

        $this->command->comment('   📡 Generando feed.xml...');
        $this->generateFeed($fullBaseUrl);

        $this->command->comment('   🗺️ Generando sitemap compuesto por tipos...');
        $sitemap = (new StaticSitemapGenerator)->generate($this->site, $this->targetFolder);
        $this->command->comment("   🗺️ {$sitemap['urls']} URLs en {$sitemap['files']} archivos (buffer máximo: {$sitemap['peak_buffer']}).");
        $this->resourceSnapshot('sitemap', $sitemap['urls'], $sitemap['files']);

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

    protected function generateCategoriesJson(): void
    {
        $path = $this->targetFolder.'/categories.json';
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException("No se pudo escribir [{$path}].");
        }

        fwrite($handle, '[');
        $first = true;

        try {
            foreach ($this->categoriesWithPublishedPosts()->cursor() as $category) {
                $payload = [
                    'id' => $category->id,
                    'parent_id' => $category->parent_id,
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'path' => $this->categoryPath($category),
                ];
                $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                fwrite($handle, ($first ? "\n" : ",\n").$this->indentJson($json));
                $first = false;
            }

            fwrite($handle, $first ? ']' : "\n]");
        } finally {
            fclose($handle);
        }
    }

    protected function generateArchives(string $publicPath): void
    {
        $archiveRoot = $this->targetFolder.'/archive';
        $this->resetGeneratedDirectory($archiveRoot);

        $years = $this->archivePeriods(4)->pluck('period');
        $this->putHtml($archiveRoot.'/index.html', view('site.archive.index', [
            'years' => $years,
            'site' => $this->site,
            'subdir' => $publicPath,
            'subdirUrl' => $publicPath,
            'canonicalPath' => 'archive',
            'staticAssets' => $this->staticAssets,
            'generatedMenu' => $this->menuHtml($this->joinPublicPath($publicPath, 'archive/')),
        ])->render());

        foreach ($years as $year) {
            $yearPath = "{$archiveRoot}/{$year}";
            File::ensureDirectoryExists($yearPath);
            $months = $this->archivePeriods(7, (string) $year)->pluck('period')->map(fn (string $period): string => substr($period, 5, 2));

            $this->putHtml($yearPath.'/index.html', view('site.archive.year', [
                'year' => $year,
                'months' => $months,
                'site' => $this->site,
                'subdir' => $publicPath,
                'subdirUrl' => $publicPath,
                'canonicalPath' => "archive/{$year}",
                'staticAssets' => $this->staticAssets,
                'generatedMenu' => $this->menuHtml($this->joinPublicPath($publicPath, "archive/{$year}/")),
            ])->render());

            foreach ($months as $month) {
                $monthPath = "{$yearPath}/{$month}";
                File::ensureDirectoryExists($monthPath);
                $days = $this->archivePeriods(10, "{$year}-{$month}")
                    ->pluck('total', 'period')
                    ->mapWithKeys(fn (int $total, string $period): array => [substr($period, 8, 2) => $total]);

                $this->putHtml($monthPath.'/index.html', view('site.archive.month', [
                    'year' => $year,
                    'month' => $month,
                    'days' => $days,
                    'site' => $this->site,
                    'subdir' => $publicPath,
                    'subdirUrl' => $publicPath,
                    'canonicalPath' => "archive/{$year}/{$month}",
                    'staticAssets' => $this->staticAssets,
                    'generatedMenu' => $this->menuHtml($this->joinPublicPath($publicPath, "archive/{$year}/{$month}/")),
                ])->render());

                foreach ($days as $day => $total) {
                    $dayPath = "{$monthPath}/{$day}";
                    File::ensureDirectoryExists($dayPath);
                    $this->streamArchiveDay($dayPath.'/index.html', (string) $year, (string) $month, (string) $day, (int) $total, $publicPath);
                }
            }
        }
    }

    protected function generateCategoryListings(string $publicPath): void
    {
        $postsPerPage = max((int) config('static_cms.posts_per_home_page', 20), 1);
        $dataRoot = $this->targetFolder.'/data';
        $categoriesDataRoot = $dataRoot.'/categories';
        $categoryRoot = $this->targetFolder.'/category';

        $this->resetGeneratedDirectory($dataRoot);
        $this->resetGeneratedDirectory($categoryRoot);
        File::ensureDirectoryExists($categoriesDataRoot);

        foreach ($this->categoriesWithPublishedPosts(true)->cursor() as $category) {
            $totalPages = (int) ceil(((int) $category->published_posts_count) / $postsPerPage);
            $catSlug = (string) $category->slug;
            $catFolder = "{$categoryRoot}/{$catSlug}";
            $categoryDataFolder = "{$categoriesDataRoot}/{$catSlug}";
            File::ensureDirectoryExists($catFolder);
            File::ensureDirectoryExists($categoryDataFolder);

            $query = $this->listingQuery()->where('category_id', $category->id);
            foreach ($this->keysetPages($query, $postsPerPage, $postsPerPage) as $pageNum => $entries) {
                $postsPayload = $entries->map(fn (Post $entry): array => $this->serializePost($entry, $publicPath));
                $categoryBaseUrl = $this->joinPublicPath($publicPath, "category/{$catSlug}");
                $categoryJsonBaseUrl = $this->joinPublicPath($publicPath, "data/categories/{$catSlug}");
                $viewData = [
                    'category' => $category,
                    'categoryPath' => $this->categoryPath($category),
                    'posts' => $postsPayload,
                    'site' => $this->site,
                    'currentPage' => $pageNum,
                    'totalPages' => $totalPages,
                    'subdirUrl' => $publicPath,
                    'paginationBaseUrl' => $categoryBaseUrl,
                    'paginationJsonBaseUrl' => $categoryJsonBaseUrl,
                    'canonicalPath' => $pageNum === 1 ? "category/{$catSlug}" : "category/{$catSlug}/page/{$pageNum}",
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
                    'totalPages' => $totalPages,
                    'canonicalUrl' => $pageNum === 1 ? $categoryBaseUrl.'/' : $categoryBaseUrl."/page/{$pageNum}/",
                    'title' => $category->name.' — '.($this->site->long_name ?? config('app.name')),
                    'posts' => $postsPayload,
                    'html' => StaticHtmlCleaner::clean($listingHtml),
                ];

                File::put($categoryDataFolder."/page-{$pageNum}.json", json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                $htmlPath = $pageNum === 1 ? $catFolder.'/index.html' : $catFolder."/page/{$pageNum}/index.html";
                File::ensureDirectoryExists(dirname($htmlPath));
                $this->putHtml($htmlPath, view('site.category', $viewData)->render());
                unset($postsPayload, $entries, $viewData, $payload, $listingHtml);
                gc_collect_cycles();
            }
        }

        if (File::exists($this->targetFolder.'/archive.json')) {
            File::delete($this->targetFolder.'/archive.json');
        }
    }

    protected function generateHome(string $publicPath): void
    {
        $firstPageSize = max((int) config('static_cms.home_first_page_posts', 10), 1);
        $postsPerPage = max((int) config('static_cms.posts_per_home_page', 20), 1);
        $maxPages = max((int) config('static_cms.max_home_pages', 20), 1);
        $totalPosts = $this->publishedEntries()->where('type', Post::TYPE_POST)->count();
        $totalPages = min($maxPages, $totalPosts <= $firstPageSize ? 1 : 1 + (int) ceil(($totalPosts - $firstPageSize) / $postsPerPage));
        $dataRoot = $this->targetFolder.'/data';

        if (File::exists($this->targetFolder.'/page')) {
            $this->deleteGeneratedDirectory($this->targetFolder.'/page');
        }
        foreach (File::glob($this->targetFolder.'/page-*.json') ?: [] as $stalePageFile) {
            File::delete($stalePageFile);
        }

        foreach ($this->keysetPages($this->listingQuery(), $firstPageSize, $postsPerPage, $maxPages) as $currentPage => $entries) {
            $postsPayload = $entries->map(fn (Post $entry): array => $this->serializePost($entry, $publicPath));
            $homeJsonBaseUrl = $this->joinPublicPath($publicPath, 'data');
            $viewData = [
                'posts' => $postsPayload,
                'site' => $this->site,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'subdirUrl' => $publicPath,
                'paginationBaseUrl' => $publicPath,
                'paginationJsonBaseUrl' => $homeJsonBaseUrl,
                'canonicalPath' => $currentPage === 1 ? '' : "page/{$currentPage}",
                'staticAssets' => $this->staticAssets,
                'generatedMenu' => $this->menuHtml($currentPage === 1 ? ($publicPath ?: '/') : $this->joinPublicPath($publicPath, "page/{$currentPage}/")),
            ];
            $listingHtml = view('site.partials.listing', $viewData + ['listingTitle' => 'Últimos artículos', 'listingKind' => 'home'])->render();
            $pagePayload = [
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'canonicalUrl' => $currentPage === 1 ? ($publicPath ?: '/').($publicPath ? '/' : '') : $this->joinPublicPath($publicPath, "page/{$currentPage}/"),
                'title' => ($this->site->long_name ?? config('app.name')).' — Carlos Dagorret',
                'posts' => $postsPayload,
                'html' => StaticHtmlCleaner::clean($listingHtml),
            ];

            File::put($dataRoot."/page-{$currentPage}.json", json_encode($pagePayload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $htmlPath = $currentPage === 1 ? $this->targetFolder.'/index.html' : $this->targetFolder."/page/{$currentPage}/index.html";
            File::ensureDirectoryExists(dirname($htmlPath));
            $this->putHtml($htmlPath, view('site.index', $viewData)->render());
            unset($postsPayload, $entries, $viewData, $pagePayload, $listingHtml);
            gc_collect_cycles();
        }
    }

    protected function generateFeed(string $fullBaseUrl): void
    {
        $limit = max((int) config('static_cms.max_feed_items', 50), 1);
        $posts = $this->publishedEntries()
            ->where('type', Post::TYPE_POST)
            ->whereNotNull('slug')
            ->whereNotNull('created_at')
            ->with('category:id,name')
            ->select(['id', 'slug', 'title', 'category_id', 'created_at'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->cursor();
        $feedPath = $this->targetFolder.'/feed.xml';
        $feedFile = fopen($feedPath, 'wb');
        if ($feedFile === false) {
            throw new RuntimeException("No se pudo escribir [{$feedPath}].");
        }

        $siteTitle = htmlspecialchars($this->site->long_name ?? $this->site->name ?? config('app.name'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $siteDescription = htmlspecialchars($this->site->description ?? $this->site->long_name ?? config('app.name'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $feedLink = htmlspecialchars("{$fullBaseUrl}/", ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $atomSelfLink = htmlspecialchars("{$fullBaseUrl}/feed.xml", ENT_XML1 | ENT_QUOTES, 'UTF-8');

        try {
            fwrite($feedFile, '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL);
            fwrite($feedFile, '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">'.PHP_EOL);
            fwrite($feedFile, '  <channel>'.PHP_EOL);
            fwrite($feedFile, "    <title>{$siteTitle}</title>".PHP_EOL);
            fwrite($feedFile, "    <link>{$feedLink}</link>".PHP_EOL);
            fwrite($feedFile, "    <description>{$siteDescription}</description>".PHP_EOL);
            fwrite($feedFile, '    <language>es</language>'.PHP_EOL);
            fwrite($feedFile, "    <atom:link href=\"{$atomSelfLink}\" rel=\"self\" type=\"application/rss+xml\" />".PHP_EOL);

            foreach ($posts as $post) {
                $url = htmlspecialchars("{$fullBaseUrl}/{$post->slug}/", ENT_XML1 | ENT_QUOTES, 'UTF-8');
                
                // Sanitización de caracteres invisibles Unicode (Zero-Width Space, BOM, etc.)
                $cleanTitle = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $post->title);
                $title = htmlspecialchars($cleanTitle, ENT_XML1 | ENT_QUOTES, 'UTF-8');

                fwrite($feedFile, '    <item>'.PHP_EOL);
                fwrite($feedFile, "      <title>{$title}</title>".PHP_EOL);
                fwrite($feedFile, "      <link>{$url}</link>".PHP_EOL);
                fwrite($feedFile, "      <guid>{$url}</guid>".PHP_EOL);
                fwrite($feedFile, '      <pubDate>'.$post->created_at->toRssString().'</pubDate>'.PHP_EOL);
                if ($post->category) {
                    $cleanCategory = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $post->category->name);
                    fwrite($feedFile, '      <category>'.htmlspecialchars($cleanCategory, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</category>'.PHP_EOL);
                }
                fwrite($feedFile, '    </item>'.PHP_EOL);
            }
            fwrite($feedFile, '  </channel>'.PHP_EOL.'</rss>'.PHP_EOL);
        } finally {
            fclose($feedFile);
        }
    }

    protected function streamArchiveDay(string $path, string $year, string $month, string $day, int $total, string $publicPath): void
    {
        $marker = '__CMS_FARO_STREAMED_ARCHIVE_POSTS__';
        $skeleton = StaticHtmlCleaner::clean(view('site.archive.day', [
            'year' => $year, 'month' => $month, 'day' => $day,
            'posts' => [], 'postsStreamMarker' => $marker, 'totalPosts' => $total,
            'site' => $this->site, 'subdir' => $publicPath, 'subdirUrl' => $publicPath,
            'canonicalPath' => "archive/{$year}/{$month}/{$day}",
            'staticAssets' => $this->staticAssets,
            'generatedMenu' => $this->menuHtml($this->joinPublicPath($publicPath, "archive/{$year}/{$month}/{$day}/")),
        ])->render());
        $position = strpos($skeleton, $marker);
        if ($position === false) {
            throw new RuntimeException('No se encontró el marcador de streaming del archivo diario.');
        }

        $temporary = $path.'.tmp-'.bin2hex(random_bytes(5));
        $handle = fopen($temporary, 'xb');
        if ($handle === false) {
            throw new RuntimeException("No se pudo crear el archivo temporal [{$temporary}].");
        }

        try {
            fwrite($handle, substr($skeleton, 0, $position));
            $date = "{$year}-{$month}-{$day}";
            foreach ($this->keysetPages($this->archiveDayQuery($date), $this->chunkSize(), $this->chunkSize()) as $posts) {
                foreach ($posts as $post) {
                    fwrite($handle, StaticHtmlCleaner::clean(view('site.archive.partials.post', [
                        'post' => $post,
                        'subdirUrl' => $publicPath,
                    ])->render()));
                }
                unset($posts);
            }
            fwrite($handle, substr($skeleton, $position + strlen($marker)));
        } finally {
            fclose($handle);
        }

        if (! rename($temporary, $path)) {
            File::delete($temporary);
            throw new RuntimeException("No se pudo publicar atómicamente [{$path}].");
        }
    }

    protected function archiveDayQuery(string $date): Builder
    {
        return $this->publishedEntries()
            ->where('type', Post::TYPE_POST)
            ->whereRaw('substr(created_at, 1, 10) = ?', [$date])
            ->with('category:id,name')
            ->select(['id', 'slug', 'title', 'category_id', 'created_at']);
    }

    protected function archivePeriods(int $length, ?string $prefix = null): Collection
    {
        $query = $this->publishedEntries()
            ->where('type', Post::TYPE_POST)
            ->whereNotNull('slug')
            ->whereNotNull('created_at')
            ->selectRaw("substr(created_at, 1, {$length}) as period, count(*) as total")
            ->groupBy('period')
            ->orderByDesc('period');
        if ($prefix !== null) {
            $query->whereRaw('substr(created_at, 1, ?) = ?', [strlen($prefix), $prefix]);
        }

        return $query->get()->map(fn ($row) => (object) ['period' => (string) $row->period, 'total' => (int) $row->total]);
    }

    protected function categoriesWithPublishedPosts(bool $withCount = false): Builder
    {
        $query = Category::query()
            ->where('site_id', $this->site->getKey())
            ->whereHas('posts', fn (Builder $posts) => $posts->where('status', Post::STATUS_PUBLISHED)->where('type', Post::TYPE_POST))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
        if ($withCount) {
            $query->withCount(['posts as published_posts_count' => fn (Builder $posts) => $posts->where('status', Post::STATUS_PUBLISHED)->where('type', Post::TYPE_POST)]);
        }

        return $query;
    }

    protected function listingQuery(): Builder
    {
        return $this->publishedEntries()
            ->where('type', Post::TYPE_POST)
            ->whereNotNull('slug')
            ->with('category.parent')
            ->select($this->listingColumns());
    }

    protected function publishedEntries(): Builder
    {
        return Post::query()
            ->where(function (Builder $query): void {
                $query->where('site_id', $this->site->getKey())
                    ->orWhere('site_id', $this->site->short_name);
                if (Schema::hasColumn('sites', 'slug') && filled($this->site->getAttribute('slug'))) {
                    $query->orWhere('site_id', $this->site->getAttribute('slug'));
                }
            })
            ->where('status', Post::STATUS_PUBLISHED);
    }

    /** @return \Generator<int, Collection<int, Post>> */
    protected function keysetPages(Builder $baseQuery, int $firstPageSize, int $pageSize, ?int $maxPages = null): \Generator
    {
        $page = 1;
        $cursorDate = null;
        $cursorId = null;

        while ($maxPages === null || $page <= $maxPages) {
            $limit = $page === 1 ? $firstPageSize : $pageSize;
            $query = clone $baseQuery;
            if ($cursorId !== null) {
                $query->where(function (Builder $after) use ($cursorDate, $cursorId): void {
                    $after->whereRaw("coalesce(created_at, '0000-00-00 00:00:00') < ?", [$cursorDate])
                        ->orWhere(function (Builder $same) use ($cursorDate, $cursorId): void {
                            $same->whereRaw("coalesce(created_at, '0000-00-00 00:00:00') = ?", [$cursorDate])
                                ->where('id', '>', $cursorId);
                        });
                });
            }
            $entries = $query
                ->orderByRaw("coalesce(created_at, '0000-00-00 00:00:00') desc")
                ->orderBy('id')
                ->limit($limit)
                ->get();
            if ($entries->isEmpty()) {
                if ($page === 1) {
                    yield 1 => $entries;
                }
                break;
            }

            yield $page => $entries;
            $last = $entries->last();
            $cursorDate = $last->created_at?->format('Y-m-d H:i:s') ?? '0000-00-00 00:00:00';
            $cursorId = (int) $last->id;
            $page++;
        }
    }

    protected function listingColumns(): array
    {
        $columns = ['id', 'slug', 'title', 'body', 'type', 'category_id', 'keywords', 'created_at', 'updated_at'];
        if (Schema::hasColumn('posts', 'has_math')) {
            $columns[] = 'has_math';
        }

        return $columns;
    }

    protected function putHtml(string $path, string $html): void
    {
        File::put($path, StaticHtmlCleaner::clean($html));
    }

    protected function menuHtml(string $currentPath): string
    {
        return (new MenuRenderer)->renderStructure($this->menuStructure, $currentPath);
    }

    protected function resourceSnapshot(string $phase, int $records = 0, int $batches = 0): void
    {
        if (method_exists($this->command, 'resourceSnapshot')) {
            $this->command->resourceSnapshot($phase, $records, $batches);
        }
    }

    protected function chunkSize(): int
    {
        return min(max((int) config('static_cms.build_chunk_size', 1000), 100), 5000);
    }

    protected function indentJson(string $json): string
    {
        return '    '.str_replace("\n", "\n    ", $json);
    }

    protected function baseUrl(): string
    {
        return $this->site->publicOrigin();
    }

    protected function resetGeneratedDirectory(string $path): void
    {
        if (File::exists($path) || is_link($path)) {
            $this->deleteGeneratedDirectory($path);
        }
        if (! File::makeDirectory($path, 0755, true) && ! File::isDirectory($path)) {
            throw new RuntimeException("No se pudo crear el directorio generado [{$path}].");
        }
    }

    protected function deleteGeneratedDirectory(string $path): void
    {
        $root = realpath($this->targetFolder);
        $normalizedPath = rtrim(str_replace('\\', '/', $path), '/');
        if ($root === false) {
            throw new RuntimeException("No se pudo resolver el dist_path [{$this->targetFolder}].");
        }
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if (is_link($path) || dirname($normalizedPath) !== $root || ! in_array(basename($normalizedPath), self::GENERATED_DIRECTORIES, true)) {
            throw new RuntimeException("Se rechazo la limpieza del directorio generado [{$path}].");
        }
        $canonicalPath = realpath($path);
        if ($canonicalPath === false || dirname(rtrim(str_replace('\\', '/', $canonicalPath), '/')) !== $root || ! File::deleteDirectory($canonicalPath)) {
            throw new RuntimeException("No se pudo limpiar de forma segura el directorio generado [{$path}].");
        }
    }

    protected function serializePost(Post $entry, string $publicPath): array
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
            'keywords' => array_values(array_filter(array_map('trim', explode(',', (string) $entry->keywords)))),
            'excerpt' => method_exists($entry, 'getExcerpt') ? $entry->getExcerpt(30) : PostBodyRenderer::excerpt($entry->body ?? '', 30),
            'date' => $entry->created_at?->format('Y-m-d'),
        ];
    }

    protected function buildCategoryPaths(): array
    {
        $categories = Category::query()->where('site_id', $this->site->getKey())->get(['id', 'parent_id', 'name'])->keyBy('id');
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

            return $paths[$category->id] = $parent ? $resolve($parent, $visited).' / '.$category->name : $category->name;
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
        return method_exists($this->site, 'publicPath') ? $this->site->publicPath() : '';
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
