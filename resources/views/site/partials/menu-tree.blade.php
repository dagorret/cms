@php
    $normalizeMenuPath = function (?string $path): string {
        $path = parse_url((string) $path, PHP_URL_PATH) ?: '/';
        $path = preg_replace('#/page/\d+/?$#', '/', $path);
        $normalized = trim(str_replace('/index.html', '/', $path), '/');

        return '/'.$normalized.($normalized === '' ? '' : '/');
    };
    $activePath = $normalizeMenuPath($currentPath ?? null);
    $renderItems = function (array $nodes, int $depth = 0, string $branch = 'root') use (&$renderItems, $normalizeMenuPath, $activePath): string {
        if ($nodes === []) {
            return '';
        }

        $rootAttributes = $depth === 0
            ? ' id="site-menu-tree" class="site-menu__root" data-menu-root'
            : ' class="site-menu__submenu" data-menu-submenu';
        $html = '<ul'.$rootAttributes.'>';

        foreach ($nodes as $index => $node) {
            $url = e((string) $node['url']);
            $label = e((string) $node['label']);
            $target = in_array($node['target'] ?? '_self', ['_self', '_blank'], true) ? $node['target'] : '_self';
            $rel = trim((string) ($node['rel'] ?? ''));
            $category = trim((string) ($node['category'] ?? ''));
            $children = $node['children'] ?? [];
            $hasChildren = $children !== [];
            $nodeKey = isset($node['id']) ? (string) $node['id'] : $branch.'-'.$index;
            $submenuId = 'submenu-'.preg_replace('/[^a-zA-Z0-9_-]+/', '-', $nodeKey);
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

            $html .= '<li class="site-menu__item'.($hasChildren ? ' site-menu__item--branch' : '').'"'.($hasChildren ? ' data-menu-branch' : '').'>';
            $html .= '<div class="site-menu__row"><a'.$attributes.'>'.$label.'</a>';

            if ($hasChildren) {
                $html .= '<button type="button" class="site-menu__submenu-toggle" data-menu-submenu-toggle aria-expanded="false" aria-controls="'.$submenuId.'" aria-label="Abrir submenú '.$label.'"><span aria-hidden="true">▾</span></button>';
            }

            $html .= '</div>';

            if ($hasChildren) {
                $childrenHtml = $renderItems($children, $depth + 1, $nodeKey);
                $html .= preg_replace('/^<ul /', '<ul id="'.$submenuId.'" ', $childrenHtml, 1);
            }

            $html .= '</li>';
        }

        return $html.'</ul>';
    };
@endphp
{!! $renderItems($items) !!}
