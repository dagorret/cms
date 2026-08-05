<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Filament\Actions\StaticBuildAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                StaticBuildAction::make('compile')->label('Compilar'),
                EditAction::make(),
            ]);
    }
}
