<?php

namespace App\Services;

class StaticHtmlCleaner
{
    public static function clean(string $html): string
    {
        $protectedBlocks = [];

        $html = preg_replace_callback(
            '/<(pre|textarea|script|style)\b[^>]*>.*?<\/\1>/is',
            function (array $matches) use (&$protectedBlocks) {
                $key = '%%STATIC_HTML_BLOCK_' . count($protectedBlocks) . '%%';
                $protectedBlocks[$key] = $matches[0];

                return $key;
            },
            $html
        );

        // Preserve conditional comments while stripping ordinary HTML comments.
        $html = preg_replace('/<!--(?!\[if\b|\s*<!\[endif\]).*?-->/s', '', $html);

        // Collapse markup-only whitespace. Text nodes keep their authored spacing.
        $html = preg_replace('/>\s+</', '><', $html);
        $html = trim($html);

        return strtr($html, $protectedBlocks);
    }
}
