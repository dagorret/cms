<?php

declare(strict_types=1);

namespace App\Support;

use Athphane\FilamentEditorjs\Facades\FilamentEditorjs;
use Illuminate\Support\Str;
use JsonSerializable;
use Stringable;

final class PostBodyRenderer
{
    public static function render(mixed $content): string
    {
        $normalized = self::normalizeContent($content);

        if (self::isEditorJsPayload($normalized)) {
            return self::renderEditorJs($normalized);
        }

        if (is_string($normalized)) {
            return self::isHtml($normalized)
                ? $normalized
                : (string) Str::markdown($normalized);
        }

        return '';
    }

    public static function toPlainText(mixed $content, int $limit = 420): string
    {
        $text = self::plainText($content);

        return $limit > 0 ? Str::limit($text, $limit, '') : $text;
    }

    public static function excerpt(mixed $content, int $words = 30): string
    {
        $text = self::stripMath(self::plainText($content));

        return $words > 0 ? Str::words($text, $words, '') : $text;
    }

    public static function plainText(mixed $content): string
    {
        $text = html_entity_decode(strip_tags(self::render($content)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    public static function stripMath(string $text): string
    {
        $withoutBlocks = preg_replace('/\$\$[\s\S]*?\$\$/u', ' ', $text) ?? $text;
        $withoutInline = preg_replace('/(?<!\\\\)\$(?!\$)[^\r\n$]*(?<!\\\\)\$/u', ' ', $withoutBlocks) ?? $withoutBlocks;

        return trim(preg_replace('/\s+/', ' ', $withoutInline) ?? '');
    }

    protected static function normalizeContent(mixed $content): mixed
    {
        if ($content instanceof JsonSerializable) {
            return $content->jsonSerialize();
        }

        if ($content instanceof Stringable) {
            $content = (string) $content;
        }

        if (is_array($content)) {
            return $content;
        }

        if (! is_string($content)) {
            return '';
        }

        $trimmed = trim($content);

        if ($trimmed === '') {
            return '';
        }

        $decoded = self::decodeEditorJsJson($trimmed);

        if (is_array($decoded)) {
            return $decoded;
        }

        return $content;
    }

    protected static function isEditorJsPayload(mixed $content): bool
    {
        return is_array($content) && isset($content['blocks']) && is_array($content['blocks']);
    }

    protected static function isHtml(string $content): bool
    {
        return preg_match(
            '/<\s*(article|aside|blockquote|br|code|div|figcaption|figure|h[1-6]|hr|img|li|ol|p|pre|section|span|strong|table|tbody|td|th|thead|tr|ul|a)\b[^>]*>/i',
            trim($content),
        ) === 1;
    }

    protected static function decodeEditorJsJson(string $content): ?array
    {
        foreach ([$content, html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8')] as $candidate) {
            $decoded = json_decode($candidate, true);

            if (json_last_error() === JSON_ERROR_NONE && self::isEditorJsPayload($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    protected static function renderEditorJs(array $content): string
    {
        $root = FilamentEditorjs::getFacadeRoot();

        if (is_object($root) && method_exists($root, 'render')) {
            return (string) FilamentEditorjs::render($content);
        }

        return (string) FilamentEditorjs::renderContent($content);
    }
}
