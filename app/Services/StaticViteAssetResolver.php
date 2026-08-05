<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\StaticViteAssets;
use JsonException;
use RuntimeException;

final class StaticViteAssetResolver
{
    public const CSS_ENTRY = 'resources/css/app.css';

    public const JS_ENTRY = 'resources/js/app.js';

    public function __construct(
        private readonly ?string $buildPath = null,
    ) {}

    public function resolve(string $publicBasePath = ''): StaticViteAssets
    {
        $buildPath = $this->buildPath();
        $manifestPath = $buildPath.'/manifest.json';

        if (! is_file($manifestPath)) {
            throw new RuntimeException(
                "No existe el manifest de Vite [{$manifestPath}]. Ejecuta primero: npm run build"
            );
        }

        try {
            $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                "El manifest de Vite [{$manifestPath}] contiene JSON invalido. Ejecuta nuevamente: npm run build",
                previous: $exception,
            );
        }

        if (! is_array($manifest)) {
            throw new RuntimeException(
                "El manifest de Vite [{$manifestPath}] no contiene un objeto valido. Ejecuta nuevamente: npm run build"
            );
        }

        if (! isset($manifest[self::CSS_ENTRY]) || ! is_array($manifest[self::CSS_ENTRY])) {
            throw new RuntimeException(
                'El manifest de Vite no contiene la entrada ['.self::CSS_ENTRY.']. Ejecuta nuevamente: npm run build'
            );
        }

        $stylesheets = [];
        $scripts = [];
        $visited = [];

        $this->collectEntry(self::CSS_ENTRY, $manifest, $stylesheets, $scripts, $visited, true);

        if (isset($manifest[self::JS_ENTRY]) && is_array($manifest[self::JS_ENTRY])) {
            $this->collectEntry(self::JS_ENTRY, $manifest, $stylesheets, $scripts, $visited, true);
        }

        if ($stylesheets === []) {
            throw new RuntimeException(
                'El manifest de Vite no resolvio ningun archivo CSS para ['.self::CSS_ENTRY.'].'
            );
        }

        foreach (array_merge($stylesheets, $scripts) as $file) {
            $assetPath = $buildPath.'/'.$file;

            if (! is_file($assetPath)) {
                throw new RuntimeException("El asset declarado por Vite no existe [{$assetPath}]. Ejecuta nuevamente: npm run build");
            }
        }

        return new StaticViteAssets(
            array_values(array_unique($stylesheets)),
            array_values(array_unique($scripts)),
            $publicBasePath,
        );
    }

    public function buildPath(): string
    {
        $configured = $this->buildPath ?? config('static_cms.vite.build_path');

        return rtrim((string) ($configured ?: public_path('build')), '/\\');
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<string>  $stylesheets
     * @param  list<string>  $scripts
     * @param  array<string, true>  $visited
     */
    private function collectEntry(
        string $key,
        array $manifest,
        array &$stylesheets,
        array &$scripts,
        array &$visited,
        bool $emitScriptFile,
    ): void {
        if (isset($visited[$key])) {
            if ($emitScriptFile) {
                $file = is_array($manifest[$key] ?? null) ? ($manifest[$key]['file'] ?? null) : null;

                if (is_string($file) && $this->isSafeManifestPath($file) && str_ends_with(strtolower($file), '.js')) {
                    $scripts[] = $file;
                }
            }

            return;
        }

        $visited[$key] = true;
        $entry = $manifest[$key] ?? null;

        if (! is_array($entry)) {
            throw new RuntimeException("El import [{$key}] referenciado por el manifest de Vite no existe o es invalido.");
        }

        $imports = $entry['imports'] ?? [];

        if (! is_array($imports)) {
            throw new RuntimeException("La entrada [{$key}] contiene un campo imports invalido en el manifest de Vite.");
        }

        foreach ($imports as $import) {
            if (! is_string($import) || $import === '') {
                throw new RuntimeException("La entrada [{$key}] contiene un import invalido en el manifest de Vite.");
            }

            $this->collectEntry($import, $manifest, $stylesheets, $scripts, $visited, false);
        }

        $cssFiles = $entry['css'] ?? [];

        if (! is_array($cssFiles)) {
            throw new RuntimeException("La entrada [{$key}] contiene un campo css invalido en el manifest de Vite.");
        }

        foreach ($cssFiles as $cssFile) {
            if (! is_string($cssFile) || ! $this->isSafeManifestPath($cssFile)) {
                throw new RuntimeException("La entrada [{$key}] contiene una ruta CSS insegura o invalida.");
            }

            $this->assertAssetExists($cssFile);

            $stylesheets[] = $cssFile;
        }

        $file = $entry['file'] ?? null;

        if (! is_string($file) || $file === '') {
            return;
        }

        if (! $this->isSafeManifestPath($file)) {
            throw new RuntimeException("La entrada [{$key}] contiene una ruta de asset insegura [{$file}].");
        }

        $this->assertAssetExists($file);

        if (str_ends_with(strtolower($file), '.css')) {
            $stylesheets[] = $file;

            return;
        }

        if ($emitScriptFile && str_ends_with(strtolower($file), '.js')) {
            $scripts[] = $file;
        }
    }

    private function assertAssetExists(string $file): void
    {
        $assetPath = $this->buildPath().'/'.$file;

        if (! is_file($assetPath)) {
            throw new RuntimeException("El asset declarado por Vite no existe [{$assetPath}]. Ejecuta nuevamente: npm run build");
        }
    }

    private function isSafeManifestPath(string $path): bool
    {
        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\');
    }
}
