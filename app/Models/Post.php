<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    // Agregá este array con los campos de tu tabla:
    protected $fillable = [
        'title',
        'slug',
        'body',
        'keywords',
        'type',
        'status',
        'site_id',
        'published_at',
        'static_built_at',
    ];
}
