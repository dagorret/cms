<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    // ⚡ Redirige directo a la tabla al terminar de editar con éxito
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
