<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\MediaReferenceResolver;
use Illuminate\Http\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ManagedMediaController extends Controller
{
    public function __invoke(Media $media, string $path, MediaReferenceResolver $references): Response|BinaryFileResponse
    {
        abort_unless($references->isSafeSuffix($path), 404);

        $path = trim(str_replace('\\', '/', rawurldecode($path)), '/');
        $baseDirectory = dirname($media->getPath());
        $candidate = $baseDirectory.'/'.$path;
        $realBase = realpath($baseDirectory);
        $realCandidate = realpath($candidate);

        abort_if(
            $realBase === false
            || $realCandidate === false
            || is_link($candidate)
            || ! is_file($realCandidate)
            || ! str_starts_with($realCandidate, rtrim($realBase, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR),
            404,
        );

        return response()->file($realCandidate, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
