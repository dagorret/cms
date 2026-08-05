<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Site;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('parent.name')->label('Padre')->placeholder('Raíz'),
                TextColumn::make('site.long_name')->label('Sitio')->sortable(),
                TextColumn::make('sort_order')->label('Orden')->sortable(),
                IconColumn::make('is_visible')->label('Visible')->boolean(),
                TextColumn::make('posts_count')->counts('posts')->label('Posts'),
            ])
            ->filters([
                SelectFilter::make('site_id')
                    ->label('Sitio')
                    ->options(Site::query()->pluck('long_name', 'id')->all()),
                SelectFilter::make('is_visible')
                    ->options([1 => 'Visible', 0 => 'Oculta']),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('sort_order');
    }
}
