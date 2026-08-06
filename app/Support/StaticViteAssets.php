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
        public ?string $mathJaxScript = null,
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

    public function mathJaxScriptUrl(): ?string
    {
        return $this->mathJaxScript !== null ? $this->url($this->mathJaxScript) : null;
    }

    private function url(string $file): string
    {
        $basePath = trim($this->publicBasePath, '/');
        $prefix = $basePath === '' ? '' : '/'.$basePath;

        return $prefix.'/build/'.ltrim($file, '/');
    }
}
