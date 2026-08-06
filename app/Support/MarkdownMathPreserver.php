<?php

declare(strict_types=1);

namespace App\Support;

final class MarkdownMathPreserver
{
    /**
     * @return array{markdown: string, replacements: array<string, string>}
     */
    public static function extract(string $markdown): array
    {
        $prefix = 'FAROMATH'.strtoupper(substr(hash('sha256', $markdown), 0, 16)).'TOKEN';

        while (str_contains($markdown, $prefix)) {
            $prefix .= 'X';
        }

        $protected = '';
        $replacements = [];
        $length = strlen($markdown);

        for ($offset = 0; $offset < $length;) {
            if (self::isLineStart($markdown, $offset)) {
                $codeEnd = self::fencedCodeEnd($markdown, $offset);

                if ($codeEnd !== null) {
                    $protected .= substr($markdown, $offset, $codeEnd - $offset);
                    $offset = $codeEnd;

                    continue;
                }

                $lineEnd = self::indentedCodeLineEnd($markdown, $offset);

                if ($lineEnd !== null) {
                    $protected .= substr($markdown, $offset, $lineEnd - $offset);
                    $offset = $lineEnd;

                    continue;
                }
            }

            if ($markdown[$offset] === '`') {
                $codeEnd = self::inlineCodeEnd($markdown, $offset);

                if ($codeEnd !== null) {
                    $protected .= substr($markdown, $offset, $codeEnd - $offset);
                    $offset = $codeEnd;

                    continue;
                }
            }

            $mathEnd = self::mathEnd($markdown, $offset);

            if ($mathEnd !== null) {
                $math = substr($markdown, $offset, $mathEnd - $offset);
                $placeholder = $prefix.count($replacements).'END';
                $replacements[$placeholder] = $math;
                $protected .= $placeholder;
                $offset = $mathEnd;

                continue;
            }

            $protected .= $markdown[$offset];
            $offset++;
        }

        return ['markdown' => $protected, 'replacements' => $replacements];
    }

    /** @param array<string, string> $replacements */
    public static function restore(string $html, array $replacements): string
    {
        return $replacements === [] ? $html : strtr($html, $replacements);
    }

    private static function mathEnd(string $markdown, int $offset): ?int
    {
        if (self::startsUnescaped($markdown, '$$', $offset)) {
            return self::closingDelimiterEnd($markdown, '$$', $offset + 2);
        }

        if (self::startsUnescaped($markdown, '\\[', $offset)) {
            return self::closingDelimiterEnd($markdown, '\\]', $offset + 2);
        }

        if (self::startsUnescaped($markdown, '\\(', $offset)) {
            return self::closingDelimiterEnd($markdown, '\\)', $offset + 2);
        }

        if (
            $markdown[$offset] === '$'
            && ! self::isEscaped($markdown, $offset)
            && ($markdown[$offset + 1] ?? '') !== '$'
            && isset($markdown[$offset + 1])
            && ! ctype_space($markdown[$offset + 1])
        ) {
            $length = strlen($markdown);

            for ($candidate = $offset + 1; $candidate < $length; $candidate++) {
                if ($markdown[$candidate] === "\n" || $markdown[$candidate] === "\r") {
                    return null;
                }

                if (
                    $markdown[$candidate] === '$'
                    && ! self::isEscaped($markdown, $candidate)
                    && $markdown[$candidate - 1] !== '$'
                    && ! ctype_space($markdown[$candidate - 1])
                ) {
                    return $candidate + 1;
                }
            }
        }

        return null;
    }

    private static function startsUnescaped(string $markdown, string $delimiter, int $offset): bool
    {
        return substr_compare($markdown, $delimiter, $offset, strlen($delimiter)) === 0
            && ! self::isEscaped($markdown, $offset);
    }

    private static function closingDelimiterEnd(string $markdown, string $delimiter, int $offset): ?int
    {
        while (($closing = strpos($markdown, $delimiter, $offset)) !== false) {
            if (! self::isEscaped($markdown, $closing)) {
                return $closing + strlen($delimiter);
            }

            $offset = $closing + 1;
        }

        return null;
    }

    private static function isEscaped(string $markdown, int $offset): bool
    {
        $slashes = 0;

        for ($index = $offset - 1; $index >= 0 && $markdown[$index] === '\\'; $index--) {
            $slashes++;
        }

        return $slashes % 2 === 1;
    }

    private static function isLineStart(string $markdown, int $offset): bool
    {
        return $offset === 0 || $markdown[$offset - 1] === "\n" || $markdown[$offset - 1] === "\r";
    }

    private static function fencedCodeEnd(string $markdown, int $offset): ?int
    {
        if (preg_match('/\G {0,3}(`{3,}|~{3,})[^\r\n]*(?:\r\n|\n|\r|$)/A', $markdown, $opening, 0, $offset) !== 1) {
            return null;
        }

        $fence = $opening[1];
        $searchOffset = $offset + strlen($opening[0]);
        $pattern = '/^ {0,3}'.preg_quote($fence[0], '/').'{'.strlen($fence).',}[ \t]*(?:\r\n|\n|\r|$)/m';

        if (preg_match($pattern, $markdown, $closing, PREG_OFFSET_CAPTURE, $searchOffset) !== 1) {
            return strlen($markdown);
        }

        return $closing[0][1] + strlen($closing[0][0]);
    }

    private static function indentedCodeLineEnd(string $markdown, int $offset): ?int
    {
        if (substr_compare($markdown, '    ', $offset, 4) !== 0 && ($markdown[$offset] ?? '') !== "\t") {
            return null;
        }

        $newline = strpos($markdown, "\n", $offset);

        return $newline === false ? strlen($markdown) : $newline + 1;
    }

    private static function inlineCodeEnd(string $markdown, int $offset): ?int
    {
        $length = strlen($markdown);
        $runLength = strspn($markdown, '`', $offset);

        for ($candidate = $offset + $runLength; $candidate < $length;) {
            $candidate = strpos($markdown, '`', $candidate);

            if ($candidate === false) {
                return null;
            }

            $candidateLength = strspn($markdown, '`', $candidate);

            if ($candidateLength === $runLength) {
                return $candidate + $candidateLength;
            }

            $candidate += $candidateLength;
        }

        return null;
    }
}
