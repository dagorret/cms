<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Support\Str;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
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

                     RichEditor::make('body'),

                     TextInput::make('keywords')
                     ->placeholder('ej: historia, mapas, siglo xix'),

                     Select::make('type')
                     ->options([
                         'notebook' => 'Cuaderno',
                         'essay' => 'Ensayo',
                         'source' => 'Fuente',
                         'map' => 'Mapa',
                     ])
                     ->default('notebook')
                     ->required(),

                     Select::make('status')
                     ->options([
                         'draft' => 'Borrador',
                         'published' => 'Publicado',
                     ])
                     ->default('draft')
                     ->required(),

                     \Filament\Forms\Components\Select::make('site_id')
                     ->relationship('site', 'long_name') // Busca la relación 'site' en tu Post y muestra el 'long_name'
                     ->preload()                          // Carga la lista rápido en memoria
                     ->searchable()                       // Te deja escribir para filtrar si tenés varios sitios
                     ->required()
                     ->label('Sitio Web'),

                     \Filament\Forms\Components\DateTimePicker::make('published_at')
                     ->default(now()) // ⚡ Te autopopula la fecha y hora actual al abrir el modal
                     ->label('Fecha de Publicación'),

                     DateTimePicker::make('static_built_at')
                     ->disabled(),
        ]);
    }
}
