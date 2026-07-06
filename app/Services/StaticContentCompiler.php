<?php

namespace App\Services;

use App\Models\Site;
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
        protected bool $resource
    ) {}

    public function compile($entries)
    {
        $publicPath = $this->publicPath();

        foreach ($entries as $entry) {
            if (empty($entry->slug)) continue;

            $entryFolder = $this->targetFolder . '/' . $entry->slug;
            $htmlFile = $entryFolder . '/index.html';

            // 🚀 Cero consultas I/O costosas al disco sobre fechas.
            // Confiamos al 100% en la Base de Datos como Fuente de Verdad Primaria.
            if (!File::exists($entryFolder)) {
                File::makeDirectory($entryFolder, 0755, true);
            }

            $viewName = ($entry->type ?? 'post') === 'page' && view()->exists('site.page')
                ? 'site.page'
                : 'site.posts.show';

            $html = view($viewName, [
                'post' => $entry,
                'site' => $this->site,
                'subdir' => $publicPath,
                'subdirUrl' => $publicPath,
            ])->render();

            $html = StaticHtmlCleaner::clean($html);

            File::put($htmlFile, $html);
            File::put($entryFolder . '/' . self::ENTRY_MANIFEST, json_encode([
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
        $path = trim((string) $this->site->subdir, '/');

        if ($path === '' || $path === 'dist') {
            return '';
        }

        return '/' . $path;
    }
}
