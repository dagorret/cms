<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Site;
use App\Support\StaticBuildProcess;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Throwable;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('launchNasa')
                ->label('Lanzar Orquestador NASA')
                ->icon(Heroicon::OutlinedCommandLine)
                ->modalHeading('Lanzar Orquestador NASA')
                ->modalSubmitActionLabel('Compilar')
                ->schema([
                    Select::make('site_id')
                        ->label('Sitio')
                        ->options(fn (): array => Site::query()
                            ->orderBy('long_name')
                            ->pluck('long_name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('target')
                        ->label('Seccion')
                        ->options([
                            'all' => 'Todo el Sitio',
                            'posts' => 'Solo Articulos',
                            'logo' => 'Logos y Branding',
                        ])
                        ->default('all')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $result = StaticBuildProcess::runSite(
                            (string) $data['site_id'],
                            (string) ($data['target'] ?? 'all'),
                        );

                        Notification::make()
                            ->title($result->successful() ? 'Orquestador finalizado' : 'Fallo el orquestador')
                            ->body(StaticBuildProcess::summary($result))
                            ->{$result->successful() ? 'success' : 'danger'}()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Fallo el orquestador')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            CreateAction::make(),
        ];
    }
}
