<?php

namespace App\Filament\Resources\Menus\RelationManagers;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Post;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        $site = $this->getOwnerRecord()->site;

        return $schema->components([
            TextInput::make('label')->required()->maxLength(255),
            Select::make('type')->options(MenuItem::TYPES)->required()->live(),
            Select::make('parent_id')
                ->label('Ítem padre')
                ->options(fn (?MenuItem $record): array => $this->getOwnerRecord()->items()
                    ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                    ->pluck('label', 'id')->all())
                ->nullable()->searchable(),
            Select::make('category_id')->label('Categoría')
                ->options(Category::query()->where('site_id', $site->getKey())->pluck('name', 'id')->all())
                ->visible(fn (Get $get): bool => $get('type') === 'category')->required(fn (Get $get): bool => $get('type') === 'category'),
            Select::make('post_id')
                ->label(fn (Get $get): string => $get('type') === 'page' ? 'Página' : 'Post')
                ->options(fn (Get $get): array => $site->posts()
                    ->where('type', $get('type') === 'page' ? Post::TYPE_PAGE : Post::TYPE_POST)
                    ->pluck('title', 'id')->all())
                ->visible(fn (Get $get): bool => in_array($get('type'), ['post', 'page'], true))
                ->required(fn (Get $get): bool => in_array($get('type'), ['post', 'page'], true)),
            TextInput::make('url')->maxLength(2048)
                ->visible(fn (Get $get): bool => in_array($get('type'), ['internal_url', 'external_url'], true))
                ->required(fn (Get $get): bool => in_array($get('type'), ['internal_url', 'external_url'], true)),
            Select::make('target')->options(['_self' => 'Misma ventana', '_blank' => 'Nueva ventana'])->default('_self')->required(),
            TextInput::make('rel')->maxLength(255),
            TextInput::make('sort_order')->numeric()->minValue(0)->default(0)->required(),
            Toggle::make('is_active')->label('Activo')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('parent.label')->label('Padre')->placeholder('Raíz'),
                TextColumn::make('sort_order')->label('Orden'),
                IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
