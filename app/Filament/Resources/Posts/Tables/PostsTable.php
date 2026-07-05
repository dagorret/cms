<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Models\Post;
use App\Support\StaticBuildProcess;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->columns([
            // 1. Mostramos el título
            TextColumn::make('title')
            ->label('Título')
            ->searchable()
            ->sortable(),

                  // 2. El tipo (Cuaderno, etc.)
                  TextColumn::make('type')
                  ->label('Tipo')
                  ->sortable(),

                  // 3. El estado (Borrador, etc.) con formato badge
                  TextColumn::make('status')
                  ->label('Estado')
                  ->badge()
                  ->color(fn (string $state): string => match ($state) {
                      'draft' => 'gray',
                      'published' => 'success',
                      default => 'warning',
                  }),
        ])
        ->filters([
            // Filtros vacíos por ahora
        ])
        ->recordActions([
            Action::make('compile')
                ->label('Compilar')
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->requiresConfirmation()
                ->modalHeading('Compilar articulo')
                ->modalDescription('Regenera solamente el HTML estatico de este articulo.')
                ->action(function (Post $record): void {
                    try {
                        $result = StaticBuildProcess::runPost($record);

                        Notification::make()
                            ->title($result->successful() ? 'Articulo compilado' : 'Fallo la compilacion')
                            ->body(StaticBuildProcess::summary($result))
                            ->{$result->successful() ? 'success' : 'danger'}()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Fallo la compilacion')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            EditAction::make(),
        ]);
    }
}
