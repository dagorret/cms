<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Site;
use App\Support\PostBodyMathDetector;
use App\Support\StaticViteAssets;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class StaticContentCompiler
{
    protected const ENTRY_MANIFEST = '.cms-faro-entry.json';

    public function __construct(
        protected Command $command,
        protected Site $site,
        protected string $targetFolder,
        protected bool $force,
        protected bool $resource,
        protected StaticViteAssets $staticAssets,
    ) {}

    public function compile($entries)
    {
        $publicPath = $this->publicPath();
        $menuRenderer = new MenuRenderer;
        $menuStructure = $menuRenderer->structure($this->site, 'primary', $publicPath);
        $postChronology = new PostChronology;

        foreach ($entries as $entry) {
            if (empty($entry->slug)) {
                continue;
            }

            $entryFolder = $this->targetFolder.'/'.$entry->slug;
            $htmlFile = $entryFolder.'/index.html';

            // 🚀 Cero consultas I/O costosas al disco sobre fechas.
            // Confiamos al 100% en la Base de Datos como Fuente de Verdad Primaria.
            if (! File::exists($entryFolder)) {
                File::makeDirectory($entryFolder, 0755, true);
            }

            $viewName = $entry->type === Post::TYPE_PAGE && view()->exists('site.page')
                ? 'site.page'
                : 'site.posts.show';
            $chronology = $postChronology->adjacentTo($entry);
            $hasMath = (bool) $entry->has_math || PostBodyMathDetector::containsMath($entry->body);

            if ($hasMath && $this->staticAssets->mathJaxScriptUrl() === null) {
                throw new \RuntimeException('El manifest de Vite no contiene la entrada de MathJax. Ejecuta primero: npm run build');
            }

            $html = view($viewName, [
                'post' => $entry,
                'site' => $this->site,
                'subdir' => $publicPath,
                'subdirUrl' => $publicPath,
                'canonicalPath' => trim((string) $entry->slug, '/'),
                'generatedMenu' => $menuRenderer->renderStructure($menuStructure, rtrim($publicPath, '/').'/'.$entry->slug.'/'),
                'staticAssets' => $this->staticAssets,
                'hasMath' => $hasMath,
                'previousPost' => $this->navigationItem($chronology['previous'], $publicPath),
                'nextPost' => $this->navigationItem($chronology['next'], $publicPath),
            ])->render();

            $html = StaticHtmlCleaner::clean($html);

            File::put($htmlFile, $html);
            File::put($entryFolder.'/'.self::ENTRY_MANIFEST, json_encode([
                'post_id' => $entry->id,
                'site_id' => $this->site->getKey(),
                'site_short_name' => $this->site->short_name,
                'slug' => $entry->slug,
                'status' => $entry->status,
                'built_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    protected function publicPath(): string
    {
        return $this->site->publicPath();
    }

    /** @return array{title: string, url: string}|null */
    protected function navigationItem(?Post $post, string $publicPath): ?array
    {
        if (! $post) {
            return null;
        }

        return [
            'title' => (string) $post->title,
            'url' => rtrim($publicPath, '/').'/'.trim((string) $post->slug, '/').'/',
        ];
    }
}
