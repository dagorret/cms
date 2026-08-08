<?php

declare(strict_types=1);

namespace App\EditorJs;

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
                $url = $media->hasGeneratedConversion('preview')
                    ? $media->getUrl('preview')
                    : $media->getUrl();

                // Para el HTML estático nos interesa solamente el path.
                // http://127.0.0.1:8000/storage/15/...
                // pasa a:
                // /storage/15/...
                $path = parse_url($url, PHP_URL_PATH);

                if (is_string($path) && $path !== '') {
                    data_set($data, 'file.url', $path);
                }
            }
        }

        return view('filament-editorjs::renderers.image', $data)->render();
    }

    public function getType(): string
    {
        return 'image';
    }
}
