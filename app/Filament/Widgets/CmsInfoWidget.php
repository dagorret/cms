<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class CmsInfoWidget extends Widget
{
    protected static ?int $sort = -2;

    protected static bool $isLazy = false;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.cms-info-widget';
}
