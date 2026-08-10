<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Menu;
use App\Models\Post;
use App\Models\Site;
use App\Services\SitePublishedMediaSelector;
use App\Services\StaticContentCompiler;
use App\Services\StaticDistPathResolver;
use App\Services\StaticSchemaGenerator;
use App\Services\StaticViteAssetPublisher;
use App\Services\StaticViteAssetResolver;
use App\Support\StaticViteAssets;
use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class SiteBuildCommand extends Command
{
    protected const ENTRY_MANIFEST = '.cms-faro-entry.json';

    protected const STRUCTURAL_DIRECTORIES = [
        'archive',
        'assets',
        'build',
        'category',
        'data',
        'page',
        'sitemaps',
        'vendor',
    ];

    protected $signature = 'site:build
        {site_id : ID, slug o short_name del sitio a compilar}
        {--scope=all : Alcance operativo: all, posts, logo}
        {--post= : ID de post para regenerar solo ese articulo}
        {--F|force}
        {--R|resource}';

    protected $description = 'Orquestador modular tipo NASA con incrementalidad real por Base de Datos y Cursor';

    protected ?StaticViteAssets $staticAssets = null;

    protected ?string $viteBuildPath = null;

    protected ?string $staticOutputSignature = null;

    protected bool $resourceEnabled = false;

    public function handle(): int
    {
        $siteIdentifier = trim((string) $this->argument('site_id'));
        $force = (bool) $this->option('force');
        $resource = (bool) $this->option('resource');
        $this->resourceEnabled = $resource;

        try {
            $site = $this->resolveSite($siteIdentifier);
            $section = $this->resolveScope((string) $this->option('scope'));
            $postId = $this->resolvePostId();
            $targetFolder = $this->resolveDistPath($site);
            $viteResolver = new StaticViteAssetResolver;
            $this->staticAssets = $viteResolver->resolve($this->publicPath($site));
            $this->viteBuildPath = $viteResolver->buildPath();
            $this->staticOutputSignature = $this->calculateStaticOutputSignature($site);
            $this->ensureBuildDirectory($targetFolder);
        } catch (RuntimeException $exception) {
            $this->error('❌ '.$exception->getMessage());

            return Command::FAILURE;
        }

        if (trim((string) $site->domain) === '') {
            $this->error("❌ El sitio [{$site->short_name}] no tiene dominio publico configurado en sites.domain.");

            return Command::FAILURE;
        }

        $this->info("🚀 [Orquestador NASA] Iniciando para: {$site->long_name} | Sitio: {$site->short_name} | Scope: {$section}");
        $this->comment("   📁 DIST_PATH: {$targetFolder}");
        $this->resourceSnapshot('inicio');

        if ($postId !== null) {
            $this->removeObsoleteVendorAssets($targetFolder);

            return $this->compileSinglePost($site, $targetFolder, $postId, $resource);
        }

        try {
            if ($force && $section !== 'logo') {
                $this->warn('🧹 Opcion --force activada. Invalidando la firma y forzando rebuild completo...');
                $this->cleanDistForForcedBuild($targetFolder);
            }

            $this->removeObsoleteVendorAssets($targetFolder);
            $this->synchronizePublishedEntryFolders($site, $targetFolder);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('❌ Error al preparar la salida estatica: '.$exception->getMessage());

            return Command::FAILURE;
        }

        if ($section === 'logo') {
            try {
                $this->processMediaAssets($site, $targetFolder);
                $this->publishStaticFiles($targetFolder);
            } catch (Throwable $exception) {
                report($exception);
                $this->error('❌ Error al procesar assets: '.$exception->getMessage());

                return Command::FAILURE;
            }

            $this->info("✔ Assets y logos publicados en {$targetFolder}");

            return Command::SUCCESS;
        }

        // ===================================================================
        // 🚀 ETAPA 1: BUCLE WHILE CON FUENTE DE VERDAD EN BD + CURSOR
        // ===================================================================
        $recoverMissingEntryOutputs = ! $force
            && $this->hasMissingPublishedEntryOutput($site, $targetFolder, $section);
        $staticOutputChanged = $this->staticOutputSignatureChanged($targetFolder);
        $rebuildAllEntries = $force || $recoverMissingEntryOutputs || $staticOutputChanged;

        if ($recoverMissingEntryOutputs) {
            $this->warn('⚠️  La BD marca el contenido como compilado, pero faltan HTML individuales. Se reconstruyen las entradas publicadas.');
        }

        if ($staticOutputChanged && ! $force) {
            $this->warn('⚠️  Cambio el manifest de Vite o una plantilla publica. Se reconstruyen las entradas para mantener referencias de assets coherentes.');
        }

        $compiler = new StaticContentCompiler(
            $this,
            $site,
            $targetFolder,
            $rebuildAllEntries,
            $resource,
            $this->resolvedStaticAssets(),
        );

        // Conteo base condicional para saber el trabajo pendiente real
        $totalEntries = $this->publishedSitePosts($site)
            ->when($section === 'posts', fn ($query) => $this->scopeOnlyPosts($query))
            ->when(! $rebuildAllEntries, function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('static_built_at')
                        ->orWhereColumn('updated_at', '>', 'static_built_at');
                });
            })
            ->count();

        $perPage = $this->buildChunkSize();
        $totalPages = (int) (ceil($totalEntries / $perPage) ?: 1);

        $this->info("📊 Registros sucios/pendientes en BD: {$totalEntries} | Bloques: {$perPage} | Páginas a procesar: ".($totalEntries > 0 ? $totalPages : 0));

        $processedCount = 0;

        if ($totalEntries > 0) {
            $query = $this->publishedSitePosts($site)
                ->with('category.parent')
                ->when($section === 'posts', fn ($query) => $this->scopeOnlyPosts($query))
                ->when(! $rebuildAllEntries, function ($query) {
                    $query->where(function ($q) {
                        $q->whereNull('static_built_at')
                            ->orWhereColumn('updated_at', '>', 'static_built_at');
                    });
                })
                ->orderBy('id', 'asc')
                ->select($this->entryColumns());

            $batch = 0;
            $query->chunkById($perPage, function ($postsChunk) use ($compiler, &$processedCount, &$batch, $totalPages): void {
                $compiler->compile($postsChunk);
                $chunkIds = $postsChunk->modelKeys();
                Post::query()->whereKey($chunkIds)->update(['static_built_at' => now()]);
                $processedCount += count($chunkIds);
                $batch++;
                $this->resourceSnapshot('posts', $processedCount, $batch, $totalPages);
                unset($chunkIds);
                gc_collect_cycles();
            }, 'id');
        }

        $this->info('✔️ Fin del procesamiento de HTMLs individuales.');
        $this->resourceSnapshot('posts', $processedCount, $totalEntries > 0 ? (int) ceil($processedCount / $perPage) : 0);

        // ===================================================================
        // 📦 ETAPA 2: ESTRUCTURAS GLOBALES (Se actualizan siempre rápido)
        // ===================================================================
        try {
            $this->regenerateGlobalStructures($site, $targetFolder);
            $this->synchronizePublishedEntryFolders($site, $targetFolder);
            $this->processMediaAssets($site, $targetFolder);
            $this->publishStaticFiles($targetFolder);
            $this->publishViteAssets($targetFolder);
            $this->writeStaticOutputSignature($targetFolder);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('❌ Error al finalizar la generacion estatica: '.$exception->getMessage());

            return Command::FAILURE;
        }

        // Reporte Final de Cierre
        $executionTime = round(microtime(true) - $this->startedAt(), 2);
        $peakMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->newLine();
        $this->info('📊 --- REPORTE DE RENDIMIENTO ULTRA (NASA MODE) ---');
        $this->info("⏱️  Tiempo total de ejecución: {$executionTime} segundos");

        $peakMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 1);
        $memoryLimit = ini_get('memory_limit');

        $this->info("🧠 Pico máximo de memoria RAM: {$peakMemory} MB | PHP memory_limit: {$memoryLimit}");
        $this->info('-------------------------------------------------------');
        $this->resourceSnapshot('fin', $processedCount);

        return Command::SUCCESS;
    }

    protected function compileSinglePost(Site $site, string $targetFolder, int $postId, bool $resource): int
    {
        $post = $this->publishedSitePosts($site)
            ->whereKey($postId)
            ->with('category.parent')
            ->select($this->entryColumns())
            ->first();

        if ($post
            && $this->staticOutputSignatureChanged($targetFolder)
            && $this->hasManagedEntryOtherThan($targetFolder, (string) $post->slug)) {
            $this->error('❌ Cambio el manifest de Vite o una plantilla publica. Ejecuta un build completo antes de continuar con builds incrementales.');

            return Command::FAILURE;
        }

        if (! $post) {
            $inactivePost = $this->siteScopedPosts($site)
                ->whereKey($postId)
                ->select(['id', 'slug', 'status'])
                ->first();

            if ($inactivePost) {
                $this->deleteEntryFolder($targetFolder, (string) $inactivePost->slug);
                $this->warn("⚠️  Articulo [{$postId}] omitido: estado actual [{$inactivePost->status}], solo se compila status=published.");

                return $this->finalizeSinglePostBuild($site, $targetFolder);
            }

            if ($this->hasManagedEntryForPost($site, $targetFolder, $postId)) {
                $this->warn("⚠️  El articulo [{$postId}] ya no existe; se elimina su salida estatica y se regeneran los indices globales.");

                return $this->finalizeSinglePostBuild($site, $targetFolder);
            }

            $this->error("❌ No existe el post [{$postId}] para el sitio [{$site->short_name}].");

            return Command::FAILURE;
        }

        $compiler = new StaticContentCompiler(
            $this,
            $site,
            $targetFolder,
            true,
            $resource,
            $this->resolvedStaticAssets(),
        );
        $compiler->compile(collect([$post]));

        Post::whereKey($post->getKey())->update([
            'static_built_at' => now(),
        ]);

        $this->info("✔️ Articulo [{$post->id}] {$post->slug} compilado en {$this->joinPath($targetFolder, "{$post->slug}/index.html")}");

        return $this->finalizeSinglePostBuild($site, $targetFolder);
    }

    protected function regenerateGlobalStructures(Site $site, string $targetFolder): void
    {
        $this->info('📦 Regenerando índices globales dinámicos (JSON, Portadas, Sitemap)...');

        $generator = new StaticSchemaGenerator(
            $this,
            $site,
            $targetFolder,
            $this->resolvedStaticAssets(),
        );
        $generator->build();
    }

    protected function finalizeSinglePostBuild(Site $site, string $targetFolder): int
    {
        try {
            $this->regenerateGlobalStructures($site, $targetFolder);
            $this->synchronizePublishedEntryFolders($site, $targetFolder);
            $this->processMediaAssets($site, $targetFolder);
            $this->publishStaticFiles($targetFolder);
            $this->publishViteAssets($targetFolder);
            $this->writeStaticOutputSignature($targetFolder);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('❌ Error al finalizar la compilacion incremental: '.$exception->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function startedAt(): float
    {
        return defined('LARAVEL_START') ? (float) constant('LARAVEL_START') : microtime(true);
    }

    public function resourceSnapshot(string $phase, int $records = 0, int $batches = 0, ?int $totalBatches = null): void
    {
        if (! $this->resourceEnabled) {
            return;
        }

        $used = round(memory_get_usage(false) / 1024 / 1024, 2);
        $current = round(memory_get_usage(true) / 1024 / 1024, 2);
        $peak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $elapsed = round(microtime(true) - $this->startedAt(), 2);
        $batchText = $totalBatches === null ? (string) $batches : "{$batches}/{$totalBatches}";
        $this->comment("   ⏱️ [{$phase}] procesados={$records} lotes={$batchText} uso={$used} MB memoria={$current} MB pico={$peak} MB tiempo={$elapsed}s");
    }

    protected function buildChunkSize(): int
    {
        return min(max((int) config('static_cms.build_chunk_size', 1000), 100), 5000);
    }

    protected function resolveSite(string $siteIdentifier): Site
    {
        if ($siteIdentifier === '') {
            throw new RuntimeException('Debes indicar el ID, slug o short_name del sitio. Ejemplo: php artisan site:build 12 --scope=all');
        }

        if (ctype_digit($siteIdentifier)) {
            $site = Site::query()->whereKey((int) $siteIdentifier)->first();

            if ($site) {
                return $site;
            }
        }

        $siteQuery = Site::query()->where('short_name', $siteIdentifier);

        if (Schema::hasColumn('sites', 'slug')) {
            $siteQuery->orWhere('slug', $siteIdentifier);
        }

        $site = $siteQuery->first();

        if (! $site) {
            throw new RuntimeException("No existe un sitio con ID, slug o short_name [{$siteIdentifier}]. No se ejecuta compilacion.");
        }

        return $site;
    }

    protected function resolveScope(string $scope): string
    {
        $scope = trim($scope) !== '' ? trim($scope) : 'all';

        if (! in_array($scope, ['all', 'posts', 'logo'], true)) {
            throw new RuntimeException("Scope invalido [{$scope}]. Usa uno de: all, posts, logo.");
        }

        return $scope;
    }

    protected function resolvePostId(): ?int
    {
        $post = $this->option('post');

        if (! filled($post)) {
            return null;
        }

        $postId = filter_var($post, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($postId === false) {
            throw new RuntimeException('La opcion --post debe ser un ID numerico positivo.');
        }

        return $postId;
    }

    protected function resolveDistPath(Site $site): string
    {
        if (! Schema::hasColumn('sites', 'dist_path')) {
            throw new RuntimeException('La tabla sites no tiene la columna dist_path. Agrega esa columna antes de compilar.');
        }

        $distPath = trim((string) $site->getAttribute('dist_path'));

        if ($distPath === '') {
            throw new RuntimeException("El sitio [{$site->short_name}] no tiene dist_path configurado.");
        }

        try {
            return (new StaticDistPathResolver)->resolve($distPath);
        } catch (RuntimeException $exception) {
            throw new RuntimeException("dist_path invalido para [{$site->short_name}]: {$exception->getMessage()}", previous: $exception);
        }
    }

    protected function ensureBuildDirectory(string $distPath): void
    {
        try {
            File::ensureDirectoryExists($distPath, 0755, true);
        } catch (Throwable $exception) {
            throw new RuntimeException("No se pudo crear el directorio dist_path [{$distPath}]: {$exception->getMessage()}");
        }

        if (! File::isDirectory($distPath)) {
            throw new RuntimeException("dist_path [{$distPath}] no es un directorio valido.");
        }

        if (! is_writable($distPath)) {
            throw new RuntimeException("dist_path [{$distPath}] no tiene permisos de escritura.");
        }
    }

    protected function siteScopedPosts(Site $site)
    {
        return Post::query()
            ->where(function ($query) use ($site): void {
                $query->where('site_id', $site->id)
                    ->orWhere('site_id', $site->short_name);

                if (Schema::hasColumn('sites', 'slug') && filled($site->getAttribute('slug'))) {
                    $query->orWhere('site_id', $site->getAttribute('slug'));
                }
            });
    }

    protected function publishedSitePosts(Site $site)
    {
        return $this->siteScopedPosts($site)
            ->where('status', Post::STATUS_PUBLISHED);
    }

    protected function scopeOnlyPosts($query)
    {
        return $query->where('type', Post::TYPE_POST);
    }

    protected function hasMissingPublishedEntryOutput(Site $site, string $targetFolder, string $section): bool
    {
        return $this->publishedSitePosts($site)
            ->when($section === 'posts', fn ($query) => $this->scopeOnlyPosts($query))
            ->whereNotNull('slug')
            ->select(['id', 'slug'])
            ->orderBy('id')
            ->lazyById(2000, 'id')
            ->contains(function (Post $entry) use ($targetFolder): bool {
                $slug = trim((string) $entry->slug, '/\\');

                return $this->isSafeEntrySlug($slug)
                    && ! File::isFile($this->joinPath($targetFolder, "{$slug}/index.html"));
            });
    }

    protected function synchronizePublishedEntryFolders(Site $site, string $targetFolder): int
    {
        $deleted = 0;
        $batch = [];
        $chunkSize = $this->buildChunkSize();

        foreach ($this->entryDirectories($targetFolder) as $directory) {
            $manifest = $this->entryManifest($directory);
            if (! $this->isManagedEntryDirectory($directory, $manifest)) {
                continue;
            }
            if ($manifest !== [] && ! $this->entryManifestBelongsToSite($manifest, $site)) {
                continue;
            }
            $batch[] = ['directory' => $directory, 'manifest' => $manifest];
            if (count($batch) >= $chunkSize) {
                $deleted += $this->synchronizeEntryBatch($site, $targetFolder, $batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $deleted += $this->synchronizeEntryBatch($site, $targetFolder, $batch);
        }

        if ($deleted > 0) {
            $this->warn("🧹 Limpieza de vida/muerte: {$deleted} directorios HTML huerfanos eliminados en {$targetFolder}");
        }

        return $deleted;
    }

    /** @param list<array{directory:string,manifest:array}> $batch */
    protected function synchronizeEntryBatch(Site $site, string $targetFolder, array $batch): int
    {
        $ids = [];
        $slugs = [];
        foreach ($batch as $entry) {
            $id = filter_var($entry['manifest']['post_id'] ?? null, FILTER_VALIDATE_INT);
            $slug = $this->entrySlugFromDirectory($entry['directory'], $entry['manifest']);
            if ($id !== false && $id !== null) {
                $ids[] = (int) $id;
            } elseif ($slug !== '') {
                $slugs[] = $slug;
            }
        }

        $activeIds = [];
        $activeSlugs = [];
        $query = $this->publishedSitePosts($site)->select(['id', 'slug']);
        $query->where(function ($active) use ($ids, $slugs): void {
            if ($ids !== []) {
                $active->whereIn('id', $ids);
            }
            if ($slugs !== []) {
                $ids === [] ? $active->whereIn('slug', $slugs) : $active->orWhereIn('slug', $slugs);
            }
        });
        foreach ($query->cursor() as $post) {
            $activeIds[(int) $post->id] = trim((string) $post->slug, '/\\');
            $activeSlugs[trim((string) $post->slug, '/\\')] = true;
        }

        $deleted = 0;
        foreach ($batch as $entry) {
            $id = filter_var($entry['manifest']['post_id'] ?? null, FILTER_VALIDATE_INT);
            $slug = $this->entrySlugFromDirectory($entry['directory'], $entry['manifest']);
            $active = $id !== false && $id !== null
                ? (($activeIds[(int) $id] ?? null) === $slug)
                : isset($activeSlugs[$slug]);
            if (! $active) {
                $this->deleteManagedEntryDirectory($targetFolder, $entry['directory']);
                $deleted++;
            }
        }

        return $deleted;
    }

    /** @return \Generator<int, string> */
    protected function entryDirectories(string $targetFolder): \Generator
    {
        $iterator = new FilesystemIterator($targetFolder, FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $entry) {
            if ($entry->isLink()) {
                $this->warn("⚠️  Se omite enlace simbolico durante la limpieza de entradas [{$entry->getPathname()}].");

                continue;
            }
            if ($entry->isDir()) {
                yield $entry->getPathname();
            }
        }
    }

    protected function isManagedEntryDirectory(string $directory, array $manifest): bool
    {
        return $manifest !== []
            && ! in_array(basename($directory), self::STRUCTURAL_DIRECTORIES, true);
    }

    protected function entryManifest(string $directory): array
    {
        $manifestPath = $this->joinPath($directory, self::ENTRY_MANIFEST);

        if (! File::isFile($manifestPath)) {
            return [];
        }

        try {
            $manifest = json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($manifest) ? $manifest : [];
    }

    protected function entryManifestBelongsToSite(array $manifest, Site $site): bool
    {
        $siteTokens = array_filter([
            (string) $site->getKey(),
            (string) $site->short_name,
            Schema::hasColumn('sites', 'slug') ? (string) $site->getAttribute('slug') : null,
        ]);

        return in_array((string) ($manifest['site_id'] ?? ''), $siteTokens, true)
            || in_array((string) ($manifest['site_short_name'] ?? ''), $siteTokens, true);
    }

    protected function hasManagedEntryForPost(Site $site, string $targetFolder, int $postId): bool
    {
        foreach ($this->entryDirectories($targetFolder) as $directory) {
            $manifest = $this->entryManifest($directory);

            if ($manifest === [] || ! $this->entryManifestBelongsToSite($manifest, $site)) {
                continue;
            }

            if ((string) ($manifest['post_id'] ?? '') === (string) $postId) {
                return true;
            }
        }

        return false;
    }

    protected function entrySlugFromDirectory(string $directory, array $manifest): string
    {
        return trim((string) ($manifest['slug'] ?? basename($directory)), '/\\');
    }

    protected function deleteEntryFolder(string $targetFolder, string $slug): bool
    {
        $slug = trim($slug, '/\\');

        if (! $this->isSafeEntrySlug($slug)) {
            return false;
        }

        $entryFolder = $this->joinPath($targetFolder, $slug);

        if (! File::isDirectory($entryFolder)) {
            return false;
        }

        if ($this->entryManifest($entryFolder) === []) {
            return false;
        }

        $this->deleteManagedEntryDirectory($targetFolder, $entryFolder);

        return true;
    }

    protected function isSafeEntrySlug(string $slug): bool
    {
        return $slug !== ''
            && ! str_contains($slug, '..')
            && ! str_contains($slug, '/')
            && ! str_contains($slug, '\\');
    }

    protected function entryColumns(): array
    {
        $columns = [
            'id',
            'site_id',
            'slug',
            'title',
            'body',
            'keywords',
            'type',
            'category_id',
            'status',
            'published_at',
            'created_at',
            'updated_at',
            'static_built_at',
        ];

        if (Schema::hasColumn('posts', 'has_math')) {
            $columns[] = 'has_math';
        }

        return $columns;
    }

    protected function publicPath(Site $site): string
    {
        return $site->publicPath();
    }

    protected function resolvedStaticAssets(): StaticViteAssets
    {
        if (! $this->staticAssets) {
            throw new RuntimeException('Los assets estaticos de Vite no fueron resueltos.');
        }

        return $this->staticAssets;
    }

    protected function calculateStaticOutputSignature(Site $site): string
    {
        if (! $this->viteBuildPath) {
            throw new RuntimeException('No se resolvio el directorio de build de Vite.');
        }

        $manifestPath = $this->viteBuildPath.'/manifest.json';
        $context = hash_init('sha256');
        hash_update_file($context, $manifestPath);

        hash_update($context, json_encode([
            'public_path' => $this->publicPath($site),
            'domain' => rtrim((string) $site->domain, '/'),
            'pagination' => [
                'home_first_page_posts' => max((int) config('static_cms.home_first_page_posts', 10), 1),
                'posts_per_home_page' => max((int) config('static_cms.posts_per_home_page', 20), 1),
                'max_home_pages' => max((int) config('static_cms.max_home_pages', 20), 1),
            ],
            'menus' => [
                'locations' => config('static_cms.menu_locations', []),
                'max_depth' => max((int) config('static_cms.menu_max_depth', 3), 1),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        if (Schema::hasTable('menus') && Schema::hasTable('menu_items')) {
            $menus = Menu::query()
                ->where('site_id', $site->getKey())
                ->with(['items' => fn ($query) => $query->orderBy('id')])
                ->orderBy('id')
                ->get()
                ->map(fn ($menu) => [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'slug' => $menu->slug,
                    'location' => $menu->location,
                    'is_active' => $menu->is_active,
                    'items' => $menu->items->map(fn ($item) => [
                        'id' => $item->id,
                        'parent_id' => $item->parent_id,
                        'label' => $item->label,
                        'type' => $item->type,
                        'category_id' => $item->category_id,
                        'post_id' => $item->post_id,
                        'url' => $item->url,
                        'target' => $item->target,
                        'rel' => $item->rel,
                        'sort_order' => $item->sort_order,
                        'is_active' => $item->is_active,
                    ])->all(),
                ])->all();
            hash_update($context, json_encode($menus, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }

        $viewFiles = collect(File::allFiles(resource_path('views/site')))
            ->sortBy(fn ($file) => $file->getPathname())
            ->values();

        foreach ($viewFiles as $viewFile) {
            hash_update($context, $viewFile->getRelativePathname());
            hash_update_file($context, $viewFile->getPathname());
        }

        return hash_final($context);
    }

    protected function staticOutputSignatureChanged(string $targetFolder): bool
    {
        $signaturePath = $this->joinPath($targetFolder, '.cms-faro-build.json');

        if (! File::isFile($signaturePath) || ! $this->staticOutputSignature) {
            return true;
        }

        try {
            $metadata = json_decode(File::get($signaturePath), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return true;
        }

        return ! is_array($metadata)
            || ! hash_equals($this->staticOutputSignature, (string) ($metadata['signature'] ?? ''));
    }

    protected function writeStaticOutputSignature(string $targetFolder): void
    {
        if (! $this->staticOutputSignature) {
            throw new RuntimeException('No se calculo la firma de la salida estatica.');
        }

        File::put(
            $this->joinPath($targetFolder, '.cms-faro-build.json'),
            json_encode([
                'signature' => $this->staticOutputSignature,
                'built_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    protected function hasManagedEntryOtherThan(string $targetFolder, string $slug): bool
    {
        foreach ($this->entryDirectories($targetFolder) as $directory) {
            if (in_array(basename($directory), self::STRUCTURAL_DIRECTORIES, true)
                || $this->entryManifest($directory) === []) {
                continue;
            }

            if (basename($directory) !== trim($slug, '/\\')) {
                return true;
            }
        }

        return false;
    }

    protected function publishViteAssets(string $targetFolder): void
    {
        if (! $this->viteBuildPath) {
            throw new RuntimeException('No se resolvio el directorio de build de Vite.');
        }

        $destination = (new StaticViteAssetPublisher)->publish($this->viteBuildPath, $targetFolder);

        foreach (array_merge(
            $this->resolvedStaticAssets()->stylesheets,
            $this->resolvedStaticAssets()->scripts,
        ) as $relativePath) {
            if (! File::isFile($destination.'/'.$relativePath)) {
                throw new RuntimeException("El asset publicado no existe [{$destination}/{$relativePath}].");
            }
        }

        $this->comment("   ✔️ Build de Vite publicado atomicamente en {$destination}");
    }

    /**
     * Static files overwrite earlier global structures or media on collision.
     * Vite is published afterwards and therefore wins inside its build destination.
     */
    protected function publishStaticFiles(string $targetFolder): void
    {
        $sourcePath = $this->staticFilesSourcePath();

        if (! File::isDirectory($sourcePath)) {
            $this->comment("   ℹ️  No existe la carpeta de estaticos globales: {$sourcePath}. Se omite su publicacion.");

            return;
        }

        try {
            if (! File::copyDirectory($sourcePath, $targetFolder)) {
                throw new RuntimeException("No se pudieron copiar los estaticos globales desde {$sourcePath} hacia {$targetFolder}.");
            }
        } catch (Throwable $exception) {
            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException(
                    "No se pudieron publicar los estaticos globales en {$targetFolder}: {$exception->getMessage()}",
                    previous: $exception,
                );
        }

        $this->info("   ✔️ Estaticos globales publicados en {$targetFolder}");
    }

    protected function staticFilesSourcePath(): string
    {
        return resource_path('static');
    }

    protected function cleanDistForForcedBuild(string $targetFolder): void
    {
        $root = realpath($targetFolder);

        if ($root === false || is_link($targetFolder) || ! File::isDirectory($root)) {
            throw new RuntimeException("No se puede limpiar un dist_path no canonico o simbolico [{$targetFolder}].");
        }

        $signaturePath = rtrim($root, '/').'/.cms-faro-build.json';

        if (is_link($signaturePath)) {
            throw new RuntimeException("Se rechazo una firma simbolica dentro de dist [{$signaturePath}].");
        }

        if (File::isFile($signaturePath) && ! File::delete($signaturePath)) {
            throw new RuntimeException("No se pudo invalidar la firma estatica [{$signaturePath}].");
        }
    }

    protected function deleteManagedEntryDirectory(string $targetFolder, string $directory): void
    {
        $root = realpath($targetFolder);
        $canonicalDirectory = realpath($directory);

        if ($root === false || $canonicalDirectory === false) {
            throw new RuntimeException("No se pudo resolver la ruta canonica antes de limpiar [{$directory}].");
        }

        $root = rtrim(str_replace('\\', '/', $root), '/');
        $canonicalDirectory = rtrim(str_replace('\\', '/', $canonicalDirectory), '/');

        if (is_link($directory)
            || dirname($canonicalDirectory) !== $root
            || ! str_starts_with($canonicalDirectory.'/', $root.'/')
            || in_array(basename($canonicalDirectory), self::STRUCTURAL_DIRECTORIES, true)) {
            throw new RuntimeException("Se rechazo la eliminacion de una salida no administrada [{$directory}].");
        }

        if (! File::deleteDirectory($canonicalDirectory)) {
            throw new RuntimeException("No se pudo eliminar la entrada administrada [{$canonicalDirectory}].");
        }
    }

    protected function removeObsoleteVendorAssets(string $targetFolder): void
    {
        $vendorDirectory = $this->joinPath($targetFolder, 'vendor');

        if (! File::exists($vendorDirectory)) {
            return;
        }

        $root = realpath($targetFolder);
        $canonicalVendor = realpath($vendorDirectory);

        if (
            $root === false
            || $canonicalVendor === false
            || is_link($vendorDirectory)
            || dirname($canonicalVendor) !== $root
            || basename($canonicalVendor) !== 'vendor'
        ) {
            throw new RuntimeException("Se rechazo la limpieza del directorio vendor obsoleto [{$vendorDirectory}].");
        }

        if (! File::deleteDirectory($canonicalVendor)) {
            throw new RuntimeException("No se pudo eliminar el directorio vendor obsoleto [{$canonicalVendor}].");
        }
    }

    protected function processMediaAssets(Site $site, string $targetFolder): void
    {
        $mediaBasePath = trim((string) config('static_cms.media.base_path'), '/');

        if ($mediaBasePath === '') {
            $this->warn('⚠️  static_cms.media.base_path esta vacio. Se omite la publicacion de medios.');

            return;
        }

        $sourcePath = storage_path('app/public/'.$mediaBasePath);
        $destinationPath = $this->joinPath($targetFolder, $mediaBasePath);
        $typeStorage = strtolower(trim((string) config('static_cms.media.type_storage', 'copy')));
        $optimize = (bool) config('static_cms.media.optimize', false);

        $this->comment("   🖼️  Procesando medios: {$sourcePath} -> {$destinationPath} ({$typeStorage})");

        $this->cleanMediaDestination($destinationPath);

        if ($typeStorage !== 'copy') {
            if ($typeStorage !== 'symlink') {
                $this->warn("   ⚠️  type_storage [{$typeStorage}] no reconocido. Se usa copy.");
                $typeStorage = 'copy';
            }
        }

        File::ensureDirectoryExists($destinationPath, 0755, true);

        $publishedSources = 0;

        if (File::isDirectory($sourcePath)) {
            $this->publishMediaDirectoryContents($sourcePath, $destinationPath, $typeStorage);
            $publishedSources++;
        } else {
            $this->warn("   ⚠️  No existe la carpeta de medios generales: {$sourcePath}");
        }

        foreach (app(SitePublishedMediaSelector::class)->forSite($site) as $media) {
            $mediaSource = $this->resolveRequiredSpatieMediaSource($media, $site);

            if ($mediaSource === null) {
                continue;
            }

            $mediaDestination = $this->joinPath($destinationPath, (string) $media->getKey());
            $this->publishMediaDirectory($mediaSource, $mediaDestination, $typeStorage);
            $publishedSources++;
        }

        if ($publishedSources === 0) {
            $this->warn('   ⚠️  No hay medios generales ni medios Spatie para publicar.');

            return;
        }

        $verb = $typeStorage === 'symlink' ? 'enlazados simbolicamente' : 'copiados';
        $this->info("   ✔️ Medios {$verb} en {$destinationPath}");

        if ($optimize && $typeStorage === 'copy') {
            $this->optimizeCopiedMediaAssets($destinationPath);
        }
    }

    protected function resolveRequiredSpatieMediaSource(Media $media, Site $site): ?string
    {
        $mediaPath = $media->getPath();
        $mediaSource = dirname($mediaPath);

        if (is_link($mediaPath) || is_link($mediaSource)) {
            throw new RuntimeException(
                'El medio Spatie requerido usa un enlace simbolico no permitido. '.$this->requiredMediaContext($media, $site),
            );
        }

        if (! File::isFile($mediaPath) || ! File::isDirectory($mediaSource)) {
            $this->warn('   ⚠️  '.$this->missingRequiredMediaMessage($media, $site).' Se omite este medio.');

            return null;
        }

        $realMediaPath = realpath($mediaPath);
        $realMediaSource = realpath($mediaSource);
        $diskRoot = Storage::disk($media->disk)->path('');
        $realDiskRoot = realpath($diskRoot);

        if ($realMediaPath === false
            || $realMediaSource === false
            || $realDiskRoot === false
            || ! $this->pathIsInside($realMediaPath, $realDiskRoot)
            || ! $this->pathIsInside($realMediaSource, $realDiskRoot)) {
            throw new RuntimeException(
                'La ruta del medio Spatie requerido no es segura. '.$this->requiredMediaContext($media, $site),
            );
        }

        foreach (array_keys(array_filter($media->generated_conversions ?? [])) as $conversionName) {
            $conversionPath = $media->getPath((string) $conversionName);

            if (is_link($conversionPath)) {
                throw new RuntimeException(
                    "La conversion [{$conversionName}] usa un enlace simbolico no permitido. "
                    .$this->requiredMediaContext($media, $site),
                );
            }

            if (! File::isFile($conversionPath)) {
                $this->warn(
                    "   ⚠️  Falta la conversion fisica [{$conversionName}]. "
                    .$this->requiredMediaContext($media, $site).' Se omite esta conversion.',
                );

                continue;
            }

            $conversionRoot = realpath(Storage::disk($media->conversions_disk ?: $media->disk)->path(''));
            $realConversionPath = realpath($conversionPath);

            if ($conversionRoot === false
                || $realConversionPath === false
                || ! $this->pathIsInside($realConversionPath, $conversionRoot)
                || ! $this->pathIsInside($realConversionPath, $realMediaSource)) {
                throw new RuntimeException(
                    "La ruta de la conversion [{$conversionName}] no es segura. "
                    .$this->requiredMediaContext($media, $site),
                );
            }
        }

        return $realMediaSource;
    }

    protected function missingRequiredMediaMessage(Media $media, Site $site): string
    {
        return 'Falta el archivo fisico de un medio Spatie requerido. '.$this->requiredMediaContext($media, $site);
    }

    protected function requiredMediaContext(Media $media, Site $site): string
    {
        return "site={$site->short_name}, media_id={$media->getKey()}, file_name={$media->file_name}, "
            ."model_type={$media->model_type}, model_id={$media->model_id}, collection={$media->collection_name}.";
    }

    protected function pathIsInside(string $path, string $root): bool
    {
        return $path === $root
            || str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }

    protected function publishMediaDirectoryContents(string $source, string $destination, string $typeStorage): void
    {
        $this->assertSafeMediaSource($source);

        if ($typeStorage === 'copy') {
            if (! File::copyDirectory($source, $destination)) {
                throw new RuntimeException("No se pudo copiar la carpeta de medios desde {$source} hacia {$destination}.");
            }

            return;
        }

        foreach (new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS) as $item) {
            $this->publishMediaDirectory($item->getPathname(), $this->joinPath($destination, $item->getFilename()), $typeStorage);
        }
    }

    protected function publishMediaDirectory(string $source, string $destination, string $typeStorage): void
    {
        $this->assertSafeMediaSource($source);

        if (File::exists($destination) || is_link($destination)) {
            if ($typeStorage === 'copy' && File::isDirectory($source) && File::isDirectory($destination) && ! is_link($destination)) {
                if (! File::copyDirectory($source, $destination)) {
                    throw new RuntimeException("No se pudo combinar el directorio de medios desde {$source} hacia {$destination}.");
                }

                return;
            }

            throw new RuntimeException("El destino de medios [{$destination}] colisiona con otra fuente.");
        }

        File::ensureDirectoryExists(dirname($destination), 0755, true);

        if ($typeStorage === 'symlink') {
            File::link($source, $destination);

            return;
        }

        if (File::isDirectory($source)) {
            if (! File::copyDirectory($source, $destination)) {
                throw new RuntimeException("No se pudo copiar el directorio de medios desde {$source} hacia {$destination}.");
            }

            return;
        }

        if (! File::copy($source, $destination)) {
            throw new RuntimeException("No se pudo copiar el archivo de medios desde {$source} hacia {$destination}.");
        }
    }

    protected function assertSafeMediaSource(string $source): void
    {
        if (is_link($source)) {
            throw new RuntimeException("La fuente de medios [{$source}] no puede ser un enlace simbolico.");
        }

        if (! File::isDirectory($source)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException("La fuente de medios contiene un enlace simbolico no permitido: {$item->getPathname()}");
            }
        }
    }

    protected function optimizeCopiedMediaAssets(string $destinationPath): void
    {
        $driver = strtolower(trim((string) config('static_cms.media.driver', 'none')));

        if ($driver === '' || $driver === 'none') {
            $this->comment('   🧩 Optimizacion de medios omitida: driver none.');

            return;
        }

        if (! in_array($driver, ['gd', 'cwebp'], true)) {
            $this->warn("   ⚠️  Driver de medios [{$driver}] no reconocido. Se omite la optimizacion.");

            return;
        }

        $processed = 0;
        $failed = 0;

        foreach ($this->lazyImageFiles($destinationPath) as $filePath) {
            $webpPath = $this->webpPathFor($filePath);

            $ok = match ($driver) {
                'gd' => $this->createWebpWithGd($filePath, $webpPath),
                'cwebp' => $this->createWebpWithCwebp($filePath, $webpPath),
            };

            $ok ? $processed++ : $failed++;
            gc_collect_cycles();
        }

        $this->comment("   🧩 Optimizacion {$driver}: {$processed} webp generados".($failed > 0 ? " | {$failed} fallidos" : '').'.');
    }

    protected function lazyImageFiles(string $destinationPath): iterable
    {
        if (! File::isDirectory($destinationPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($destinationPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());

            if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                continue;
            }

            yield $file->getPathname();
        }
    }

    protected function webpPathFor(string $filePath): string
    {
        return preg_replace('/\.(jpe?g|png)$/i', '.webp', $filePath) ?: ($filePath.'.webp');
    }

    protected function createWebpWithGd(string $sourcePath, string $webpPath): bool
    {
        if (! function_exists('imagewebp')) {
            $this->warn('   ⚠️  Extension GD sin soporte imagewebp. Se omite la optimizacion.');

            return false;
        }

        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $image = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($sourcePath),
            'png' => @imagecreatefrompng($sourcePath),
            default => false,
        };

        if ($image === false) {
            return false;
        }

        try {
            if ($extension === 'png') {
                imagepalettetotruecolor($image);
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }

            return imagewebp($image, $webpPath, 80);
        } finally {
            imagedestroy($image);
            gc_collect_cycles();
        }
    }

    protected function createWebpWithCwebp(string $sourcePath, string $webpPath): bool
    {
        $binary = trim((string) config('static_cms.media.cwebp_path', 'cwebp')) ?: 'cwebp';
        $command = escapeshellarg($binary)
            .' -q 80 '
            .escapeshellarg($sourcePath)
            .' -o '
            .escapeshellarg($webpPath);

        $output = [];
        $exitCode = 1;

        @exec($command, $output, $exitCode);

        return $exitCode === 0 && File::isFile($webpPath);
    }

    protected function joinPath(string $basePath, string $path): string
    {
        return rtrim($basePath, '/\\').'/'.ltrim($path, '/\\');
    }

    protected function cleanMediaDestination(string $destinationPath): void
    {
        if (is_link($destinationPath)) {
            @unlink($destinationPath);

            return;
        }

        if (File::isFile($destinationPath)) {
            File::delete($destinationPath);

            return;
        }

        if (File::isDirectory($destinationPath)) {
            File::deleteDirectory($destinationPath);
        }
    }
}
