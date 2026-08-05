<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'parent_id' => 'integer',
        'sort_order' => 'integer',
        'is_visible' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            $category->slug = Str::slug((string) ($category->slug ?: $category->name));

            if ($category->slug === '') {
                throw new InvalidArgumentException('La categoría debe tener un slug válido.');
            }

            if ($category->exists && $category->isDirty('site_id') && $category->posts()->exists()) {
                throw new InvalidArgumentException('No se puede mover de sitio una categoría que contiene posts.');
            }

            if (! $category->parent_id) {
                return;
            }

            if ($category->exists && (int) $category->parent_id === (int) $category->getKey()) {
                throw new InvalidArgumentException('Una categoría no puede ser su propio padre.');
            }

            $parent = static::query()->find($category->parent_id);

            if (! $parent || (int) $parent->site_id !== (int) $category->site_id) {
                throw new InvalidArgumentException('La categoría padre debe pertenecer al mismo sitio.');
            }

            $visited = [];

            while ($parent) {
                if (isset($visited[$parent->getKey()])) {
                    throw new InvalidArgumentException('La jerarquía de categorías contiene un ciclo.');
                }

                $visited[$parent->getKey()] = true;

                if ($category->exists && (int) $parent->getKey() === (int) $category->getKey()) {
                    throw new InvalidArgumentException('Una categoría no puede depender de una de sus descendientes.');
                }

                $parent = $parent->parent_id ? static::query()->find($parent->parent_id) : null;
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function ancestors(): Collection
    {
        $ancestors = new Collection;
        $current = $this->relationLoaded('parent') ? $this->parent : $this->parent()->first();
        $visited = [];

        while ($current && ! isset($visited[$current->getKey()])) {
            $visited[$current->getKey()] = true;
            $ancestors->prepend($current);
            $current = $current->parent_id ? $current->parent()->first() : null;
        }

        return $ancestors;
    }

    public function fullPath(string $separator = ' / '): string
    {
        return $this->ancestors()->push($this)->pluck('name')->join($separator);
    }

    public function descendantIds(): array
    {
        $categories = static::query()->where('site_id', $this->site_id)->get(['id', 'parent_id']);
        $descendants = [];
        $pending = [(int) $this->getKey()];

        while ($pending !== []) {
            $parentId = array_shift($pending);

            foreach ($categories->where('parent_id', $parentId) as $child) {
                $childId = (int) $child->getKey();

                if (! in_array($childId, $descendants, true)) {
                    $descendants[] = $childId;
                    $pending[] = $childId;
                }
            }
        }

        return $descendants;
    }

    public static function hierarchicalOptions(int|string|null $siteToken, array $excludedIds = []): array
    {
        if (! filled($siteToken)) {
            return [];
        }

        $site = Site::query()
            ->whereKey($siteToken)
            ->orWhere('short_name', $siteToken)
            ->first();

        if (! $site) {
            return [];
        }

        $categories = static::query()
            ->where('site_id', $site->getKey())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name']);
        $options = [];
        $appendChildren = function (?int $parentId, int $depth) use (&$appendChildren, $categories, $excludedIds, &$options): void {
            foreach ($categories->where('parent_id', $parentId) as $category) {
                if (! in_array((int) $category->getKey(), $excludedIds, true)) {
                    $options[(string) $category->getKey()] = str_repeat('— ', $depth).$category->name;
                }

                $appendChildren((int) $category->getKey(), $depth + 1);
            }
        };

        $appendChildren(null, 0);

        return $options;
    }
}
