<?php

namespace App\Filament\Resources\Media\Tables;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Support\MediaUsageChecker;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaTable
{
    private const PLACEHOLDER_ICON = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%239ca3af\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z\'/%3E%3C/svg%3E';

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('model'))
            ->columns([
                ImageColumn::make('preview')
                    ->label('')
                    ->state(fn (Media $record): ?string => self::previewUrl($record))
                    ->defaultImageUrl(self::PLACEHOLDER_ICON)
                    ->imageSize(48)
                    ->square(),

                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('file_name')
                    ->label('Nombre')
                    ->searchable(['file_name', 'name'])
                    ->limit(40)
                    ->tooltip(fn (Media $record): string => $record->file_name),

                TextColumn::make('mime_type')
                    ->label('MIME')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('size')
                    ->label('Tamaño')
                    ->formatStateUsing(fn (Media $record): string => $record->human_readable_size)
                    ->sortable(),

                TextColumn::make('collection_name')
                    ->label('Colección'),

                TextColumn::make('owner')
                    ->label('Post propietario')
                    ->state(function (Media $record): string {
                        $post = $record->model;

                        return $post instanceof Post
                            ? "#{$post->getKey()} — {$post->title}"
                            : '—';
                    }),

                TextColumn::make('model_id')
                    ->label('ID del Post')
                    ->placeholder('—'),

                TextColumn::make('usage')
                    ->label('Estado')
                    ->state(fn (Media $record): string => MediaUsageChecker::isInUse($record) ? 'En uso' : 'Huérfano')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'En uso' ? 'success' : 'warning'),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('collection_name')
                    ->label('Colección')
                    ->options(fn (): array => Media::query()
                        ->distinct()
                        ->orderBy('collection_name')
                        ->pluck('collection_name', 'collection_name')
                        ->all()),

                SelectFilter::make('mime_type')
                    ->label('MIME')
                    ->options(fn (): array => Media::query()
                        ->distinct()
                        ->orderBy('mime_type')
                        ->pluck('mime_type', 'mime_type')
                        ->all()),

                SelectFilter::make('model_id')
                    ->label('Post')
                    ->options(fn (): array => Post::query()->orderBy('title')->pluck('title', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('model_type', Post::class)->where('model_id', $data['value'])
                        : $query)
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('openOriginal')
                    ->label('Original')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Media $record): string => $record->getUrl())
                    ->openUrlInNewTab(),

                Action::make('openPreview')
                    ->label('Preview')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->url(fn (Media $record): string => $record->getUrl('preview'))
                    ->openUrlInNewTab()
                    ->visible(fn (Media $record): bool => $record->hasGeneratedConversion('preview')),

                Action::make('goToPost')
                    ->label('Ir al Post')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->url(fn (Media $record): ?string => $record->model instanceof Post
                        ? PostResource::getUrl('edit', ['record' => $record->model])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Media $record): bool => $record->model instanceof Post),

                Action::make('delete')
                    ->label('Eliminar')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar medio')
                    ->modalDescription('Se eliminará el archivo original, sus conversiones y las imágenes responsive mediante Spatie Media Library. Esta acción no se puede deshacer.')
                    ->action(fn (Media $record) => self::attemptDelete($record)),
            ])
            ->bulkActions([
                BulkAction::make('deleteUnused')
                    ->label('Eliminar seleccionados')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Elimina cada medio seleccionado que no esté en uso dentro de un post. Los que estén en uso se omiten y se informan.')
                    ->action(function (Collection $records): void {
                        $deletedCount = 0;
                        $skipped = [];

                        foreach ($records as $record) {
                            $post = MediaUsageChecker::referencingPost($record);

                            if ($post) {
                                $skipped[] = "#{$record->getKey()} (post #{$post->getKey()} — {$post->title})";

                                continue;
                            }

                            $record->delete();
                            $deletedCount++;
                        }

                        Notification::make()
                            ->title("Medios eliminados: {$deletedCount}")
                            ->body(filled($skipped) ? 'Omitidos por estar en uso: '.implode(', ', $skipped) : null)
                            ->color(filled($skipped) ? 'warning' : 'success')
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function previewUrl(Media $record): ?string
    {
        if (! str_starts_with((string) $record->mime_type, 'image/')) {
            return null;
        }

        return $record->getAvailableUrl(['preview']);
    }

    private static function attemptDelete(Media $record): void
    {
        $post = MediaUsageChecker::referencingPost($record);

        if ($post) {
            Notification::make()
                ->title('No se puede eliminar')
                ->body("Este medio está en uso en el post #{$post->getKey()} — {$post->title}.")
                ->danger()
                ->actions([
                    Action::make('editPost')
                        ->label('Editar post')
                        ->url(PostResource::getUrl('edit', ['record' => $post]))
                        ->openUrlInNewTab(),
                ])
                ->send();

            return;
        }

        $record->delete();

        Notification::make()
            ->title('Medio eliminado')
            ->success()
            ->send();
    }
}
