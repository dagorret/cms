<?php

namespace App\Filament\Resources\Sites\Tables;

use App\Models\Site;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class SitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('short_name')
                    ->searchable(),
                TextColumn::make('long_name')
                    ->searchable(),
                TextColumn::make('slogan')
                    ->searchable(),
                TextColumn::make('meta_description')
                    ->searchable(),
                TextColumn::make('domain')
                    ->searchable(),
                TextColumn::make('subdir')
                    ->searchable(),
                TextColumn::make('dist_path')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('lanzarOrquestador')
                    ->label('Lanzar')
                    ->icon(Heroicon::OutlinedRocketLaunch)
                    ->color('warning')
                    ->modalHeading('Lanzar Orquestador NASA')
                    ->modalSubmitActionLabel('Compilar')
                    ->schema([
                        Select::make('seccion')
                            ->label('Sección')
                            ->options([
                                'all' => 'Todo el sitio',
                                'posts' => 'Solo artículos',
                                'logo' => 'Logos y branding',
                            ])
                            ->required()
                            ->default('all'),
                    ])
                    ->action(function (Site $record, array $data): void {
                        try {
                            Artisan::queue('site:build', [
                                'site_id' => $record->getKey(),
                                '--scope' => (string) ($data['seccion'] ?? 'all'),
                            ]);

                            Notification::make()
                                ->title('Orquestador NASA lanzado')
                                ->body("Compilación encolada para {$record->short_name}. Sección: {$data['seccion']}.")
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title('Fallo al lanzar el orquestador')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
