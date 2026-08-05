<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Menu;
use App\Models\Post;
use App\Models\Site;
use App\Services\StaticContentCompiler;
use App\Services\StaticDistPathResolver;
use App\Services\StaticSchemaGenerator;
use App\Services\StaticViteAssetPublisher;
use App\Services\StaticViteAssetResolver;
use App\Support\StaticViteAssets;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
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

    public function handle(): int
    {
        $siteIdentifier = trim((string) $this->argument('site_id'));
        $force = (bool) $this->option('force');
        $resource = (bool) $this->option('resource');

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

        if ($postId !== null) {
            $this->publishKatexAssets($targetFolder);

            return $this->compileSinglePost($site, $targetFolder, $postId, $resource);
        }

        try {
            if ($force && $section !== 'logo') {
                $this->warn('🧹 Opcion --force activada. Invalidando la firma y forzando rebuild completo...');
                $this->cleanDistForForcedBuild($targetFolder);
            }

            $this->publishKatexAssets($targetFolder);
            $this->synchronizePublishedEntryFolders($site, $targetFolder);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('❌ Error al preparar la salida estatica: '.$exception->getMessage());

            return Command::FAILURE;
        }

        if ($section === 'logo') {
            try {
                $this->processMediaAssets($targetFolder);
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

        $perPage = 2000;
        $totalPages = ceil($totalEntries / $perPage) ?: 1;

        $this->info("📊 Registros sucios/pendientes en BD: {$totalEntries} | Bloques: {$perPage} | Páginas a procesar: ".($totalEntries > 0 ? $totalPages : 0));

        $currentPage = 0;
        $processedCount = 0;
        $lastId = 0;

        while ($currentPage < $totalPages && $totalEntries > 0) {
            // Query ultra veloz usando la Base de Datos como Fuente de Verdad Primaria
            $postsChunk = $this->publishedSitePosts($site)
                ->where('id', '>', $lastId)
                ->with('category.parent')
                ->when($section === 'posts', fn ($query) => $this->scopeOnlyPosts($query))
                // ⚡ Incrementalidad inteligente: si no es force, solo trae lo sucio
                ->when(! $rebuildAllEntries, function ($query) {
                    $query->where(function ($q) {
                        $q->whereNull('static_built_at')
                            ->orWhereColumn('updated_at', '>', 'static_built_at');
                    });
                })
                ->orderBy('id', 'asc')
                ->select($this->entryColumns())
                ->take($perPage)
                ->get();

            if ($postsChunk->isEmpty()) {
                break;
            }

            // Compilamos los HTMLs (ya no gasta I/O de disco preguntando fechas de archivos)
            $compiler->compile($postsChunk);

            // Actualización masiva de la marca de tiempo de compilación estática (Corregido)
            $chunkIds = $postsChunk->pluck('id')->toArray();
            Post::whereIn('id', $chunkIds)->update([
                'static_built_at' => now(),
            ]);

            $lastId = end($chunkIds);
            $processedCount += $postsChunk->count();

            if ($resource) {
                $ram = round(memory_get_usage(true) / 1024 / 1024, 2);
                $time = round(microtime(true) - $this->startedAt(), 2);
                $this->comment('   ⏱️ [Lote Incremental] Bloque '.($currentPage + 1)."/{$totalPages} completo | Procesados: {$processedCount} | RAM: {$ram} MB | Tiempo: {$time}s");
            }

            unset($postsChunk, $chunkIds);
            gc_collect_cycles();

            $currentPage++;
        }

        $this->info('✔️ Fin del procesamiento de HTMLs individuales.');

        // ===================================================================
        // 📦 ETAPA 2: ESTRUCTURAS GLOBALES (Se actualizan siempre rápido)
        // ===================================================================
        try {
            $this->regenerateGlobalStructures($site, $targetFolder);
            $this->processMediaAssets($targetFolder);
            $this->synchronizePublishedEntryFolders($site, $targetFolder);
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
        $this->info("🧠 Pico máximo de memoria RAM: {$peakMemory} MB / 512 MB");
        $this->info('-------------------------------------------------------');

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

        $lightColumns = [
            'id',
            'slug',
            'title',
            'body',
            'type',
            'category_id',
            'keywords',
            'created_at',
            'updated_at',
        ];

        if (Schema::hasColumn('posts', 'has_math')) {
            $lightColumns[] = 'has_math';
        }

        $allEntriesLight = $this->publishedSitePosts($site)
            ->with('category.parent')
            ->select($lightColumns)
            ->orderBy('created_at', 'desc')
            ->get();

        $posts = $allEntriesLight->filter(fn ($entry) => $entry->type === Post::TYPE_POST);
        $pages = $allEntriesLight->filter(fn ($entry) => $entry->type === Post::TYPE_PAGE);

        $generator = new StaticSchemaGenerator(
            $this,
            $site,
            $targetFolder,
            $this->resolvedStaticAssets(),
        );
        $generator->build($posts, $pages, $allEntriesLight);
    }

    protected function finalizeSinglePostBuild(Site $site, string $targetFolder): int
    {
        try {
            $this->regenerateGlobalStructures($site, $targetFolder);
            $this->processMediaAssets($targetFolder);
            $this->synchronizePublishedEntryFolders($site, $targetFolder);
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
        $activeSlugs = $this->activePublishedSlugMap($site);
        $deleted = 0;

        foreach (File::directories($targetFolder) as $directory) {
            if (is_link($directory)) {
                $this->warn("⚠️  Se omite enlace simbolico durante la limpieza de entradas [{$directory}].");

                continue;
            }

            $manifest = $this->entryManifest($directory);

            if (! $this->isManagedEntryDirectory($directory, $manifest)) {
                continue;
            }

            if ($manifest !== [] && ! $this->entryManifestBelongsToSite($manifest, $site)) {
                continue;
            }

            $slug = $this->entrySlugFromDirectory($directory, $manifest);

            if ($slug !== '' && isset($activeSlugs[$slug])) {
                continue;
            }

            $this->deleteManagedEntryDirectory($targetFolder, $directory);
            $deleted++;
        }

        if ($deleted > 0) {
            $this->warn("🧹 Limpieza de vida/muerte: {$deleted} directorios HTML huerfanos eliminados en {$targetFolder}");
        }

        return $deleted;
    }

    protected function activePublishedSlugMap(Site $site): array
    {
        return $this->publishedSitePosts($site)
            ->whereNotNull('slug')
            ->pluck('slug')
            ->map(fn (mixed $slug): string => trim((string) $slug, '/\\'))
            ->filter(fn (string $slug): bool => $slug !== '')
            ->unique()
            ->mapWithKeys(fn (string $slug): array => [$slug => true])
            ->all();
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
        foreach (File::directories($targetFolder) as $directory) {
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
        $path = trim((string) $site->subdir, '/');

        return ($path === '' || $path === 'dist') ? '' : '/'.$path;
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
        foreach (File::directories($targetFolder) as $directory) {
            if (is_link($directory)
                || in_array(basename($directory), self::STRUCTURAL_DIRECTORIES, true)
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

    /**
     * Publica los assets estaticos de KaTeX (CSS, JS y fuentes) dentro del
     * dist aislado del sitio, ya que ese directorio es el unico que se despliega
     * (ver README: rsync dist/ -> VPS). No dependemos de un CDN externo ni
     * de un paso de renderizado en el servidor: el navegador hace el render
     * en cliente via auto-render.min.js, apuntando a rutas relativas locales.
     */
    protected function publishKatexAssets(string $targetFolder): void
    {
        $source = base_path('node_modules/katex/dist');

        if (! File::isDirectory($source)) {
            $this->warn("⚠️  No existe node_modules/katex/dist. Corre 'npm install' antes de compilar (formulas KaTeX no se veran).");

            return;
        }

        $destination = $this->joinPath($targetFolder, 'vendor/katex');

        File::ensureDirectoryExists($destination);
        File::ensureDirectoryExists($destination.'/contrib');
        File::ensureDirectoryExists($destination.'/fonts');

        File::copy($source.'/katex.min.css', $destination.'/katex.min.css');
        File::copy($source.'/katex.min.js', $destination.'/katex.min.js');
        File::copy($source.'/contrib/auto-render.min.js', $destination.'/contrib/auto-render.min.js');
        File::copyDirectory($source.'/fonts', $destination.'/fonts');

        $this->comment("   ✔️ Assets de KaTeX publicados en {$destination}");
    }

    protected function processMediaAssets(string $targetFolder): void
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

        if (! File::isDirectory($sourcePath)) {
            $this->warn("   ⚠️  No existe la carpeta de medios origen: {$sourcePath}");

            return;
        }

        $destinationParent = dirname($destinationPath);

        if (! File::exists($destinationParent)) {
            File::makeDirectory($destinationParent, 0755, true);
        }

        if ($typeStorage === 'symlink') {
            File::link($sourcePath, $destinationPath);
            $this->info("   ✔️ Medios enlazados simbolicamente en {$destinationPath}");

            return;
        }

        if ($typeStorage !== 'copy') {
            $this->warn("   ⚠️  type_storage [{$typeStorage}] no reconocido. Se usa copy.");
        }

        if (! File::copyDirectory($sourcePath, $destinationPath)) {
            throw new RuntimeException("No se pudo copiar la carpeta de medios hacia {$destinationPath}");
        }

        $this->info("   ✔️ Medios copiados en {$destinationPath}");

        if ($optimize) {
            $this->optimizeCopiedMediaAssets($destinationPath);
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

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($destinationPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
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
