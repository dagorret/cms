<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Site;
use App\Models\Post;
use App\Services\StaticContentCompiler;
use App\Services\StaticSchemaGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SiteBuildCommand extends Command
{
    protected $signature = 'site:build {site_code} {--F|force} {--R|resource}';
    protected $description = 'Orquestador modular tipo NASA con incrementalidad real por Base de Datos y Cursor';

    public function handle()
    {
        $siteCode = $this->argument('site_code');
        $force = $this->option('force');
        $resource = $this->option('resource');

        $site = Site::where('short_name', $siteCode)->first();

        if (!$site) {
            $this->error("❌ El sitio [{$siteCode}] no existe.");
            return Command::FAILURE;
        }

        $this->info("🚀 [Orquestador NASA] Iniciando para: {$site->long_name}...");

        $targetFolder = base_path('dist' . ($site->subdir ? '/' . trim($site->subdir, '/') : ''));
        if (!File::exists($targetFolder)) {
            File::makeDirectory($targetFolder, 0755, true);
        }

        if ($force) {
            $this->warn('🧹 Opcion --force activada. Limpiando cache anterior y forzando rebuild completo...');
            File::cleanDirectory($targetFolder);
        }

        // ===================================================================
        // 🚀 ETAPA 1: BUCLE WHILE CON FUENTE DE VERDAD EN BD + CURSOR
        // ===================================================================
        $compiler = new StaticContentCompiler($this, $site, $targetFolder, $force, $resource);

        // Conteo base condicional para saber el trabajo pendiente real
        $totalEntries = Post::where(function($query) use ($site) {
                $query->where('site_id', $site->id)
                      ->orWhere('site_id', $site->short_name);
            })
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
            $postsChunk = Post::where(function($query) use ($site) {
                    $query->where('site_id', $site->id)
                          ->orWhere('site_id', $site->short_name);
                })
                ->where('id', '>', $lastId)
                // ⚡ Incrementalidad inteligente: si no es force, solo trae lo sucio
                ->when(!$force, function($query) {
                    $query->where(function($q) {
                        $q->whereNull('static_built_at')
                          ->orWhereColumn('updated_at', '>', 'static_built_at');
                    });
                })
                ->orderBy('id', 'asc')
                ->select([
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
                ])
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

        $allEntriesLight = Post::where(function($query) use ($site) {
                $query->where('site_id', $site->id)
                      ->orWhere('site_id', $site->short_name);
            })
            ->select([
                'id',
                'slug',
                'title',
                'type',
                'category',
                'keywords',
                'has_math',
                'created_at',
                'updated_at',
                DB::raw('substr(body, 1, 700) as excerpt'),
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $posts = $allEntriesLight->filter(fn($entry) => ($entry->type ?? 'post') === 'post');
        $pages = $allEntriesLight->filter(fn($entry) => ($entry->type ?? 'post') === 'page');

        $generator = new StaticSchemaGenerator($this, $site, $targetFolder);
        $generator->build($posts, $pages, $allEntriesLight);

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
}
