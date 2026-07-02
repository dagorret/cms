<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Post;
use Illuminate\Support\Facades\File;

class SiteBuildCommand extends Command
{
    // Este es el nombre del comando que vas a ejecutar en la consola
    protected $signature = 'site:build';
    
    protected $description = 'Compila el CMS y escupe el sitio estático en HTML plano';

    public function handle()
    {
        $this->info('🚀 [Constructor] Iniciando compilación del sitio estático...');

        // 1. Definimos la carpeta de salida 'dist' en la raíz del proyecto
        $outputFolder = base_path('dist');
        
        // Limpiamos la carpeta por si había basura de ejecuciones anteriores
        if (File::exists($outputFolder)) {
            File::deleteDirectory($outputFolder);
        }
        File::makeDirectory($outputFolder);

        // 2. Buscamos los posts en la base de datos (por ahora traemos todos para probar)
        $posts = Post::orderBy('created_at', 'desc')->get();

        if ($posts->isEmpty()) {
            $this->warn('⚠️ No se encontraron posts en la base de datos para compilar.');
        }

        // 3. GENERAR LA HOME (index.html)
        $this->info('📝 Renderizando portada (index.html)...');
        
        // Pasamos los posts a tu vista Blade recién creada y la convertimos en un string HTML
        $indexHtml = view('site.index', compact('posts'))->render();
        
        // Guardamos el archivo físico en /dist/index.html
        File::put($outputFolder . '/index.html', $indexHtml);


        // 4. GENERAR CADA ENSAYO INDIVIDUAL
        foreach ($posts as $post) {
            if (empty($post->slug)) {
                $this->error("❌ El post ID {$post->id} no tiene un slug válido. Saltando...");
                continue;
            }

            $this->info("📄 Compilando ensayo: /{$post->slug}/");

            // Creamos una carpeta para el post (Estructura de URLs limpias)
            $postFolder = $outputFolder . '/' . $post->slug;
            File::makeDirectory($postFolder, 0755, true, true);

            // Renderizamos la vista de detalle
            $postHtml = view('site.post', compact('post'))->render();

            // Guardamos como index.html adentro de su propia carpeta
            File::put($postFolder . '/index.html', $postHtml);
        }

        $this->info('✨ [Éxito] ¡Sitio estático generado por completo en la carpeta /dist!');
        $this->info('Memoria pico: ' . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB');
        return Command::SUCCESS;
    }
}
