<?php

declare(strict_types=1);

namespace App\Services;

use FilesystemIterator;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class StaticViteAssetPublisher
{
    public function publish(string $sourceBuildPath, string $distPath): string
    {
        if (is_link($sourceBuildPath) || is_link($distPath)) {
            throw new RuntimeException('No se permite publicar assets desde o hacia enlaces simbolicos.');
        }

        $source = realpath($sourceBuildPath);
        $dist = realpath($distPath);

        if ($source === false || ! is_dir($source)) {
            throw new RuntimeException("No existe el directorio de build de Vite [{$sourceBuildPath}]. Ejecuta primero: npm run build");
        }

        if ($dist === false || ! is_dir($dist)) {
            throw new RuntimeException("El directorio de salida estatica no existe [{$distPath}].");
        }

        $destination = $dist.'/build';
        $temporary = $dist.'/.build-publishing-'.bin2hex(random_bytes(8));
        $backup = $dist.'/.build-previous-'.bin2hex(random_bytes(8));

        $this->assertInside($dist, $destination);
        $this->assertInside($dist, $temporary);
        $this->assertInside($dist, $backup);

        try {
            $this->copyWithoutLinks($source, $temporary);

            if (! is_file($temporary.'/manifest.json')) {
                throw new RuntimeException('La copia temporal del build de Vite no contiene manifest.json.');
            }

            if (file_exists($destination) || is_link($destination)) {
                if (is_link($destination) || ! is_dir($destination)) {
                    throw new RuntimeException("El destino de assets [{$destination}] no es un directorio seguro.");
                }

                if (! rename($destination, $backup)) {
                    throw new RuntimeException("No se pudo apartar el build anterior [{$destination}].");
                }
            }

            if (! rename($temporary, $destination)) {
                if (is_dir($backup)) {
                    rename($backup, $destination);
                }

                throw new RuntimeException("No se pudo publicar atomicamente el build de Vite en [{$destination}].");
            }

            if (is_dir($backup)) {
                File::deleteDirectory($backup);
            }
        } catch (Throwable $exception) {
            if (is_dir($temporary)) {
                File::deleteDirectory($temporary);
            }

            if (! is_dir($destination) && is_dir($backup)) {
                rename($backup, $destination);
            }

            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException('No se pudo publicar el build de Vite: '.$exception->getMessage(), previous: $exception);
        }

        return $destination;
    }

    private function copyWithoutLinks(string $source, string $destination): void
    {
        File::ensureDirectoryExists($destination, 0755, true);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException("El build de Vite contiene un enlace simbolico no permitido [{$item->getPathname()}].");
            }

            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            $target = $destination.'/'.$relativePath;

            if ($item->isDir()) {
                File::ensureDirectoryExists($target, 0755, true);

                continue;
            }

            File::ensureDirectoryExists(dirname($target), 0755, true);

            if (! copy($item->getPathname(), $target)) {
                throw new RuntimeException("No se pudo copiar el asset de Vite [{$relativePath}].");
            }
        }
    }

    private function assertInside(string $root, string $path): void
    {
        $root = rtrim(str_replace('\\', '/', $root), '/').'/';
        $path = str_replace('\\', '/', $path);

        if (! str_starts_with($path.'/', $root)) {
            throw new RuntimeException("La ruta de publicacion [{$path}] queda fuera de dist [{$root}].");
        }
    }
}
