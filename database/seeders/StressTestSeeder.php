<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Post;
use App\Models\Site;
use App\Models\User; // 🔥 Importamos el modelo User
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class StressTestSeeder extends Seeder
{
    public function run(): void
    {
        // 🔑 1. Asegurar que exista el usuario administrador en el sistema
        $this->command->warn("👤 Verificando usuario administrador...");
        $user = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrador',
                'password' => bcrypt('123456'), // Poné la contraseña que uses siempre
            ]
        );
        $this->command->info("✅ Usuario listo: admin@admin.com");

        // 🌐 2. Asegurar que exista el Sitio de pruebas
        $site = Site::firstOrCreate(
            ['short_name' => 'ensayos'],
            [
                'long_name' => 'Bitácora de Ensayos Masivos',
                'slogan' => 'Laboratorio de pruebas de alta densidad',
                'meta_description' => 'Un sitio de pruebas volumétricas para el motor estático tipo NASA.',
                'domain' => 'https://ensayos.test',
                'subdir' => null,
            ]
        );

        $this->command->warn("🧹 Vaciando tabla posts...");
        Post::query()->delete(); 

        $faker = Faker::create('es_ES');
        $totalPosts = 300000;
        $batchSize = 500; 
        $chunks = $totalPosts / $batchSize;

        // 🔥 Control estricto del archivo de configuración
        $typesFromConfig = config('static_cms.types');

        if (empty($typesFromConfig) || !is_array($typesFromConfig)) {
            $this->command->error("🚨 Error crítico: No se encontraron los tipos en config/static_cms.php");
            throw new \Exception("Asegurate de correr 'php artisan config:clear' o revisar la sintaxis de config/static_cms.php");
        }

        $tiposReales = array_keys($typesFromConfig); 
        $totalTipos = count($tiposReales);

        $this->command->info("🏗️  Generando {$totalPosts} posts distribuidos en los " . implode(', ', $tiposReales) . "...");

        for ($i = 0; $i < $chunks; $i++) {
            $data = [];
            
            for ($j = 0; $j < $batchSize; $j++) {
                $globalIndex = ($i * $batchSize) + $j + 1;
                
                $pureText = rtrim($faker->sentence(rand(6, 12)), '.');
                $titulo = "Ensayo #{$globalIndex} - " . $pureText;
                
                $slugBase = Str::limit(Str::slug($pureText), 150, '');
                $slugUnico = "ensayo-{$globalIndex}-" . $slugBase;
                
                $cuerpoAleatorio = "## " . $faker->sentence() . "\n\n" . $faker->paragraphs(rand(20, 40), true);
                
                // Rotación uniforme entre los tipos reales configurados
                $tipoAsignado = $tiposReales[$globalIndex % $totalTipos];

                $data[] = [
                    'site_id'      => $site->short_name, 
                    'title'        => $titulo, 
                    'slug'         => $slugUnico, 
                    'body'         => $cuerpoAleatorio,
                    'type'         => $tipoAsignado,
                    'status'       => 'published',
                    'keywords'     => 'key-' . $globalIndex . ', ' . implode(', ', $faker->words(rand(1, 2))),
                    'published_at' => now(),
                    'created_at'   => $faker->dateTimeBetween('-1 year', 'now'),
                    'updated_at'   => now(),
                ];
            }

            Post::insert($data);
            $this->command->comment("✅ Bloque " . ($i + 1) . "/{$chunks} insertado...");
        }

        $this->command->info("🚀 ¡Stress test cargado con éxito, usuario creado y base de datos lista!");
    }
}
