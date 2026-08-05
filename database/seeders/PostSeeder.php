<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        // Usuario administrador
        User::firstOrCreate(
            ['email' => 'dagorret@gmail.com'],
            [
                'name' => 'Carlos',
                'password' => Hash::make('123456'),
            ]
        );

        // Sitio de ejemplo
        $site = Site::firstOrCreate(
            ['short_name' => 'ensayos'],
            [
                'long_name' => 'Bitácora de Ensayos',
                'slogan' => 'Mis pensamientos en crudo',
                'meta_description' => 'Un blog estático optimizado para SEO.',
                'domain' => 'localhost',
                'subdir' => '/',
                'dist_path' => '/home/carlos/work/cms/dist',
            ]
        );

        // Crear 10 posts de ejemplo
        Post::factory()
            ->count(10)
            ->create([
                'site_id' => $site->getKey(),
            ])
            ->each(function (Post $post): void {
                $post->update([
                    'slug' => Str::slug(
                        "{$post->type}-{$post->id}-{$post->title}"
                    ),
                ]);
            });
    }
}
