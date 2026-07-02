<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Post extends Model
{
    use HasFactory;

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

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id', 'short_name'); // O 'id', según lo que uses para vincularlos.
    }
}
