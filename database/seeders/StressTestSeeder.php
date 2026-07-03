<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class StressTestSeeder extends Seeder
{
    public function run()
    {
        // 1. Buscamos o creamos el sitio con sus datos obligatorios
        $site = Site::firstOrCreate(
            ['short_name' => 'ensayos'],
            [
                'long_name' => 'Bitácora de Ensayos Masivos',
                'slogan' => 'Laboratorio de pruebas de alta densidad',
                'meta_description' => 'Un sitio de pruebas volumétricas para estresar el constructor.',
                'domain' => 'https://ensayos.test',
                'subdir' => null,
            ]
        );

        // 2. Limpiamos la tabla posts para el test limpio
        $this->command->warn("🧹 Limpiando datos viejos de la tabla posts...");
        Post::query()->delete(); 

        $faker = Faker::create('es_ES');

        $totalPosts = 30000;
        $batchSize = 500; 
        $chunks = $totalPosts / $batchSize;

        // Tus 5 categorías reales
        $categorias Reales = ['ensayos', 'cuadernos', 'mapas', 'fuentes', 'conversaciones'];

        $this->command->info("🏗️  Generando {$totalPosts} posts distribuidos en tus 5 categorías...");

        for ($i = 0; $i < $chunks; $i++) {
            $data = [];
            
            for ($j = 0; $j < $batchSize; $j++) {
                $globalIndex = ($i * $batchSize) + $j + 1;
                
                $pureText = rtrim($faker->sentence(rand(6, 12)), '.');
                $titulo = "Ensayo #{$globalIndex} - " . $pureText;
                $slugUnico = "ensayo-{$globalIndex}-" . Str::slug($pureText);
                $cuerpoAleatorio = "## " . $faker->sentence() . "\n\n" . $faker->paragraphs(rand(20, 40), true);
                
                // Rotamos entre tus 5 categorías de forma pareja
                $categoriaAsignada = $categoriasReales[$globalIndex % 5];

                $data[] = [
                    'site_id' => $site->short_name, 
                    'title' => $titulo, 
                    'slug' => $slugUnico, 
                    'body' => $cuerpoAleatorio,
                    'type' => 'post',        // 🔥 Tipo estructural correcto
                    'category' => $categoriaAsignada, // 🔥 Tu taxonomía real
                    'status' => 'published', // 🔥 Publicado de entrada
                    'keywords' => implode(', ', $faker->words(rand(1, 3))),
                    'published_at' => now(),
                    'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                    'updated_at' => now(),
                ];
            }

            Post::insert($data);
            $this->command->comment("✅ Bloque " . ($i + 1) . "/{$chunks} insertado...");
        }

        $this->command->info("🚀 ¡30,000 posts listos y categorizados con éxito!");
    }
}
