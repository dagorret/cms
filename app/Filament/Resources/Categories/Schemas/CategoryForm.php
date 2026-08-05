<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('site_id')
                ->relationship('site', 'long_name')
                ->live()
                ->afterStateUpdated(fn (Set $set): mixed => $set('parent_id', null))
                ->required()
                ->searchable()
                ->preload(),
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (mixed $state, Set $set): mixed => $set('slug', Str::slug((string) $state))),
            TextInput::make('slug')
                ->required()
                ->maxLength(255),
            Select::make('parent_id')
                ->label('Categoría padre')
                ->options(function (Get $get, ?Category $record): array {
                    $excluded = $record ? [(int) $record->getKey(), ...$record->descendantIds()] : [];

                    return Category::hierarchicalOptions($get('site_id'), $excluded);
                })
                ->searchable()
                ->preload()
                ->nullable(),
            Textarea::make('description')->columnSpanFull(),
            TextInput::make('sort_order')->numeric()->default(0)->minValue(0)->required(),
            Toggle::make('is_visible')->label('Visible')->default(true),
        ]);
    }
}
