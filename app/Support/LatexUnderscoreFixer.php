<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Corrige guiones bajos escapados (\_) dentro de expresiones LaTeX ($..$, $$..$$, \(..\), \[..\])
 * sin tocar el resto del contenido. Reutiliza el mismo detector de expresiones matemáticas que ya
 * usan PostBodyRenderer y MarkdownBlockRenderer para no duplicar el parser.
 */
final class LatexUnderscoreFixer
{
    public const TYPE_EDITORJS = 'editorjs';

    public const TYPE_MARKDOWN = 'markdown';

    /**
     * @return array{raw: string, count: int, type: string}
     */
    public static function fixRawBody(string $raw): array
    {
        $decoded = json_decode($raw, true);

        if (
            json_last_error() === JSON_ERROR_NONE
            && is_array($decoded)
            && isset($decoded['blocks'])
            && is_array($decoded['blocks'])
        ) {
            return self::fixEditorJsPayload($raw, $decoded);
        }

        [$fixed, $count] = self::fixMathUnderscores($raw);

        return ['raw' => $fixed, 'count' => $count, 'type' => self::TYPE_MARKDOWN];
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{raw: string, count: int, type: string}
     */
    private static function fixEditorJsPayload(string $raw, array $decoded): array
    {
        $count = 0;

        foreach ($decoded['blocks'] as &$block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'markdown') {
                continue;
            }

            $source = data_get($block, 'data.source');

            if (! is_string($source) || $source === '') {
                continue;
            }

            [$fixed, $blockCount] = self::fixMathUnderscores($source);

            if ($blockCount > 0) {
                $block['data']['source'] = $fixed;
                $count += $blockCount;
            }
        }
        unset($block);

        if ($count === 0) {
            return ['raw' => $raw, 'count' => 0, 'type' => self::TYPE_EDITORJS];
        }

        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return ['raw' => $encoded === false ? $raw : $encoded, 'count' => $count, 'type' => self::TYPE_EDITORJS];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function fixMathUnderscores(string $text): array
    {
        $extracted = MarkdownMathPreserver::extract($text);
        $count = 0;
        $replacements = [];

        foreach ($extracted['replacements'] as $placeholder => $math) {
            $occurrences = substr_count($math, '\\_');
            $replacements[$placeholder] = $occurrences > 0 ? str_replace('\\_', '_', $math) : $math;
            $count += $occurrences;
        }

        if ($count === 0) {
            return [$text, 0];
        }

        return [MarkdownMathPreserver::restore($extracted['markdown'], $replacements), $count];
    }
}
