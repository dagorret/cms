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

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    /**
     * Subdirectorio público (ej: "/blog"), o cadena vacía si el sitio vive en la raíz.
     * "dist" se trata como raíz por convención heredada del compilador estático.
     */
    public function publicPath(): string
    {
        $path = trim((string) $this->subdir, '/');

        return ($path === '' || $path === 'dist') ? '' : '/'.$path;
    }

    /**
     * Esquema + host público, SIN subdir. NUNCA App::url()/APP_URL: ese valor
     * puede apuntar al CMS administrativo, no al sitio generado.
     */
    public function publicOrigin(): string
    {
        $domain = rtrim(trim((string) $this->domain), '/');

        if (! preg_match('#^https?://#i', $domain)) {
            $host = strtolower((string) strtok($domain, '/'));
            $scheme = ($host === 'localhost' || str_starts_with($host, '127.') || str_starts_with($host, '['))
                ? 'http://'
                : 'https://';
            $domain = $scheme.$domain;
        }

        return $domain;
    }

    /**
     * Dominio público absoluto incluyendo subdir (esquema + host + subdir).
     */
    public function publicBaseUrl(): string
    {
        return $this->publicOrigin().$this->publicPath();
    }

    /**
     * URL pública absoluta para un path relativo del sitio generado. Sin argumento
     * devuelve la home. Siempre termina en "/" (convención de las plantillas estáticas).
     */
    public function publicUrl(string $path = ''): string
    {
        $base = rtrim($this->publicBaseUrl(), '/');
        $path = trim($path, '/');

        return $path === '' ? $base.'/' : $base.'/'.$path.'/';
    }
}
