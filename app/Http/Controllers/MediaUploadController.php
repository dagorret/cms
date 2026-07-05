<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class MediaUploadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $file = $this->resolveUploadedFile($request);

            $validator = Validator::make(
                ['file' => $file],
                ['file' => ['required', 'file', 'max:51200']],
            );

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'El archivo enviado no es valido.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                return response()->json([
                    'message' => 'El archivo enviado no es valido.',
                ], 422);
            }

            $relativePath = $this->resolveRelativePath();
            $filename = $this->buildFilename($file);

            Storage::disk('public')->makeDirectory($relativePath);

            $storedPath = $file->storeAs($relativePath, $filename, 'public');

            if (! is_string($storedPath) || $storedPath === '') {
                throw new RuntimeException('No se pudo guardar el archivo en el disco public.');
            }

            $publicUrl = '/' . trim($storedPath, '/');

            return response()->json([
                'url' => $publicUrl,
                'path' => trim($storedPath, '/'),
                'filename' => $filename,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo subir el archivo.',
            ], 500);
        }
    }

    protected function resolveUploadedFile(Request $request): ?UploadedFile
    {
        foreach (['file', 'upload', 'image'] as $key) {
            $file = $request->file($key);

            if ($file instanceof UploadedFile) {
                return $file;
            }
        }

        return null;
    }

    protected function resolveRelativePath(): string
    {
        $basePath = trim((string) config('static_cms.media.base_path'), '/');
        $subfolder = trim((string) config('static_cms.media.subfolder'), '/');
        $dateFormat = trim((string) config('static_cms.media.date_format'));
        $datedPath = $dateFormat !== '' ? date($dateFormat) : null;

        $segments = array_filter(
            [$basePath, $subfolder, $datedPath],
            static fn (?string $segment): bool => $segment !== null && trim($segment, '/') !== '',
        );

        $relativePath = implode('/', $segments);

        if ($relativePath === '') {
            throw new RuntimeException('La configuracion static_cms.media.base_path no puede quedar vacia.');
        }

        return $relativePath;
    }

    protected function buildFilename(UploadedFile $file): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = Str::slug($name) ?: 'media';
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');

        return sprintf('%s-%s-%s.%s', $slug, date('YmdHis'), Str::random(8), $extension);
    }
}
