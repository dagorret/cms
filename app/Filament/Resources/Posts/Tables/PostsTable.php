<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Filament\Actions\StaticBuildAction;
use App\Models\Post;
use App\Models\Site;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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

                // Tipo técnico, separado de la categoría editorial.
                TextColumn::make('type')
                    ->label('Tipo técnico')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === Post::TYPE_PAGE ? 'Página' : 'Post')
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->placeholder('Sin categoría')
                    ->searchable()
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
                SelectFilter::make('type')
                    ->label('Tipo técnico')
                    ->options([
                        Post::TYPE_POST => 'Post',
                        Post::TYPE_PAGE => 'Página',
                    ]),
                SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('site_id')
                    ->label('Sitio')
                    ->options(Site::query()->pluck('long_name', 'short_name')->all()),
                SelectFilter::make('status')
                    ->options([
                        Post::STATUS_DRAFT => 'Borrador',
                        Post::STATUS_PUBLISHED => 'Publicado',
                        Post::STATUS_SCHEDULED => 'Programado',
                    ]),
            ])
            ->recordActions([
                StaticBuildAction::make('compile')->label('Compilar'),
                EditAction::make(),
            ]);
    }
}
