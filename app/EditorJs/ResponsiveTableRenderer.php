<?php

declare(strict_types=1);

namespace App\EditorJs;

use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

final class ResponsiveTableRenderer implements NodeRendererInterface
{
    private TableRenderer $tableRenderer;

    public function __construct()
    {
        $this->tableRenderer = new TableRenderer;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        Table::assertInstanceOf($node);

        return new HtmlElement(
            'div',
            ['class' => 'table-wrapper', 'role' => 'region', 'aria-label' => 'Tabla desplazable'],
            $this->tableRenderer->render($node, $childRenderer),
        );
    }
}
