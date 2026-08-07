<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use App\Models\Site;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use XMLWriter;

final class StaticSitemapGenerator
{
    /** @return array{files:int,urls:int,peak_buffer:int} */
    public function generate(Site $site, string $distPath): array
    {
        $dist = realpath($distPath);
        if ($dist === false || is_link($distPath)) {
            throw new RuntimeException('El dist_path del sitemap no es seguro.');
        }

        $destination = $dist.'/sitemaps';
        $indexDestination = $dist.'/sitemap.xml';
        if (is_link($destination) || is_link($indexDestination)) {
            throw new RuntimeException('No se publican sitemaps sobre enlaces simbólicos.');
        }

        $temporary = $dist.'/.sitemaps-publishing-'.bin2hex(random_bytes(6));
        $temporaryFiles = $temporary.'/sitemaps';
        File::ensureDirectoryExists($temporaryFiles);
        $limit = max((int) config('static_cms.sitemap_urls_per_file', 1000), 1);
        $publicPath = $this->publicPath($site);
        $baseUrl = $this->baseUrl($site).$publicPath;
        $filesCount = 0;
        $urlCount = 0;
        $peakBuffer = 0;
        $partsManifest = $temporary.'/parts.jsonl';
        $partsHandle = fopen($partsManifest, 'xb');
        if ($partsHandle === false) {
            throw new RuntimeException('No se pudo crear el manifiesto temporal del sitemap.');
        }

        try {
            foreach ($this->groups($site, $baseUrl) as $name => $entries) {
                $buffer = [];
                $part = 1;

                foreach ($entries as $entry) {
                    $buffer[] = $entry;
                    $peakBuffer = max($peakBuffer, count($buffer));

                    if (count($buffer) >= $limit) {
                        $file = $this->writeUrlSet($temporaryFiles, $baseUrl, $name, $part++, $buffer);
                        fwrite($partsHandle, json_encode($file, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
                        $filesCount++;
                        $urlCount += count($buffer);
                        $buffer = [];
                    }
                }

                if ($buffer !== []) {
                    $file = $this->writeUrlSet($temporaryFiles, $baseUrl, $name, $part, $buffer);
                    fwrite($partsHandle, json_encode($file, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
                    $filesCount++;
                    $urlCount += count($buffer);
                }
            }

            fclose($partsHandle);
            $partsHandle = null;
            $this->writeIndex($temporary.'/sitemap.xml', $partsManifest);
            File::delete($partsManifest);
            $this->validateXml($temporary.'/sitemap.xml');
            $this->publish($dist, $temporary);
            $this->updateRobots($site, $dist);
        } catch (Throwable $exception) {
            if (is_resource($partsHandle)) {
                fclose($partsHandle);
            }
            if (File::isDirectory($temporary)) {
                File::deleteDirectory($temporary);
            }
            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException('No se pudo generar el sitemap: '.$exception->getMessage(), previous: $exception);
        }

        return ['files' => $filesCount, 'urls' => $urlCount, 'peak_buffer' => $peakBuffer];
    }

    /** @return array<string, iterable<array{url:string,lastmod:?string}>> */
    private function groups(Site $site, string $baseUrl): array
    {
        return [
            'posts' => $this->postEntries($site, Post::TYPE_POST, $baseUrl),
            'pages' => $this->pageEntries($site, $baseUrl),
            'categories' => $this->categoryEntries($site, $baseUrl),
            'pagination' => $this->paginationEntries($site, $baseUrl),
            'archives' => $this->archiveEntries($site, $baseUrl),
        ];
    }

    private function postEntries(Site $site, string $type, string $baseUrl): iterable
    {
        foreach ($this->publishedQuery($site)->where('type', $type)->lazyById(500) as $post) {
            yield ['url' => $baseUrl.'/'.$post->slug.'/', 'lastmod' => $this->date($post->updated_at)];
        }
    }

    private function pageEntries(Site $site, string $baseUrl): iterable
    {
        $lastmod = $this->publishedQuery($site)->max('updated_at');
        yield ['url' => $baseUrl.'/', 'lastmod' => $this->date($lastmod)];
        yield from $this->postEntries($site, Post::TYPE_PAGE, $baseUrl);
    }

    private function categoryEntries(Site $site, string $baseUrl): iterable
    {
        $categories = $site->categories()->where('is_visible', true)
            ->whereHas('posts', fn ($query) => $query->where('status', Post::STATUS_PUBLISHED)->where('type', Post::TYPE_POST))
            ->orderBy('id')->cursor();

        foreach ($categories as $category) {
            yield ['url' => $baseUrl.'/category/'.$category->slug.'/', 'lastmod' => $this->date($category->updated_at)];
        }
    }

    private function paginationEntries(Site $site, string $baseUrl): iterable
    {
        $first = max((int) config('static_cms.home_first_page_posts', 10), 1);
        $perPage = max((int) config('static_cms.posts_per_home_page', 20), 1);
        $maxPages = max((int) config('static_cms.max_home_pages', 20), 1);
        $posts = $this->publishedQuery($site)->where('type', Post::TYPE_POST);
        $count = (clone $posts)->count();
        $lastmod = $this->date((clone $posts)->max('updated_at'));
        $total = min($maxPages, $count <= $first ? 1 : 1 + (int) ceil(($count - $first) / $perPage));

        for ($page = 2; $page <= $total; $page++) {
            yield ['url' => $baseUrl.'/page/'.$page.'/', 'lastmod' => $lastmod];
        }

        $categoryCounts = $site->categories()->where('is_visible', true)
            ->withCount(['posts as published_posts_count' => fn ($query) => $query->where('status', Post::STATUS_PUBLISHED)->where('type', Post::TYPE_POST)])
            ->orderBy('id')->cursor();

        foreach ($categoryCounts as $category) {
            $pages = (int) ceil($category->published_posts_count / $perPage);
            for ($page = 2; $page <= $pages; $page++) {
                yield ['url' => $baseUrl.'/category/'.$category->slug.'/page/'.$page.'/', 'lastmod' => $this->date($category->updated_at)];
            }
        }
    }

    private function archiveEntries(Site $site, string $baseUrl): iterable
    {
        yield ['url' => $baseUrl.'/archive/', 'lastmod' => null];
        foreach ([4, 7, 10] as $length) {
            $periods = $this->publishedQuery($site)
                ->where('type', Post::TYPE_POST)
                ->whereNotNull('created_at')
                ->selectRaw("substr(created_at, 1, {$length}) as period, max(updated_at) as lastmod")
                ->groupBy('period')
                ->orderBy('period')
                ->cursor();
            foreach ($periods as $period) {
                yield [
                    'url' => $baseUrl.'/archive/'.str_replace('-', '/', (string) $period->period).'/',
                    'lastmod' => $this->date($period->lastmod),
                ];
            }
        }
    }

    private function publishedQuery(Site $site): Builder
    {
        return Post::query()->where(fn ($query) => $query->where('site_id', $site->getKey())->orWhere('site_id', $site->short_name))
            ->where('status', Post::STATUS_PUBLISHED)
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->select(['id', 'slug', 'type', 'created_at', 'updated_at']);
    }

    /** @param list<array{url:string,lastmod:?string}> $entries */
    private function writeUrlSet(string $directory, string $baseUrl, string $name, int $part, array $entries): array
    {
        $filename = "{$name}-{$part}.xml";
        $path = $directory.'/'.$filename;
        $writer = new XMLWriter;
        $writer->openUri($path);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $latest = null;

        foreach ($entries as $entry) {
            $writer->startElement('url');
            $writer->writeElement('loc', $entry['url']);
            if ($entry['lastmod']) {
                $writer->writeElement('lastmod', $entry['lastmod']);
            }
            $writer->endElement();
            if ($entry['lastmod'] && strcmp($entry['lastmod'], (string) $latest) > 0) {
                $latest = $entry['lastmod'];
            }
        }

        $writer->endElement();
        $writer->endDocument();
        $writer->flush();
        $this->validateXml($path);

        return ['filename' => $filename, 'url' => $baseUrl.'/sitemaps/'.$filename, 'lastmod' => $latest];
    }

    private function writeIndex(string $path, string $partsManifest): void
    {
        $writer = new XMLWriter;
        $writer->openUri($path);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('sitemapindex');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $handle = fopen($partsManifest, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo leer el manifiesto temporal del sitemap.');
        }
        while (($line = fgets($handle)) !== false) {
            $file = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $writer->startElement('sitemap');
            $writer->writeElement('loc', $file['url']);
            if ($file['lastmod']) {
                $writer->writeElement('lastmod', $file['lastmod']);
            }
            $writer->endElement();
        }
        fclose($handle);
        $writer->endElement();
        $writer->endDocument();
        $writer->flush();
    }

    private function validateXml(string $path): void
    {
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $reader = new \XMLReader;
        if (! $reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException("El XML generado no se pudo abrir [{$path}].");
        }
        try {
            while ($reader->read()) {
                // XMLReader valida la estructura incrementalmente sin cargar el documento.
            }
        } finally {
            $reader->close();
        }
        $errors = libxml_get_errors();
        libxml_clear_errors();
        if ($errors !== []) {
            throw new RuntimeException("El XML generado no es válido [{$path}].");
        }
    }

    private function publish(string $dist, string $temporary): void
    {
        $destination = $dist.'/sitemaps';
        $index = $dist.'/sitemap.xml';
        $backup = $dist.'/.sitemaps-previous-'.bin2hex(random_bytes(5));
        $indexBackup = $dist.'/.sitemap-previous-'.bin2hex(random_bytes(5)).'.xml';

        $publishedDirectory = false;
        $publishedIndex = false;

        try {
            if (File::isDirectory($destination) && ! rename($destination, $backup)) {
                throw new RuntimeException('No se pudo apartar el sitemap anterior.');
            }
            if (File::isFile($index) && ! rename($index, $indexBackup)) {
                throw new RuntimeException('No se pudo apartar el índice anterior.');
            }
            if (! rename($temporary.'/sitemaps', $destination)) {
                throw new RuntimeException('No se pudo publicar el directorio de sitemaps.');
            }
            $publishedDirectory = true;
            if (! rename($temporary.'/sitemap.xml', $index)) {
                throw new RuntimeException('No se pudo publicar el índice del sitemap.');
            }
            $publishedIndex = true;
            File::deleteDirectory($temporary);
            if (File::isDirectory($backup)) {
                File::deleteDirectory($backup);
            }
            if (File::isFile($indexBackup)) {
                File::delete($indexBackup);
            }
        } catch (Throwable $exception) {
            if ($publishedDirectory && File::isDirectory($destination)) {
                File::deleteDirectory($destination);
            }
            if ($publishedIndex && File::isFile($index)) {
                File::delete($index);
            }
            if (File::isDirectory($backup)) {
                rename($backup, $destination);
            }
            if (File::isFile($indexBackup)) {
                rename($indexBackup, $index);
            }
            throw $exception;
        }
    }

    private function updateRobots(Site $site, string $dist): void
    {
        $path = $dist.'/robots.txt';
        if (is_link($path)) {
            throw new RuntimeException('robots.txt no puede ser un enlace simbólico.');
        }
        $lines = File::isFile($path) ? preg_split('/\R/', trim(File::get($path))) : ['User-agent: *', 'Allow: /'];
        $lines = array_values(array_filter($lines, fn ($line) => ! str_starts_with(strtolower(trim((string) $line)), 'sitemap:')));
        $lines[] = 'Sitemap: '.$this->baseUrl($site).$this->publicPath($site).'/sitemap.xml';
        File::put($path, implode(PHP_EOL, $lines).PHP_EOL);
    }

    private function publicPath(Site $site): string
    {
        return $site->publicPath();
    }

    private function baseUrl(Site $site): string
    {
        return $site->publicOrigin();
    }

    private function date(DateTimeInterface|string|null $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10);
    }
}
