<?php

namespace Database\Seeders;

use App\Models\Category;
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

        $categories = collect([
            ['name' => 'Cuaderno', 'slug' => 'cuaderno'],
            ['name' => 'Ensayo', 'slug' => 'ensayo'],
            ['name' => 'Fuente', 'slug' => 'fuente'],
            ['name' => 'Mapa', 'slug' => 'mapa'],
            ['name' => 'Conversación', 'slug' => 'conversacion'],
        ])->map(fn (array $category): Category => Category::firstOrCreate(
            ['site_id' => $site->getKey(), 'slug' => $category['slug']],
            ['name' => $category['name']],
        ));

        Post::factory()
            ->count(10)
            ->sequence(fn ($sequence): array => [
                'site_id' => $site->short_name,
                'type' => Post::TYPE_POST,
                'category_id' => $categories[$sequence->index % $categories->count()]->getKey(),
            ])
            ->create()
            ->each(function (Post $post): void {
                $post->update([
                    'slug' => Str::slug(
                        "{$post->category->slug}-{$post->id}-{$post->title}"
                    ),
                ]);
            });
    }
}
