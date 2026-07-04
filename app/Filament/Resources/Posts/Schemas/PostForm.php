<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\MarkdownEditor; 
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Support\Str;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        // ⚡ Determinamos qué editor renderizar según el archivo de configuración de forma estricta
        $editorComponent = config('static_cms.default_editor') === 'rich_editor'
            ? RichEditor::make('body')
            : MarkdownEditor::make('body');

        return $schema
        ->components([
            TextInput::make('title')
            ->required()
            ->maxLength(255)
            ->live(onBlur: true)
            ->afterStateUpdated(fn (string $operation, $state, callable $set) =>
            $operation === 'create' ? $set('slug', Str::slug($state)) : null
            ),

            TextInput::make('slug')
            ->disabled()
            ->dehydrated()
            ->required()
            ->unique(table: 'posts', ignoreRecord: true),

            $editorComponent, 

            TextInput::make('keywords')
            ->placeholder('ej: historia, mapas, siglo xix'),

            Select::make('type')
            ->options(config('static_cms.types', [])) // 🔥 Pura fidelidad: Si no está en el config, devuelve vacío.
            ->default('notebook')
            ->required(),

            Select::make('status')
            ->options([
                'draft' => 'Borrador',
                'published' => 'Publicado',
            ])
            ->default('draft')
            ->required(),

            Select::make('site_id')
            ->relationship('site', 'long_name') 
            ->preload()                          
            ->searchable()                       
            ->required()
            ->label('Sitio Web'),

            DateTimePicker::make('published_at')
            ->default(now()) 
            ->label('Fecha de Publicación'),

            DateTimePicker::make('static_built_at')
            ->disabled(),
        ]);
    }
}
