<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\Site;
use App\Services\StaticContentCompiler;
use App\Services\StaticSchemaGenerator;
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

        if ($force && $section !== 'logo') {
            $this->warn('🧹 Opcion --force activada. Limpiando cache anterior y forzando rebuild completo...');
            File::cleanDirectory($targetFolder);
        }

        $this->publishKatexAssets($targetFolder);
        $this->synchronizePublishedEntryFolders($site, $targetFolder);

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
        $compiler = new StaticContentCompiler($this, $site, $targetFolder, $force, $resource);

        // Conteo base condicional para saber el trabajo pendiente real
        $totalEntries = $this->publishedSitePosts($site)
            ->when($section === 'posts', fn ($query) => $this->scopeOnlyPosts($query))
            ->when(! $force, function ($query) {
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
                ->when($section === 'posts', fn ($query) => $this->scopeOnlyPosts($query))
                // ⚡ Incrementalidad inteligente: si no es force, solo trae lo sucio
                ->when(! $force, function ($query) {
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
        $this->info('📦 Regenerando índices globales dinámicos (JSON, Portadas, Sitemap)...');

        $lightColumns = [
            'id',
            'slug',
            'title',
            'body',
            'type',
            'keywords',
            'created_at',
            'updated_at',
        ];

        if (Schema::hasColumn('posts', 'category')) {
            $lightColumns[] = 'category';
        }

        if (Schema::hasColumn('posts', 'has_math')) {
            $lightColumns[] = 'has_math';
        }

        $allEntriesLight = $this->publishedSitePosts($site)
            ->select($lightColumns)
            ->orderBy('created_at', 'desc')
            ->get();

        $posts = $allEntriesLight->filter(fn ($entry) => ($entry->type ?? 'post') === 'post');
        $pages = $allEntriesLight->filter(fn ($entry) => ($entry->type ?? 'post') === 'page');

        $generator = new StaticSchemaGenerator($this, $site, $targetFolder);
        $generator->build($posts, $pages, $allEntriesLight);

        try {
            $this->processMediaAssets($targetFolder);
            $this->synchronizePublishedEntryFolders($site, $targetFolder);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('❌ Error al procesar medios: '.$exception->getMessage());

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
            ->select($this->entryColumns())
            ->first();

        if (! $post) {
            $inactivePost = $this->siteScopedPosts($site)
                ->whereKey($postId)
                ->select(['id', 'slug', 'status'])
                ->first();

            if ($inactivePost) {
                $this->deleteEntryFolder($targetFolder, (string) $inactivePost->slug);
                $this->synchronizePublishedEntryFolders($site, $targetFolder);
                $this->warn("⚠️  Articulo [{$postId}] omitido: estado actual [{$inactivePost->status}], solo se compila status=published.");

                return Command::SUCCESS;
            }

            $this->error("❌ No existe el post [{$postId}] para el sitio [{$site->short_name}].");

            return Command::FAILURE;
        }

        $compiler = new StaticContentCompiler($this, $site, $targetFolder, true, $resource);
        $compiler->compile(collect([$post]));

        Post::whereKey($post->getKey())->update([
            'static_built_at' => now(),
        ]);

        $this->info("✔️ Articulo [{$post->id}] {$post->slug} compilado en {$this->joinPath($targetFolder, "{$post->slug}/index.html")}");
        $this->synchronizePublishedEntryFolders($site, $targetFolder);

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

        if (! $this->isAbsolutePath($distPath)) {
            throw new RuntimeException("El dist_path del sitio [{$site->short_name}] debe ser una ruta absoluta. Valor recibido: {$distPath}");
        }

        if ($this->isFilesystemRoot($distPath)) {
            throw new RuntimeException('dist_path no puede apuntar a la raiz del filesystem.');
        }

        if ($this->isSamePath($distPath, public_path())) {
            throw new RuntimeException('dist_path no puede apuntar directamente a public_path(). Usa una ruta aislada por sitio.');
        }

        return $distPath;
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
        return $query->where(function ($nested): void {
            $nested->whereNull('type')
                ->orWhere('type', '!=', 'page');
        });
    }

    protected function synchronizePublishedEntryFolders(Site $site, string $targetFolder): int
    {
        $activeSlugs = $this->activePublishedSlugMap($site);
        $deleted = 0;

        foreach (File::directories($targetFolder) as $directory) {
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

            $this->cleanMediaDestination($directory);
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
        if ($manifest !== []) {
            return true;
        }

        $directoryName = basename($directory);

        if (in_array($directoryName, self::STRUCTURAL_DIRECTORIES, true)) {
            return false;
        }

        return File::isFile($this->joinPath($directory, 'index.html'));
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

        $this->cleanMediaDestination($entryFolder);

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
            'status',
            'published_at',
            'created_at',
            'updated_at',
            'static_built_at',
        ];

        if (Schema::hasColumn('posts', 'category')) {
            $columns[] = 'category';
        }

        if (Schema::hasColumn('posts', 'has_math')) {
            $columns[] = 'has_math';
        }

        return $columns;
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

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1
            || str_starts_with($path, '\\\\');
    }

    protected function isFilesystemRoot(string $path): bool
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');

        return $normalized === '' || preg_match('/^[A-Za-z]:$/', $normalized) === 1;
    }

    protected function isSamePath(string $firstPath, string $secondPath): bool
    {
        return rtrim($this->normalizePath($firstPath), '/\\') === rtrim($this->normalizePath($secondPath), '/\\');
    }

    protected function normalizePath(string $path): string
    {
        $realPath = realpath($path);

        return $realPath !== false ? $realPath : $path;
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
