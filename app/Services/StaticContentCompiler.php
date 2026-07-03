<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class StaticContentCompiler
{
    public function __construct(
        protected Command $command,
        protected Site $site,
        protected string $targetFolder,
        protected bool $force,
        protected bool $resource
    ) {}

    public function compile($entries)
    {
        foreach ($entries as $entry) {
            if (empty($entry->slug)) continue;

            $entryFolder = $this->targetFolder . '/' . $entry->slug;
            $htmlFile = $entryFolder . '/index.html';

            // 🚀 Cero consultas I/O costosas al disco sobre fechas.
            // Confiamos al 100% en la Base de Datos como Fuente de Verdad Primaria.
            if (!File::exists($entryFolder)) {
                File::makeDirectory($entryFolder, 0755, true);
            }

            $viewName = ($entry->type ?? 'post') === 'page' ? 'site.page' : 'site.post';
            $html = view($viewName, ['post' => $entry, 'site' => $this->site])->render();
            File::put($htmlFile, $html);
        }
    }
}
