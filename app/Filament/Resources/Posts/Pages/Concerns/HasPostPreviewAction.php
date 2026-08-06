<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Pages\Concerns;

use Filament\Actions\Action;
use Illuminate\Support\Js;

trait HasPostPreviewAction
{
    /**
     * @param  array<int, mixed>  $actions
     * @return array<int, mixed>
     */
    protected function addPostPreviewAction(array $actions): array
    {
        array_splice($actions, 1, 0, [$this->getPostPreviewAction()]);

        return $actions;
    }

    protected function getPostPreviewAction(): Action
    {
        $endpoint = route('filament.dash.post-preview.store');

        return Action::make('postPreview')
            ->label('Vista previa')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->alpineClickHandler('window.FaroPostPreview.open($wire, '.(string) Js::from($endpoint).')');
    }
}
