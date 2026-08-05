<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Models\Category;
use App\Models\Post;
use Athphane\FilamentEditorjs\Forms\Components\EditorjsTextField;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        // ⚡ Determinamos qué editor renderizar según el archivo de configuración de forma estricta
        $editorType = config('static_cms.default_editor');

        $editorComponent = match ($editorType) {
            'editorjs' => EditorjsTextField::make('body')
                ->placeholder('Empezá a escribir tu obra maestra en bloques...'),

            'rich_editor' => RichEditor::make('body')
                ->fileAttachmentsDisk('public')
                ->fileAttachmentsDirectory(self::resolveMediaDirectory())
                ->fileAttachmentsVisibility('public')
                ->getFileAttachmentUrlUsing(fn (mixed $file): ?string => filled($file) ? '/'.trim((string) $file, '/') : null),

            default => MarkdownEditor::make('body')
                ->toolbarButtons([
                    'attachFiles', 'blockquote', 'bold', 'bulletList', 'codeBlock',
                    'heading', 'italic', 'link', 'orderedList', 'redo', 'strike',
                    'table', 'undo',
                ])
                ->fileAttachmentsDisk('public')
                ->fileAttachmentsDirectory(self::resolveMediaDirectory())
                ->getFileAttachmentUrlUsing(fn (mixed $file): ?string => filled($file) ? '/'.trim((string) $file, '/') : null),
        };

        $editorComponent->columnSpanFull();

        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(
                        fn (string $operation, mixed $state, Set $set): mixed => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null
                    ),

                Hidden::make('slug_locked')
                    ->default(true)
                    ->dehydrated(false),

                TextInput::make('slug')
                    ->disabled(fn (string $operation, Get $get): bool => $operation !== 'create' && (bool) $get('slug_locked'))
                    ->dehydrated()
                    ->required()
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn (?string $state): string => Post::normalizeSlug((string) $state))
                    ->unique(table: 'posts', ignoreRecord: true)
                    ->suffixAction(
                        Action::make('editarSlug')
                            ->label('Editar Slug')
                            ->icon('heroicon-o-pencil-square')
                            ->visible(fn (string $operation, Get $get): bool => $operation !== 'create' && (bool) $get('slug_locked'))
                            ->action(function (Set $set): void {
                                $set('slug_locked', false);
                            })
                    ),

                $editorComponent,

                TextInput::make('keywords')
                    ->placeholder('ej: historia, mapas, siglo xix'),

                Select::make('site_id')
                    ->relationship('site', 'long_name')
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('category_id', null))
                    ->preload()
                    ->searchable()
                    ->required()
                    ->label('Sitio Web'),

                Select::make('type')
                    ->label('Tipo técnico')
                    ->options([
                        Post::TYPE_POST => 'Post',
                        Post::TYPE_PAGE => 'Página',
                    ])
                    ->default(Post::TYPE_POST)
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        if ($state === Post::TYPE_PAGE) {
                            $set('category_id', null);
                        }
                    })
                    ->required(),

                Select::make('category_id')
                    ->label('Categoría editorial')
                    ->options(fn (Get $get): array => Category::hierarchicalOptions($get('site_id')))
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->disabled(fn (Get $get): bool => $get('type') === Post::TYPE_PAGE),

                Select::make('status')
                    ->options([
                        Post::STATUS_DRAFT => 'Borrador',
                        Post::STATUS_PUBLISHED => 'Publicado',
                        Post::STATUS_SCHEDULED => 'Programado',
                    ])
                    ->default(Post::STATUS_DRAFT)
                    ->required(),

                Toggle::make('has_math')
                    ->label('Contiene formulas matematicas')
                    ->helperText('Activa el post-procesado KaTeX solo para este articulo.')
                    ->default(false),

                DateTimePicker::make('published_at')
                    ->default(now())
                    ->label('Fecha de Publicación'),

                DateTimePicker::make('static_built_at')
                    ->disabled(),
            ]);
    }

    protected static function resolveMediaDirectory(): string
    {
        $basePath = trim((string) config('static_cms.media.base_path'), '/');
        $subfolder = trim((string) config('static_cms.media.subfolder'), '/');
        $dateFormat = trim((string) config('static_cms.media.date_format'));
        $datedPath = $dateFormat !== '' ? date($dateFormat) : null;

        $segments = array_filter(
            [$basePath, $subfolder, $datedPath],
            static fn (?string $segment): bool => $segment !== null && trim($segment, '/') !== '',
        );

        return implode('/', $segments) ?: 'assets/media';
    }
}
