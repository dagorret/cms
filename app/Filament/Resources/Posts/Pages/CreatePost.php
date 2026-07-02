<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    // ⚡ Redirige directo a la tabla al terminar de crear
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
