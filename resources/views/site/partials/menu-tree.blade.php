@php
    $normalizeMenuPath = function (?string $path): string {
        $path = parse_url((string) $path, PHP_URL_PATH) ?: '/';
        $path = preg_replace('#/page/\d+/?$#', '/', $path);
        return '/' . trim(str_replace('/index.html', '/', $path), '/') . (trim(str_replace('/index.html', '/', $path), '/') === '' ? '' : '/');
    };
    $activePath = $normalizeMenuPath($currentPath ?? null);
    $renderItems = function (array $nodes) use (&$renderItems, $normalizeMenuPath, $activePath): string {
        if ($nodes === []) {
            return '';
        }

        $html = '<ul class="flex flex-wrap gap-[18px] [&_ul]:ml-4 [&_ul]:mt-2 [&_ul]:block">';

        foreach ($nodes as $node) {
            $url = e((string) $node['url']);
            $label = e((string) $node['label']);
            $target = in_array($node['target'] ?? '_self', ['_self', '_blank'], true) ? $node['target'] : '_self';
            $rel = trim((string) ($node['rel'] ?? ''));
            $category = trim((string) ($node['category'] ?? ''));
            $attributes = ' href="'.$url.'"';

            if ($target !== '_self') {
                $attributes .= ' target="'.e($target).'"';
            }

            if ($rel !== '') {
                $attributes .= ' rel="'.e($rel).'"';
            }

            if (! str_starts_with($url, 'http') && $normalizeMenuPath($url) === $activePath) {
                $attributes .= ' aria-current="page"';
            }

            if ($category !== '') {
                $attributes .= ' data-category="'.e($category).'" data-json-url="'.e((string) $node['json_url']).'"';
            }

            $html .= '<li><a'.$attributes.'>'.$label.'</a>'.$renderItems($node['children'] ?? []).'</li>';
        }

        return $html.'</ul>';
    };
@endphp
{!! $renderItems($items) !!}
