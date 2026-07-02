<?php

namespace App\Filament\Resources\Posts\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

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
        ]);
    }
}
