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
    protected $signature = 'site:build
        {site_code=all : Codigo del sitio o target operativo: all, posts, logo}
        {--post= : ID de post para regenerar solo ese articulo}
        {--F|force}
        {--R|resource}';
    protected $description = 'Orquestador modular tipo NASA con incrementalidad real por Base de Datos y Cursor';

    public function handle()
    {
        $target = trim((string) $this->argument('site_code'));
        $force = $this->option('force');
        $resource = $this->option('resource');
        $postId = filled($this->option('post')) ? (int) $this->option('post') : null;

        try {
            [$site, $section] = $this->resolveBuildTarget($target);
        } catch (RuntimeException $exception) {
            $this->error('❌ ' . $exception->getMessage());
            return Command::FAILURE;
        }

        if (trim((string) $site->domain) === '') {
            $this->error("❌ El sitio [{$site->short_name}] no tiene dominio publico configurado en sites.domain.");
            return Command::FAILURE;
        }

        $this->info("🚀 [Orquestador NASA] Iniciando para: {$site->long_name} | Target: {$target} | Seccion: {$section}");

        $targetFolder = base_path('dist');
        if (!File::exists($targetFolder)) {
            File::makeDirectory($targetFolder, 0755, true);
        }

        if ($postId !== null) {
            $this->publishKatexAssets($targetFolder);

            return $this->compileSinglePost($site, $targetFolder, $postId, $resource);
        }

        if ($force) {
            $this->warn('🧹 Opcion --force activada. Limpiando cache anterior y forzando rebuild completo...');
            File::cleanDirectory($targetFolder);
        }

        $this->publishKatexAssets($targetFolder);

        // ===================================================================
        // 🚀 ETAPA 1: BUCLE WHILE CON FUENTE DE VERDAD EN BD + CURSOR
        // ===================================================================
        $compiler = new StaticContentCompiler($this, $site, $targetFolder, $force, $resource);

        // Conteo base condicional para saber el trabajo pendiente real
        $totalEntries = $this->siteScopedPosts($site)
            ->when($section === 'posts', fn($query) => $this->scopeOnlyPosts($query))
            ->when(!$force, function($query) {
                $query->where(function($q) {
                    $q->whereNull('static_built_at')
                      ->orWhereColumn('updated_at', '>', 'static_built_at');
                });
            })
            ->count();

        $perPage = 2000; 
        $totalPages = ceil($totalEntries / $perPage) ?: 1;
        
        $this->info("📊 Registros sucios/pendientes en BD: {$totalEntries} | Bloques: {$perPage} | Páginas a procesar: " . ($totalEntries > 0 ? $totalPages : 0));

        $currentPage = 0;
        $processedCount = 0;
        $lastId = 0;

        while ($currentPage < $totalPages && $totalEntries > 0) {
            
            // Query ultra veloz usando la Base de Datos como Fuente de Verdad Primaria
            $postsChunk = $this->siteScopedPosts($site)
                ->where('id', '>', $lastId)
                ->when($section === 'posts', fn($query) => $this->scopeOnlyPosts($query))
                // ⚡ Incrementalidad inteligente: si no es force, solo trae lo sucio
                ->when(!$force, function($query) {
                    $query->where(function($q) {
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
                'static_built_at' => now()
            ]);

            $lastId = end($chunkIds);
            $processedCount += $postsChunk->count();
            
            if ($resource) {
                $ram = round(memory_get_usage(true) / 1024 / 1024, 2);
                $time = round(microtime(true) - LARAVEL_START, 2);
                $this->comment("   ⏱️ [Lote Incremental] Bloque " . ($currentPage + 1) . "/{$totalPages} completo | Procesados: {$processedCount} | RAM: {$ram} MB | Tiempo: {$time}s");
            }

            unset($postsChunk, $chunkIds);
            gc_collect_cycles();

            $currentPage++;
        }

        $this->info("✔️ Fin del procesamiento de HTMLs individuales.");

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

        $allEntriesLight = $this->siteScopedPosts($site)
            ->select($lightColumns)
            ->orderBy('created_at', 'desc')
            ->get();

        $posts = $allEntriesLight->filter(fn($entry) => ($entry->type ?? 'post') === 'post');
        $pages = $allEntriesLight->filter(fn($entry) => ($entry->type ?? 'post') === 'page');

        $generator = new StaticSchemaGenerator($this, $site, $targetFolder);
        $generator->build($posts, $pages, $allEntriesLight);

        try {
            $this->processMediaAssets();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('❌ Error al procesar medios: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        // Reporte Final de Cierre
        $executionTime = round(microtime(true) - LARAVEL_START, 2);
        $peakMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->newLine();
        $this->info("📊 --- REPORTE DE RENDIMIENTO ULTRA (NASA MODE) ---");
        $this->info("⏱️  Tiempo total de ejecución: {$executionTime} segundos");
        $this->info("🧠 Pico máximo de memoria RAM: {$peakMemory} MB / 512 MB");
        $this->info("-------------------------------------------------------");

        return Command::SUCCESS;
    }

    protected function compileSinglePost(Site $site, string $targetFolder, int $postId, bool $resource): int
    {
        $post = $this->siteScopedPosts($site)
            ->whereKey($postId)
            ->select($this->entryColumns())
            ->first();

        if (! $post) {
            $this->error("❌ No existe el post [{$postId}] para el sitio [{$site->short_name}].");

            return Command::FAILURE;
        }

        $compiler = new StaticContentCompiler($this, $site, $targetFolder, true, $resource);
        $compiler->compile(collect([$post]));

        Post::whereKey($post->getKey())->update([
            'static_built_at' => now(),
        ]);

        $this->info("✔️ Articulo [{$post->id}] {$post->slug} compilado en dist/{$post->slug}/index.html");

        return Command::SUCCESS;
    }

    protected function resolveBuildTarget(string $target): array
    {
        $target = $target !== '' ? $target : 'all';

        $site = Site::where('short_name', $target)->first();

        if ($site) {
            return [$site, 'all'];
        }

        $section = in_array($target, ['all', 'posts', 'logo'], true) ? $target : 'all';
        $site = Site::query()->orderBy('id')->first();

        if (! $site) {
            throw new RuntimeException('No hay sitios configurados en la tabla sites.');
        }

        return [$site, $section];
    }

    protected function siteScopedPosts(Site $site)
    {
        return Post::query()
            ->where(function ($query) use ($site): void {
                $query->where('site_id', $site->id)
                    ->orWhere('site_id', $site->short_name);
            });
    }

    protected function scopeOnlyPosts($query)
    {
        return $query->where(function ($nested): void {
            $nested->whereNull('type')
                ->orWhere('type', '!=', 'page');
        });
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
     * propio dist/, ya que dist/ es el unico directorio que se despliega
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

        $destination = $targetFolder . '/vendor/katex';

        File::ensureDirectoryExists($destination);
        File::ensureDirectoryExists($destination . '/contrib');
        File::ensureDirectoryExists($destination . '/fonts');

        File::copy($source . '/katex.min.css', $destination . '/katex.min.css');
        File::copy($source . '/katex.min.js', $destination . '/katex.min.js');
        File::copy($source . '/contrib/auto-render.min.js', $destination . '/contrib/auto-render.min.js');
        File::copyDirectory($source . '/fonts', $destination . '/fonts');

        $this->comment('   ✔️ Assets de KaTeX publicados en dist/vendor/katex');
    }

    protected function processMediaAssets(): void
    {
        $mediaBasePath = trim((string) config('static_cms.media.base_path'), '/');

        if ($mediaBasePath === '') {
            $this->warn('⚠️  static_cms.media.base_path esta vacio. Se omite la publicacion de medios.');

            return;
        }

        $sourcePath = storage_path('app/public/' . $mediaBasePath);
        $destinationPath = base_path('dist/' . $mediaBasePath);
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
            $this->info("   ✔️ Medios enlazados simbolicamente en dist/{$mediaBasePath}");

            return;
        }

        if ($typeStorage !== 'copy') {
            $this->warn("   ⚠️  type_storage [{$typeStorage}] no reconocido. Se usa copy.");
        }

        if (! File::copyDirectory($sourcePath, $destinationPath)) {
            throw new RuntimeException("No se pudo copiar la carpeta de medios hacia {$destinationPath}");
        }

        $this->info("   ✔️ Medios copiados en dist/{$mediaBasePath}");

        if ($optimize) {
            $files = File::allFiles($destinationPath);
            $this->comment('   🧩 Hook de optimizacion activo: ' . count($files) . ' archivos listados para compresion.');
        }
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
