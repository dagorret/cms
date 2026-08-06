<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Support\PostBodyRenderer;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

final class PostPreviewService
{
    public function __construct(private readonly ?string $directory = null) {}

    public function generate(int $userId, array $data): string
    {
        $html = view('site.posts.preview', $this->viewData($data))->render();

        $this->writeAtomically($userId, $html);

        return $html;
    }

    public function read(int $userId): ?string
    {
        $directory = $this->previewDirectory(create: false);

        if ($directory === null) {
            return null;
        }

        $path = $this->previewPath($directory, $userId);

        if (! is_file($path) || is_link($path)) {
            return null;
        }

        $realPath = realpath($path);

        if ($realPath === false || ! $this->isInside($realPath, $directory)) {
            throw new RuntimeException('La ruta de la vista previa no es segura.');
        }

        $contents = file_get_contents($realPath);

        if ($contents === false) {
            throw new RuntimeException('No se pudo leer la vista previa.');
        }

        return $contents;
    }

    public function pathForUser(int $userId): string
    {
        $directory = $this->previewDirectory(create: true);

        if ($directory === null) {
            throw new RuntimeException('No se pudo preparar el directorio de vistas previas.');
        }

        return $this->previewPath($directory, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(array $data): array
    {
        $publishedAt = null;

        if (filled($data['published_at'] ?? null)) {
            try {
                $publishedAt = CarbonImmutable::parse((string) $data['published_at']);
            } catch (Throwable) {
                // Una fecha incompleta no debe impedir previsualizar un borrador.
            }
        }

        $categoryName = null;
        $categoryId = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);

        if ($categoryId !== false && $categoryId !== null) {
            $categoryName = Category::query()->whereKey($categoryId)->value('name');
        }

        return [
            'title' => filled($data['title'] ?? null) ? (string) $data['title'] : 'Sin título',
            'type' => ($data['type'] ?? null) === Post::TYPE_PAGE ? Post::TYPE_PAGE : Post::TYPE_POST,
            'categoryName' => $categoryName,
            'publishedAt' => $publishedAt,
            'keywords' => filled($data['keywords'] ?? null) ? (string) $data['keywords'] : null,
            'renderedBody' => PostBodyRenderer::render($data['body'] ?? ''),
        ];
    }

    private function writeAtomically(int $userId, string $html): void
    {
        $directory = $this->previewDirectory(create: true);

        if ($directory === null) {
            throw new RuntimeException('No se pudo preparar el directorio de vistas previas.');
        }

        $target = $this->previewPath($directory, $userId);

        if (is_link($target)) {
            throw new RuntimeException('El archivo de vista previa no puede ser un enlace simbólico.');
        }

        $temporary = tempnam($directory, ".post-preview-{$userId}-");

        if ($temporary === false || ! $this->isInside($temporary, $directory) || is_link($temporary)) {
            throw new RuntimeException('No se pudo crear el archivo temporal de la vista previa.');
        }

        try {
            $handle = fopen($temporary, 'wb');

            if ($handle === false) {
                throw new RuntimeException('No se pudo abrir el archivo temporal de la vista previa.');
            }

            try {
                $length = strlen($html);
                $written = 0;

                while ($written < $length) {
                    $bytes = fwrite($handle, substr($html, $written));

                    if ($bytes === false || $bytes === 0) {
                        throw new RuntimeException('La vista previa no se pudo escribir por completo.');
                    }

                    $written += $bytes;
                }

                if (! fflush($handle)) {
                    throw new RuntimeException('No se pudo sincronizar el archivo temporal de la vista previa.');
                }

                if (function_exists('fsync') && ! fsync($handle)) {
                    throw new RuntimeException('No se pudo confirmar la escritura de la vista previa.');
                }
            } finally {
                fclose($handle);
            }

            if (filesize($temporary) !== strlen($html)) {
                throw new RuntimeException('La vista previa escrita está incompleta.');
            }

            clearstatcache(true, $target);

            if (is_link($target)) {
                throw new RuntimeException('El archivo de vista previa no puede ser un enlace simbólico.');
            }

            if (! rename($temporary, $target)) {
                throw new RuntimeException('No se pudo publicar atómicamente la vista previa.');
            }

            @chmod($target, 0600);
        } finally {
            if (is_file($temporary) && ! is_link($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function previewDirectory(bool $create): ?string
    {
        $storageRoot = storage_path('app');

        if (! is_dir($storageRoot) && ! mkdir($storageRoot, 0700, true) && ! is_dir($storageRoot)) {
            throw new RuntimeException('No se pudo preparar storage/app.');
        }

        $realStorageRoot = realpath($storageRoot);

        if ($realStorageRoot === false || is_link($storageRoot)) {
            throw new RuntimeException('El directorio storage/app no es seguro.');
        }

        $directory = $this->directory ?? $realStorageRoot.DIRECTORY_SEPARATOR.'previews';
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);

        if (! $this->isInside($directory, $realStorageRoot)) {
            throw new RuntimeException('El directorio configurado de vistas previas está fuera de storage/app.');
        }

        if (is_link($directory)) {
            throw new RuntimeException('El directorio de vistas previas no puede ser un enlace simbólico.');
        }

        if (! is_dir($directory)) {
            if (! $create) {
                return null;
            }

            if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException('No se pudo crear el directorio de vistas previas.');
            }
        }

        $realDirectory = realpath($directory);

        if ($realDirectory === false || ! $this->isInside($realDirectory, $realStorageRoot)) {
            throw new RuntimeException('El directorio de vistas previas está fuera de storage/app.');
        }

        return $realDirectory;
    }

    private function previewPath(string $directory, int $userId): string
    {
        if ($userId < 1) {
            throw new RuntimeException('El usuario autenticado no tiene un identificador válido.');
        }

        return $directory.DIRECTORY_SEPARATOR."post-preview-{$userId}.html";
    }

    private function isInside(string $path, string $directory): bool
    {
        return str_starts_with($path, rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }
}
