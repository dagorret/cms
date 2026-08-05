<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Menu extends Model
{
    protected $fillable = ['site_id', 'name', 'slug', 'location', 'is_active'];

    protected $casts = [
        'site_id' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Menu $menu): void {
            $menu->slug = Str::slug((string) ($menu->slug ?: $menu->name));

            if ($menu->slug === '') {
                throw new InvalidArgumentException('El menú debe tener un slug válido.');
            }

            if (! array_key_exists((string) $menu->location, config('static_cms.menu_locations', []))) {
                throw new InvalidArgumentException('La posición seleccionada no está configurada.');
            }
        });

        static::saved(function (Menu $menu): void {
            if ($menu->is_active) {
                static::query()
                    ->where('site_id', $menu->site_id)
                    ->where('location', $menu->location)
                    ->whereKeyNot($menu->getKey())
                    ->update(['is_active' => false]);
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
