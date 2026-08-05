<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class MenuItem extends Model
{
    public const TYPES = [
        'category' => 'Categoría',
        'post' => 'Post',
        'page' => 'Página',
        'internal_url' => 'URL interna',
        'external_url' => 'URL externa',
    ];

    protected $fillable = [
        'menu_id', 'parent_id', 'label', 'type', 'category_id', 'post_id',
        'url', 'target', 'rel', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'menu_id' => 'integer',
        'parent_id' => 'integer',
        'category_id' => 'integer',
        'post_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (MenuItem $item): void {
            if (! array_key_exists((string) $item->type, self::TYPES)) {
                throw new InvalidArgumentException('El tipo de ítem de menú no es válido.');
            }

            $menu = Menu::query()->find($item->menu_id);

            if (! $menu) {
                throw new InvalidArgumentException('El ítem debe pertenecer a un menú existente.');
            }

            $item->validateDestination($menu);
            $item->validateHierarchy($menu);

            if (! in_array($item->target, ['_self', '_blank'], true)) {
                throw new InvalidArgumentException('El target del ítem no es válido.');
            }

            if ($item->type === 'external_url' && $item->target === '_blank') {
                $relations = collect(preg_split('/\s+/', trim((string) $item->rel)))
                    ->filter()
                    ->merge(['noopener', 'noreferrer'])
                    ->unique()
                    ->join(' ');
                $item->rel = $relations;
            }
        });
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function resolvedUrl(string $publicPath = ''): string
    {
        $prefix = rtrim($publicPath, '/');

        return match ($this->type) {
            'category' => $prefix.'/category/'.$this->category?->slug.'/',
            'post', 'page' => $prefix.'/'.$this->post?->slug.'/',
            'internal_url' => $prefix.($this->url === '/' ? '/' : '/'.ltrim((string) $this->url, '/')),
            'external_url' => (string) $this->url,
            default => '#',
        };
    }

    private function validateDestination(Menu $menu): void
    {
        if ($this->type === 'category') {
            $category = Category::query()->find($this->category_id);

            if (! $category || (int) $category->site_id !== (int) $menu->site_id) {
                throw new InvalidArgumentException('La categoría del ítem debe pertenecer al mismo sitio.');
            }

            $this->post_id = null;
            $this->url = null;
        } elseif (in_array($this->type, ['post', 'page'], true)) {
            $post = Post::query()->find($this->post_id);
            $site = $post?->site;

            if (! $post || ! $site || (int) $site->getKey() !== (int) $menu->site_id || $post->type !== $this->type) {
                throw new InvalidArgumentException('La entrada del ítem debe pertenecer al mismo sitio y coincidir con su tipo.');
            }

            $this->category_id = null;
            $this->url = null;
        } elseif ($this->type === 'internal_url') {
            if (! str_starts_with((string) $this->url, '/') || str_starts_with((string) $this->url, '//')) {
                throw new InvalidArgumentException('Una URL interna debe comenzar con una única barra.');
            }

            $this->category_id = null;
            $this->post_id = null;
        } elseif ($this->type === 'external_url') {
            if (filter_var($this->url, FILTER_VALIDATE_URL) === false || parse_url((string) $this->url, PHP_URL_SCHEME) !== 'https') {
                throw new InvalidArgumentException('Una URL externa debe utilizar HTTPS.');
            }

            $this->category_id = null;
            $this->post_id = null;
        }
    }

    private function validateHierarchy(Menu $menu): void
    {
        if (! $this->parent_id) {
            return;
        }

        $parent = self::query()->find($this->parent_id);
        $depth = 1;
        $visited = [];

        while ($parent) {
            if ((int) $parent->menu_id !== (int) $menu->getKey()
                || ($this->exists && (int) $parent->getKey() === (int) $this->getKey())
                || isset($visited[$parent->getKey()])) {
                throw new InvalidArgumentException('La jerarquía del menú contiene un ciclo o cruza menús.');
            }

            $visited[$parent->getKey()] = true;
            $depth++;
            $parent = $parent->parent_id ? self::query()->find($parent->parent_id) : null;
        }

        if ($depth > max((int) config('static_cms.menu_max_depth', 3), 1)) {
            throw new InvalidArgumentException('El ítem supera la profundidad máxima configurada.');
        }
    }
}
