<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Filament\Actions\StaticBuildAction;
use App\Filament\Resources\Sites\SiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSites extends ListRecords
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            StaticBuildAction::make('launchNasa'),
            CreateAction::make(),
        ];
    }
}
