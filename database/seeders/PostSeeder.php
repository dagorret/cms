<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Site;
use App\Models\Post;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear el usuario administrador único
        User::factory()->create([
            'name' => 'Carlos',
            'email' => 'dagorret@gmail.com',
            'password' => bcrypt('123456'),
        ]);

        // 2. Crear el sitio usando el mapeo exacto que probamos para Nginx
        $site = Site::firstOrCreate(  
            ['short_name' => 'ensayos'],  
            [
                'long_name' => 'Bitácora de Ensayos',
                'slogan' => 'Mis pensamientos en crudo',
                'meta_description' => 'Un blog estático optimizado para SEO.',
                'domain' => 'localhost',
                'subdir' => '/', // Barra limpia para que juegue directo con el root
                'dist_path' => '/home/carlos/work/cms/dist', // Tu ruta absoluta final
            ]
        );

        // 3. El bucle corregido: creamos 10 posts de verdad ($i < 10)
        for ($i = 0; $i < 10; $i++) {
            
            // Pasamos la clave numérica real de la base de datos
            $post = Post::factory()->create([
                'site_id' => $site->getKey() // Usa el ID numérico (ej: 1), no el short_name
            ]);

            $estructuraSlug = "{$post->type} - {$post->id} - {$post->title}";

            $post->update([
                'slug' => Str::slug($estructuraSlug)
            ]);
        }
    }
}
