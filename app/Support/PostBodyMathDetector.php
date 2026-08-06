<?php

declare(strict_types=1);

namespace App\Support;

use JsonSerializable;
use Stringable;

final class PostBodyMathDetector
{
    public static function containsMath(mixed $content): bool
    {
        if ($content instanceof JsonSerializable) {
            $content = $content->jsonSerialize();
        }

        if ($content instanceof Stringable) {
            $content = (string) $content;
        }

        if (is_string($content)) {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return self::containsMath($decoded);
            }

            return self::sourceContainsMath($content);
        }

        if (! is_array($content)) {
            return false;
        }

        if (isset($content['blocks']) && is_array($content['blocks'])) {
            foreach ($content['blocks'] as $block) {
                if (! is_array($block) || ($block['type'] ?? null) === 'code') {
                    continue;
                }

                if (($block['type'] ?? null) === 'markdown') {
                    $source = data_get($block, 'data.source');
                    if (is_string($source) && self::sourceContainsMath($source)) {
                        return true;
                    }

                    continue;
                }

                if (self::containsMath($block['data'] ?? [])) {
                    return true;
                }
            }

            return false;
        }

        foreach ($content as $value) {
            if (self::containsMath($value)) {
                return true;
            }
        }

        return false;
    }

    private static function sourceContainsMath(string $source): bool
    {
        $withoutFencedCode = preg_replace('/^(```|~~~).*?^\1\s*$/ms', '', $source) ?? $source;

        if (preg_match('/(?<!\\\\)\$\$[\s\S]*?\S[\s\S]*?(?<!\\\\)\$\$/u', $withoutFencedCode) === 1) {
            return true;
        }

        if (preg_match('/\\\\\[[\s\S]*?\S[\s\S]*?\\\\\]/u', $withoutFencedCode) === 1) {
            return true;
        }

        if (preg_match('/\\\\\([^\r\n]*?\S[^\r\n]*?\\\\\)/u', $withoutFencedCode) === 1) {
            return true;
        }

        return preg_match(
            '/(?<!\\\\)\$(?!\$|\s)(?=[^$\r\n]*(?:[A-Za-z\\\\_^><=+*\/\-]))[^$\r\n]+?(?<!\s|\\\\)\$(?!\$)/u',
            $withoutFencedCode,
        ) === 1;
    }
}
