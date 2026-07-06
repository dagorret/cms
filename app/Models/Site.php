<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    protected $fillable = [
        'short_name',
        'long_name',
        'slogan',
        'meta_description',
        'domain',
        'subdir',
        'dist_path',
    ];

    // De paso ya dejamos declarada la relación: Un sitio tiene muchos posts
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
