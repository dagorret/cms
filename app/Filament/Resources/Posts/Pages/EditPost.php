<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Support\StaticBuildQueue;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function afterSave(): void
    {
        $this->queueStaticBuild();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    // ⚡ Redirige directo a la tabla al terminar de editar con éxito
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function queueStaticBuild(): void
    {
        if (! config('static_cms.rebuild_on_publish')) {
            return;
        }

        try {
            $queued = StaticBuildQueue::queuePost($this->record);

            Notification::make()
                ->title($queued ? 'Compilacion estatica encolada' : 'No se pudo resolver el sitio del post')
                ->{$queued ? 'success' : 'warning'}()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('No se pudo encolar la compilacion')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }
}
