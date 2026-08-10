<?php

declare(strict_types=1);

namespace App\EditorJs;

use App\Support\MediaReferenceResolver;
use Athphane\FilamentEditorjs\Renderers\BlockRenderer;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class ImageBlockRenderer extends BlockRenderer
{
    public function render(array $block, array $config = []): string
    {
        $data = $block['data'] ?? [];
        $mediaId = data_get($data, 'file.media_id');

        if ($mediaId) {
            $media = Media::find($mediaId);

            if ($media) {
                $conversion = $media->hasGeneratedConversion('preview') ? 'preview' : '';
                data_set($data, 'file.url', app(MediaReferenceResolver::class)->staticUrl($media, $conversion));
            }
        } elseif (is_string($url = data_get($data, 'file.url'))) {
            $normalized = app(MediaReferenceResolver::class)->normalizeUrl($url);

            if ($normalized !== null) {
                data_set($data, 'file.url', $normalized['url']);
            }
        }

        return view('filament-editorjs::renderers.image', $data)->render();
    }

    public function getType(): string
    {
        return 'image';
    }
}
