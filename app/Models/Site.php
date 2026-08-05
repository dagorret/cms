<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'short_name',
        'long_name',
        'slogan',
        'meta_description',
        'domain',
        'subdir',
        'dist_path',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'site_id', 'short_name');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }
}
