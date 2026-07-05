<?php

namespace App\Models;

use App\Support\PostBodyRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Athphane\FilamentEditorjs\Traits\ModelHasEditorJsComponent;

class Post extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use ModelHasEditorJsComponent;

    // Agregá este array con los campos de tu tabla:
    protected $fillable = [
        'title',
        'slug',
        'body',
        'keywords',
        'type',
        'status',
        'site_id',
        'has_math',
        'published_at',
        'static_built_at',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id', 'short_name'); // O 'id', según lo que uses para vincularlos.
    }

    protected $casts = [
        'has_math' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function getBodyAttribute(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded)
            ? $decoded
            : $value;
    }

    public function setBodyAttribute(mixed $value): void
    {
        $this->attributes['body'] = is_array($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : $value;
    }

    public function renderedBodyHtml(): string
    {
        return PostBodyRenderer::render($this->body);
    }

    public function getExcerpt(int $words = 30): string
    {
        return PostBodyRenderer::excerpt($this->body, $words);
    }

    public function editorJsContentFieldName(): string
    {
        return 'body';
    }

    public function editorjsMediaCollectionName(): string
    {
        return 'body_images';
    }

    public function registerMediaCollections(): void
    {
        $this->registerEditorJsMediaCollections(
            mime_types: config('filament-editorjs.image_mime_types'),
            generate_responsive_images: true,
        );
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerEditorJsMediaConversions($media);
    }

    public function findAndDeleteRemovedEditorJsMedia(): void
    {
        $mediaIds = collect(data_get($this->body, 'blocks', []))
            ->filter(fn (array $block): bool => data_get($block, 'type') === 'image')
            ->map(fn (array $block): mixed => data_get($block, 'data.file.media_id'))
            ->filter()
            ->values()
            ->all();

        $this->media()
            ->where('collection_name', $this->editorjsMediaCollectionName())
            ->whereNotIn('id', $mediaIds)
            ->delete();
    }
}
