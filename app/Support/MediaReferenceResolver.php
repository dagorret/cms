<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Site;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class MediaReferenceResolver
{
    /** @var array<string, true>|null */
    private ?array $trustedHosts = null;

    public function basePath(): string
    {
        $path = trim(str_replace('\\', '/', (string) config('static_cms.media.base_path')), '/');

        if ($path === '' || $this->containsUnsafeSegments($path)) {
            throw new RuntimeException('static_cms.media.base_path debe ser una ruta relativa segura.');
        }

        return $path;
    }

    public function canonicalUrl(Media $media, string $conversionName = ''): string
    {
        $suffix = $conversionName === ''
            ? $media->file_name
            : 'conversions/'.basename($media->getPathRelativeToRoot($conversionName));

        return $this->canonicalUrlFromSuffix($media, $suffix);
    }

    public function cmsUrl(Media $media, string $conversionName = ''): string
    {
        return $this->canonicalUrl($media, $conversionName);
    }

    public function staticUrl(Media $media, string $conversionName = ''): string
    {
        return $this->canonicalUrl($media, $conversionName);
    }

    /** @return array<string, mixed> */
    public function normalizeEditorJsPayload(array $payload): array
    {
        if (! isset($payload['blocks']) || ! is_array($payload['blocks'])) {
            return $payload;
        }

        foreach ($payload['blocks'] as &$block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'image') {
                continue;
            }

            $url = data_get($block, 'data.file.url');
            $mediaId = filter_var(data_get($block, 'data.file.media_id'), FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            $normalized = $this->normalizeUrl(is_string($url) ? $url : '', $mediaId === false ? null : $mediaId);

            if ($normalized === null) {
                continue;
            }

            data_set($block, 'data.file.url', $normalized['url']);
            data_set($block, 'data.file.media_id', $normalized['media_id']);
        }
        unset($block);

        return $payload;
    }

    /** @return array{url: string, media_id: int}|null */
    public function normalizeUrl(string $url, ?int $mediaId = null): ?array
    {
        $media = $mediaId ? Media::query()->find($mediaId) : $this->mediaFromRecognizedUrl($url);

        if (! $media) {
            return null;
        }

        $path = $this->urlPath($url);

        foreach ($this->candidateUrls($media) as $candidate) {
            if ($this->pathMatchesCandidate($path, $candidate['url'], $media)) {
                return [
                    'url' => $candidate['url'],
                    'media_id' => (int) $media->getKey(),
                ];
            }
        }

        if ($mediaId !== null) {
            return [
                'url' => $this->canonicalUrl($media),
                'media_id' => (int) $media->getKey(),
            ];
        }

        return null;
    }

    public function isSafeSuffix(string $path): bool
    {
        $path = trim(str_replace('\\', '/', rawurldecode($path)), '/');

        return $path !== ''
            && ! str_contains($path, "\0")
            && ! $this->containsUnsafeSegments($path);
    }

    private function canonicalUrlFromSuffix(Media $media, string $suffix): string
    {
        $suffix = trim(str_replace('\\', '/', $suffix), '/');

        if (! $this->isSafeSuffix($suffix)) {
            throw new RuntimeException('La ruta relativa del medio no es segura.');
        }

        return '/'.$this->basePath().'/'.$media->getKey().'/'.$suffix;
    }

    /** @return list<array{url: string}> */
    private function candidateUrls(Media $media): array
    {
        $urls = [['url' => $this->canonicalUrl($media)]];

        foreach (array_keys(array_filter($media->generated_conversions ?? [])) as $conversionName) {
            $urls[] = ['url' => $this->canonicalUrl($media, (string) $conversionName)];
        }

        return $urls;
    }

    private function mediaFromRecognizedUrl(string $url): ?Media
    {
        if ($url === '' || ! $this->hasTrustedOrigin($url)) {
            return null;
        }

        $path = $this->urlPath($url);
        $base = preg_quote($this->basePath(), '#');

        if (preg_match("#^/{$base}/([1-9][0-9]*)/(.+)$#", $path, $matches) !== 1
            && preg_match('#^/storage/(?:.+/)?([1-9][0-9]*)/(.+)$#', $path, $matches) !== 1) {
            return null;
        }

        return Media::query()->find((int) $matches[1]);
    }

    private function pathMatchesCandidate(string $path, string $canonicalUrl, Media $media): bool
    {
        if ($path === $canonicalUrl) {
            return true;
        }

        $suffix = substr($canonicalUrl, strlen('/'.$this->basePath().'/'.$media->getKey().'/'));

        return str_ends_with($path, '/'.$media->getKey().'/'.$suffix);
    }

    private function urlPath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? '/'.ltrim(rawurldecode($path), '/') : '';
    }

    private function hasTrustedOrigin(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null) {
            return str_starts_with($url, '/');
        }

        $host = strtolower($host);

        if ($this->trustedHosts === null) {
            $this->trustedHosts = [];
            $origins = [config('app.url')];

            if (Schema::hasTable('sites')) {
                $origins = [...$origins, ...Site::query()->pluck('domain')->all()];
            }

            foreach ($origins as $origin) {
                $trustedHost = parse_url((string) $origin, PHP_URL_HOST);

                if (is_string($trustedHost) && $trustedHost !== '') {
                    $this->trustedHosts[strtolower($trustedHost)] = true;
                }
            }
        }

        return isset($this->trustedHosts[$host]);
    }

    private function containsUnsafeSegments(string $path): bool
    {
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return true;
            }
        }

        return false;
    }
}
