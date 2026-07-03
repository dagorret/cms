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

        // B) Segmentación Cronológica pura: /archive/{YYYY}/{MM}/index.json
        $groupedByMonth = $allEntriesLight->groupBy(fn($e) => $e->created_at->format('Y/m'));

        foreach ($groupedByMonth as $yearMonth => $entries) {
            $archivePath = $this->targetFolder . "/archive/{$yearMonth}";
            if (!File::exists($archivePath)) {
                File::makeDirectory($archivePath, 0755, true);
            }

            $archiveData = $entries->map(fn($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'slug' => $e->slug,
                'type' => $e->type ?? 'post',
                'date' => $e->created_at->format('Y-m-d')
            ])->values()->toJson();

            File::put($archivePath . '/index.json', $archiveData);
        }

        // C) Índices dinámicos de Categorías paginados en JSON puro: /category/{slug}/page-{n}.json
        $postsPerPage = config('static_cms.posts_per_home_page', 10);
        $groupedByCategory = $allEntriesLight->groupBy(fn($e) => $e->category ?: $e->type);

        foreach ($groupedByCategory as $categoryName => $catEntries) {
            if (empty($categoryName)) continue;

            $catSlug = str($categoryName)->slug()->toString();
            $catFolder = $this->targetFolder . "/category/{$catSlug}";

            if (!File::exists($catFolder)) {
                File::makeDirectory($catFolder, 0755, true);
            }

            $catChunks = $catEntries->chunk($postsPerPage);
            foreach ($catChunks as $index => $chunk) {
                $pageNum = $index + 1;
                $chunkData = $chunk->map(fn($e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'slug' => $e->slug,
                    'date' => $e->created_at->format('Y-m-d')
                ])->values()->toJson();

                File::put($catFolder . "/page-{$pageNum}.json", $chunkData);
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
        $menuHtml = view('site.menu', ['pages' => $pages, 'site' => $this->site])->render();
        File::put($this->targetFolder . '/menu.html', $menuHtml);

        $menuJson = $pages->map(fn($p) => ['title' => $p->title, 'slug' => $p->slug])->values()->toJson();
        File::put($this->targetFolder . '/menu.json', $menuJson);

        // ===================================================================
        // 3. 📄 PORTADA PRINCIPAL (HTML) Y PAGINACIÓN SUBSIGUIENTE (JSON PURO)
        // ===================================================================
        $this->command->comment('   📄 Generando Portada index.html y listados JSON para la SPA...');

        // El universo completo de posts ordenados para la portada
        $allPosts = $allEntriesLight->filter(fn($e) => $e->type !== 'page');
        $chunks = $allPosts->chunk($postsPerPage);

        $maxFrontPages = config('static_cms.max_home_pages', 20);
        $pagesToRender = $chunks->take($maxFrontPages);

        // Si existía la estructura vieja de carpetas HTML para las páginas, la limpiamos
        if (File::exists($this->targetFolder . '/page')) {
            File::deleteDirectory($this->targetFolder . '/page');
        }

        foreach ($pagesToRender as $index => $chunkPosts) {
            $currentPage = $index + 1;

            if ($currentPage === 1) {
                // La página 1 de la Home siempre es un HTML real masticado para el landing inicial
                $indexHtml = view('site.index', [
                    'posts' => $chunkPosts,
                    'site' => $this->site,
                    'currentPage' => 1,
                    'totalPages' => min($chunks->count(), $maxFrontPages),
                                  'subdirUrl' => $subdir
                ])->render();

                File::put($this->targetFolder . '/index.html', $indexHtml);
            } else {
                // 🚀 De la página 2 en adelante: JSON puros en la raíz de dist para consumo SPA instantáneo
                $pageJsonData = $chunkPosts->map(fn($e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'slug' => $e->slug,
                    'date' => $e->created_at->format('Y-m-d')
                ])->values()->toJson();

                File::put($this->targetFolder . "/page-{$currentPage}.json", $pageJsonData);
            }
        }

        // ===================================================================
        // 4. 📡 GENERAR FEED RSS (SEGURO SOBRE COLECCIÓN LIGERA)
        // ===================================================================
        $this->command->comment('   📡 Generando feed.xml...');

        $maxFeedItems = config('static_cms.max_feed_items', 50);
        // Filtramos directamente desde el lote cargado para blindar que nunca dé vacío por desajustes externos
        $feedPosts = $allEntriesLight->filter(fn($e) => $e->type !== 'page')->take($maxFeedItems);

        $feedPath = $this->targetFolder . '/feed.xml';
        $feedFile = fopen($feedPath, 'w');

        fwrite($feedFile, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<rss version="2.0">' . PHP_EOL . '  <channel>' . PHP_EOL);
        fwrite($feedFile, "    <title><![CDATA[{$this->site->long_name}]]></title>" . PHP_EOL);
        fwrite($feedFile, "    <link>{$fullBaseUrl}/</link>" . PHP_EOL);

        foreach ($feedPosts as $post) {
            $url = "{$fullBaseUrl}/{$post->slug}/";
            $title = htmlspecialchars($post->title, ENT_XML1, 'UTF-8');

            fwrite($feedFile, '    <item>' . PHP_EOL);
            fwrite($feedFile, "      <title>{$title}</title>" . PHP_EOL);
            fwrite($feedFile, "      <link>{$url}</link>" . PHP_EOL);
            fwrite($feedFile, "      <pubDate>" . $post->created_at->toRssString() . "</pubDate>" . PHP_EOL);
            fwrite($feedFile, '    </item>' . PHP_EOL);
        }

        fwrite($feedFile, '  </channel>' . PHP_EOL . '</rss>' . PHP_EOL);
        fclose($feedFile);

        // ===================================================================
        // 5. 🗺️ SITEMAP MÚLTIPLE (SITEMAP INDEX + CHUNKS SECUENCIALES)
        // ===================================================================
        $this->command->comment('   🗺️ Generando Sitemap Index y fragmentos masivos...');

        $sitemapPerPage = config('static_cms.sitemap_per_page', 5000);
        $sitemapChunks = $allEntriesLight->chunk($sitemapPerPage);
        $sitemapFilesCreated = [];

        foreach ($sitemapChunks as $chunkIndex => $chunkEntries) {
            $partNumber = $chunkIndex + 1;
            $fileName = "sitemap-{$partNumber}.xml";
            $partPath = $this->targetFolder . '/' . $fileName;

            $partFile = fopen($partPath, 'w');
            fwrite($partFile, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);

            if ($partNumber === 1) {
                fwrite($partFile, "    <url><loc>{$fullBaseUrl}/</loc><priority>1.0</priority></url>" . PHP_EOL);
            }

            foreach ($chunkEntries as $entry) {
                if (empty($entry->slug)) continue;
                $url = "{$fullBaseUrl}/{$entry->slug}/";
                $lastMod = $entry->updated_at->toIso8601String();
                fwrite($partFile, "    <url><loc>{$url}</loc><lastmod>{$lastMod}</lastmod><priority>0.8</priority></url>" . PHP_EOL);
            }

            fwrite($partFile, '</urlset>' . PHP_EOL);
            fclose($partFile);

            $sitemapFilesCreated[] = $fileName;
        }

        $indexPath = $this->targetFolder . '/sitemap.xml';
        $indexFile = fopen($indexPath, 'w');

        fwrite($indexFile, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);

        foreach ($sitemapFilesCreated as $sitemapFile) {
            $sitemapUrl = "{$fullBaseUrl}/{$sitemapFile}";
            $currentDate = now()->toIso8601String();

            fwrite($indexFile, '  <sitemap>' . PHP_EOL);
            fwrite($indexFile, "    <loc>{$sitemapUrl}</loc>" . PHP_EOL);
            fwrite($indexFile, "    <lastmod>{$currentDate}</lastmod>" . PHP_EOL);
            fwrite($indexFile, '  </sitemap>' . PHP_EOL);
        }

        fwrite($indexFile, '</sitemapindex>' . PHP_EOL);
        fclose($indexFile);

        $this->command->info('   ✔️ Arquitectura unificada: SPA JSON Ready y Sitemaps fragmentados listos.');
    }
}
