<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Support\MediaReferenceResolver;
use Athphane\FilamentEditorjs\Forms\Components\EditorjsTextField;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class FaroEditorjsTextField extends EditorjsTextField
{
    #[ExposedLivewireMethod]
    #[Renderless]
    public function handleUploadedAttachmentUrlRetrieval(mixed $file): ?string
    {
        $media = Media::query()->where('uuid', $file)->first() ?? Media::query()->find($file);

        if (! $media) {
            return null;
        }

        $conversion = $media->hasGeneratedConversion('preview') ? 'preview' : '';

        return json_encode([
            'url' => app(MediaReferenceResolver::class)->canonicalUrl($media, $conversion),
            'id' => $media->getKey(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
