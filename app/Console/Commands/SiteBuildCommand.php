<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\File;

class SiteBuildCommand extends Command
{
    // Ahora el comando te pide obligatoriamente el código del sitio
    protected $signature = 'site:build {site_code}';
    protected $description = 'Compila un sitio específico del CMS y genera el estático en HTML';

    public function handle()
    {
        $siteCode = $this->argument('site_code');

        // 1. Buscamos el sitio en la base de datos
        $site = Site::where('short_name', $siteCode)->first();

        if (!$site) {
            $this->error("❌ El sitio con el código [{$siteCode}] no existe en la base de datos.");
            return Command::FAILURE;
        }

        $this->info("🚀 [Constructor] Iniciando compilación para el sitio: {$site->long_name}...");

        // 2. Definimos las variables de entorno del sitio
        $baseUrl = rtrim($site->domain, '/');
        $subdir = $site->subdir ? '/' . trim($site->subdir, '/') : '';
        $fullBaseUrl = $baseUrl . $subdir;

        // 3. Definimos la carpeta de salida 'dist' en la raíz
        $outputFolder = base_path('dist');

        // Limpiamos ejecuciones anteriores
        if (File::exists($outputFolder)) {
            File::deleteDirectory($outputFolder);
        }
        File::makeDirectory($outputFolder);

        // Si tiene subdirectorio (ej: /blog), creamos esa ruta física interna
        $targetFolder = $outputFolder . $subdir;
        if (!empty($subdir)) {
            File::makeDirectory($targetFolder, 0755, true, true);
        }

        // 4. Buscamos los posts que pertenezcan a ESTE sitio
        // (Podés usar el ID o el short_name según cómo los estés vinculando en Filament)
        $posts = Post::where('site_id', $site->id)
        ->orWhere('site_id', $site->short_name)
        ->orderBy('created_at', 'desc')
        ->get();

        if ($posts->isEmpty()) {
            $this->warn('⚠️ No se encontraron posts asignados a este sitio para compilar.');
        }

        // ==========================================
        // 5. GENERAR LA PORTADA PAGINADA (index.html)
        // ==========================================
        $perPage = 10;
        $chunks = $posts->chunk($perPage);
        $totalPages = $chunks->count();

        foreach ($chunks as $index => $chunkPosts) {
            $currentPage = $index + 1;
            $this->info("📝 Renderizando portada - Página {$currentPage} de {$totalPages}...");

            $indexHtml = view('site.index', [
                'posts' => $chunkPosts,
                'site' => $site,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'subdirUrl' => $site->subdir ? '/' . trim($site->subdir, '/') : ''
            ])->render();

            if ($currentPage === 1) {
                File::put($targetFolder . '/index.html', $indexHtml);
            } else {
                $pageFolder = $targetFolder . "/page/{$currentPage}";
                File::makeDirectory($pageFolder, 0755, true, true);
                File::put($pageFolder . '/index.html', $indexHtml);
            }
        }

        // ==========================================
        // 6. GENERAR CADA ENSAYO INDIVIDUAL (¡EL QUE FALTABA!)
        // ==========================================
        foreach ($posts as $post) {
            if (empty($post->slug)) {
                continue;
            }

            $this->info("📄 Compilando ensayo: {$subdir}/{$post->slug}/");

            $postFolder = $targetFolder . '/' . $post->slug;
            File::makeDirectory($postFolder, 0755, true, true);

            $postHtml = view('site.post', compact('post', 'site'))->render();
            File::put($postFolder . '/index.html', $postHtml);
        }

        // ==========================================
        // GENERAR SITEMAP.XML (Streaming a Disco)
        // ==========================================
        $this->info('🗺️ Generando sitemap.xml dinámico...');

        $sitemapFile = fopen($targetFolder . '/sitemap.xml', 'w');

        fwrite($sitemapFile, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
        fwrite($sitemapFile, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);
        fwrite($sitemapFile, "    <url><loc>{$fullBaseUrl}/</loc><priority>1.0</priority></url>" . PHP_EOL);

        foreach ($posts as $post) {
            $item = "    <url>" . PHP_EOL .
            "        <loc>{$fullBaseUrl}/{$post->slug}/</loc>" . PHP_EOL .
            "        <lastmod>" . $post->updated_at->toAtomString() . "</lastmod>" . PHP_EOL .
            "        <priority>0.8</priority>" . PHP_EOL .
            "    </url>" . PHP_EOL;
            fwrite($sitemapFile, $item);
        }

        fwrite($sitemapFile, '</urlset>');
        fclose($sitemapFile);

        // ==========================================
        // GENERAR FEED.XML (RSS Atom - Streaming a Disco)
        // ==========================================
        $this->info('📡 Generando feed.xml (RSS) dinámico...');

        $feedFile = fopen($targetFolder . '/feed.xml', 'w');

        fwrite($feedFile, '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL);
        fwrite($feedFile, '<feed xmlns="http://www.w3.org/2005/Atom">' . PHP_EOL);
        fwrite($feedFile, "    <title><![CDATA[{$site->long_name}]]></title>" . PHP_EOL);
        fwrite($feedFile, "    <subtitle><![CDATA[{$site->slogan}]]></subtitle>" . PHP_EOL);
        fwrite($feedFile, "    <link href=\"{$fullBaseUrl}/feed.xml\" rel=\"self\"/>" . PHP_EOL);
        fwrite($feedFile, "    <link href=\"{$fullBaseUrl}/\"/>" . PHP_EOL);
        fwrite($feedFile, "    <updated>" . (now()->toAtomString()) . "</updated>" . PHP_EOL);
        fwrite($feedFile, "    <id>{$fullBaseUrl}/</id>" . PHP_EOL);

        foreach ($posts as $post) {
            $entry = "    <entry>" . PHP_EOL .
            "        <title><![CDATA[{$post->title}]]></title>" . PHP_EOL .
            "        <link href=\"{$fullBaseUrl}/{$post->slug}/\"/>" . PHP_EOL .
            "        <id>{$fullBaseUrl}/{$post->slug}/</id>" . PHP_EOL .
            "        <updated>" . $post->updated_at->toAtomString() . "</updated>" . PHP_EOL .
            "        <summary><![CDATA[{$post->keywords}]]></summary>" . PHP_EOL .
            "    </entry>" . PHP_EOL;
            fwrite($feedFile, $entry);
        }

        fwrite($feedFile, '</feed>');
        fclose($feedFile);

        $this->info("✨ [Éxito] ¡Sitio [{$siteCode}] generado por completo en /dist!");
        $this->info('Memoria pico: ' . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB');

        return Command::SUCCESS;
    }
}
