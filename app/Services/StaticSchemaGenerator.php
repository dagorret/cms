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

    public function build($posts, $pages, $allEntries)
    {
        $this->command->info('📦 Actualizando esquemas e índices estructurales JSON...');
        
        $baseUrl = rtrim($this->site->domain, '/');
        $subdir = $this->site->subdir ? '/' . trim($this->site->subdir, '/') : '';
        $fullBaseUrl = $baseUrl . $subdir;
        
        $categoriesJson = [];
        $archiveJson = [];

        // 1. Procesar metadatos para Categorías y Archivo Cronológico
        foreach ($posts as $post) {
            $postData = [
                'title' => $post->title,
                'slug' => $post->slug,
                'url' => "{$fullBaseUrl}/{$post->slug}/",
                'date' => is_string($post->created_at) ? $post->created_at : $post->created_at->toIso8601String(),
                'keywords' => $post->keywords,
                'has_math' => (bool) ($post->has_math ?? false),
            ];

            // Agrupación por Categorías
            if (!empty($post->category)) {
                $catName = is_object($post->category) ? $post->category->name : $post->category;
                $catSlug = is_object($post->category) ? $post->category->slug : str($catName)->slug()->toString();
                
                if (!isset($categoriesJson[$catSlug])) {
                    $categoriesJson[$catSlug] = [
                        'name' => $catName,
                        'slug' => $catSlug,
                        'posts' => []
                    ];
                }
                $categoriesJson[$catSlug]['posts'][] = $postData;
            }

            // Agrupación por Archivo Cronológico
            $createdAt = is_string($post->created_at) ? \Illuminate\Support\Carbon::parse($post->created_at) : $post->created_at;
            $year = $createdAt->format('Y');
            $month = $createdAt->format('m');
            $monthName = $createdAt->translatedFormat('F');

            if (!isset($archiveJson[$year])) {
                $archiveJson[$year] = ['year' => $year, 'months' => []];
            }
            if (!isset($archiveJson[$year]['months'][$month])) {
                $archiveJson[$year]['months'][$month] = [
                    'month_code' => $month,
                    'month_name' => ucfirst($monthName),
                    'posts' => []
                ];
            }
            $archiveJson[$year]['months'][$month]['posts'][] = $postData;
        }

        // 2. Generar el formato del menú global
        $menuJson = [
            'site_name' => $this->site->long_name,
            'pages' => $pages->map(fn($p) => ['title' => $p->title, 'slug' => $p->slug, 'url' => "{$fullBaseUrl}/{$p->slug}/"])->values(),
            'categories' => array_map(fn($c) => ['name' => $c['name'], 'slug' => $c['slug']], $categoriesJson)
        ];

        // Guardar archivos JSON estructurales en disco
        File::put($this->targetFolder . '/categories.json', json_encode(array_values($categoriesJson), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        File::put($this->targetFolder . '/archive.json', json_encode($archiveJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        File::put($this->targetFolder . '/menu.json', json_encode($menuJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 3. Generar el menú HTML desde la vista Blade
        $this->command->info('🍴 Generando menú estático HTML...');
        $menuHtml = view('site.menu', ['site' => $this->site, 'posts' => $posts])->render();
        File::put($this->targetFolder . '/menu.html', $menuHtml);

        // 4. Generar la Portada Paginada (Bloques de 10)
        $this->command->info('📄 Generando paginación de la portada...');
        $perPage = 10;
        $chunks = $posts->chunk($perPage);
        $totalPages = $chunks->count() ?: 1;

        foreach ($chunks as $index => $chunkPosts) {
            $currentPage = $index + 1;
            
            $indexHtml = view('site.index', [
                'posts' => $chunkPosts,
                'site' => $this->site,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'subdirUrl' => $subdir
            ])->render();

            if ($currentPage === 1) {
                File::put($this->targetFolder . '/index.html', $indexHtml);
            } else {
                $pageFolder = $this->targetFolder . "/page/{$currentPage}";
                if (!File::exists($pageFolder)) {
                    File::makeDirectory($pageFolder, 0755, true);
                }
                File::put($pageFolder . '/index.html', $indexHtml);
            }
        }

        // 5. Generar Sitemap XML
        $this->command->info('🗺️  Generando sitemap.xml...');
        $sitemapFile = fopen($this->targetFolder . '/sitemap.xml', 'w');
        fwrite($sitemapFile, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL . "    <url><loc>{$fullBaseUrl}/</loc><priority>1.0</priority></url>" . PHP_EOL);
        foreach ($allEntries as $entry) {
            $updatedAt = is_string($entry->updated_at) ? \Illuminate\Support\Carbon::parse($entry->updated_at) : $entry->updated_at;
            fwrite($sitemapFile, "    <url><loc>{$fullBaseUrl}/{$entry->slug}/</loc><lastmod>{$updatedAt->toAtomString()}</lastmod><priority>0.8</priority></url>" . PHP_EOL);
        }
        fwrite($sitemapFile, '</urlset>');
        fclose($sitemapFile);

        // 6. Generar Feed Atom
        $this->command->info('📡 Generando feed.xml...');
        $feedFile = fopen($this->targetFolder . '/feed.xml', 'w');
        fwrite($feedFile, '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL . '<feed xmlns="http://www.w3.org/2005/Atom">' . PHP_EOL . "    <title><![CDATA[{$this->site->long_name}]]></title><link href=\"{$fullBaseUrl}/feed.xml\" rel=\"self\"/><link href=\"{$fullBaseUrl}/\"/><updated>" . now()->toAtomString() . "</updated><id>{$fullBaseUrl}/</id>" . PHP_EOL);
        foreach ($posts as $post) {
            $updatedAt = is_string($post->updated_at) ? \Illuminate\Support\Carbon::parse($post->updated_at) : $post->updated_at;
            fwrite($feedFile, "    <entry><title><![CDATA[{$post->title}]]></title><link href=\"{$fullBaseUrl}/{$post->slug}/\"/><id>{$fullBaseUrl}/{$post->slug}/</id><updated>{$updatedAt->toAtomString()}</updated></entry>" . PHP_EOL);
        }
        fwrite($feedFile, '</feed>');
        fclose($feedFile);

        // 7. Copiar Assets Públicos si existen
        $publicStorage = storage_path('app/public');
        if (File::exists($publicStorage)) {
            File::copyDirectory($publicStorage, $this->targetFolder . '/storage');
        }

        $this->command->info('✔️ Todos los índices estructurales se completaron con éxito.');
    }
}
