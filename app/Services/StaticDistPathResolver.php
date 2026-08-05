<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class StaticDistPathResolver
{
    public function resolve(string $configuredPath): string
    {
        $configuredPath = trim($configuredPath);

        if ($configuredPath === '') {
            throw new RuntimeException('dist_path no puede estar vacio.');
        }

        $projectRoot = $this->normalizeAbsolute(base_path());

        if (! $this->isAbsolutePath($configuredPath)) {
            $relative = $this->normalizeRelative($configuredPath);
            $resolved = $this->normalizeAbsolute($projectRoot.'/'.$relative);

            return $this->validateInsideProject($resolved, $projectRoot);
        }

        $absolute = $this->normalizeAbsolute($configuredPath);

        if ($this->isInside($absolute, $projectRoot)) {
            return $this->validateInsideProject($absolute, $projectRoot);
        }

        $portable = $this->portableLegacyPath($absolute);

        if ($portable !== null) {
            return $this->validateInsideProject(
                $this->normalizeAbsolute($projectRoot.'/'.$portable),
                $projectRoot,
            );
        }

        throw new RuntimeException(
            "El dist_path absoluto [{$configuredPath}] queda fuera del proyecto [{$projectRoot}]. Usa una ruta relativa como [dist] o configura una salida dentro del proyecto."
        );
    }

    private function portableLegacyPath(string $absolutePath): ?string
    {
        $distRoot = trim((string) config('static_cms.dist_root', 'dist'), '/\\');

        if ($distRoot === '' || str_contains($distRoot, '..')) {
            throw new RuntimeException('static_cms.dist_root debe ser una ruta relativa segura.');
        }

        $needle = '/'.$distRoot;
        $position = strrpos($absolutePath, $needle);

        if ($position === false) {
            return null;
        }

        $suffix = ltrim(substr($absolutePath, $position + 1), '/');

        return $suffix === $distRoot || str_starts_with($suffix, $distRoot.'/')
            ? $suffix
            : null;
    }

    private function validateInsideProject(string $path, string $projectRoot): string
    {
        if (! $this->isInside($path, $projectRoot) || $path === $projectRoot) {
            throw new RuntimeException("La salida estatica [{$path}] debe ser un subdirectorio del proyecto [{$projectRoot}].");
        }

        $publicPath = $this->normalizeAbsolute(public_path());

        if ($path === $publicPath) {
            throw new RuntimeException('dist_path no puede apuntar directamente a public_path(). Usa una ruta aislada por sitio.');
        }

        return $path;
    }

    private function normalizeRelative(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new RuntimeException("dist_path contiene un segmento no permitido [..]: {$path}");
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new RuntimeException('dist_path debe identificar un subdirectorio concreto.');
        }

        return implode('/', $segments);
    }

    private function normalizeAbsolute(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = str_starts_with($path, '/') ? '/' : '';
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return $prefix.implode('/', $segments);
    }

    private function isInside(string $path, string $root): bool
    {
        $root = rtrim($root, '/');

        return $path === $root || str_starts_with($path, $root.'/');
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1
            || str_starts_with($path, '\\\\');
    }
}
