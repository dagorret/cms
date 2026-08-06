<?php

declare(strict_types=1);

namespace App\EditorJs;

use App\Support\MarkdownMathPreserver;
use Athphane\FilamentEditorjs\Renderers\BlockRenderer;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\MarkdownConverter;

final class MarkdownBlockRenderer extends BlockRenderer
{
    public function render(array $block, array $config = []): string
    {
        $source = data_get($block, 'data.source');

        if (! is_string($source) || $source === '') {
            return '';
        }

        $identifier = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($block['id'] ?? ''));
        $identifier = trim((string) $identifier, '-') ?: substr(hash('sha256', $source), 0, 12);
        $environment = new Environment([
            // Perfil confiable: el bloque sólo está disponible para administradores del CMS.
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'footnote' => [
                'ref_id_prefix' => "fnref-markdown-{$identifier}-",
                'footnote_id_prefix' => "fn-markdown-{$identifier}-",
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new FootnoteExtension);
        $environment->addRenderer(Table::class, new ResponsiveTableRenderer, 100);

        $math = MarkdownMathPreserver::extract($source);
        $html = (string) (new MarkdownConverter($environment))->convert($math['markdown']);

        return MarkdownMathPreserver::restore($html, $math['replacements']);
    }

    public function getType(): string
    {
        return 'markdown';
    }

    public function getWordCount(array $block): int
    {
        $source = data_get($block, 'data.source', '');

        return is_string($source) ? str_word_count(strip_tags($source)) : 0;
    }
}
