<?php

namespace Database\Seeders;

use App\Models\Site;
use App\Models\Post;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {

        
        // Tu código de creación de usuario
        User::factory()->create([
            'name' => 'Carlos',
            'email' => 'dagorret@gmail.com',
            'password' => bcrypt('123456'),
        ]);

        // Creamos el sitio UNA SOLA VEZ antes del bucle
        $site = Site::firstOrCreate( 
            ['short_name' => 'ensayos'], 
            [
                'long_name' => 'Bitácora de Ensayos',
                'slogan' => 'Mis pensamientos en crudo',
                'meta_description' => 'Un blog estático optimizado para SEO.',
                'domain' => 'https://tudominio.com',
                'subdir' => 'blog'
            ]
        );

        // Ahora ejecutamos el bucle pasándole el site_id ya existente
        for ($i = 0; $i < 0; $i++) {
            
            // Pasamos el site_id explícitamente para que el factory no adivine ni busque nada
            $post = Post::factory()->create([
                'site_id' => $site->short_name
            ]);

            $estructuraSlug = "{$post->type} - {$post->id} - {$post->title}";

            $post->update([
                'slug' => Str::slug($estructuraSlug)
            ]);
        }
    }
}





