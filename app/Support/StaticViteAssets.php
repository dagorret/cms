<?php

declare(strict_types=1);

namespace App\Support;

final readonly class StaticViteAssets
{
    /**
     * @param  list<string>  $stylesheets
     * @param  list<string>  $scripts
     */
    public function __construct(
        public array $stylesheets,
        public array $scripts,
        public string $publicBasePath = '',
    ) {}

    /** @return list<string> */
    public function stylesheetUrls(): array
    {
        return array_map($this->url(...), $this->stylesheets);
    }

    /** @return list<string> */
    public function scriptUrls(): array
    {
        return array_map($this->url(...), $this->scripts);
    }

    private function url(string $file): string
    {
        $basePath = trim($this->publicBasePath, '/');
        $prefix = $basePath === '' ? '' : '/'.$basePath;

        return $prefix.'/build/'.ltrim($file, '/');
    }
}
