<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use Faker\Factory as Faker; // 🔥 Importamos el modelo User
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StressTestSeeder extends Seeder
{
    public function run(): void
    {
        // 🔑 1. Asegurar que exista el usuario administrador en el sistema
        $this->command->warn('👤 Verificando usuario administrador...');
        $user = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrador',
                'password' => bcrypt('123456'), // Poné la contraseña que uses siempre
            ]
        );
        $this->command->info('✅ Usuario listo: admin@admin.com');

        // 🌐 2. Asegurar que exista el Sitio de pruebas
        $site = Site::firstOrCreate(
            ['short_name' => 'ensayos'],
            [
                'long_name' => 'Bitácora de Ensayos Masivos',
                'slogan' => 'Laboratorio de pruebas de alta densidad',
                'meta_description' => 'Un sitio de pruebas volumétricas para el motor estático tipo NASA.',
                'domain' => 'https://ensayos.test',
                'subdir' => null,
                'dist_path' => base_path('dist/ensayos'),
            ]
        );

        $this->command->warn('🧹 Vaciando tabla posts...');
        Post::query()->delete();

        $faker = Faker::create('es_ES');
        $totalPosts = 300000;
        $batchSize = 500;
        $chunks = $totalPosts / $batchSize;

        $categories = collect(['Cuaderno', 'Ensayo', 'Fuente', 'Mapa', 'Conversación'])
            ->map(fn (string $name): Category => Category::firstOrCreate([
                'site_id' => $site->getKey(),
                'slug' => Str::slug($name),
            ], ['name' => $name]));
        $categoryIds = $categories->pluck('id')->values();
        $totalTipos = $categoryIds->count();

        $this->command->info("🏗️  Generando {$totalPosts} posts distribuidos en categorías dinámicas...");

        for ($i = 0; $i < $chunks; $i++) {
            $data = [];

            for ($j = 0; $j < $batchSize; $j++) {
                $globalIndex = ($i * $batchSize) + $j + 1;

                $pureText = rtrim($faker->sentence(rand(6, 12)), '.');
                $titulo = "Ensayo #{$globalIndex} - ".$pureText;

                $slugBase = Str::limit(Str::slug($pureText), 150, '');
                $slugUnico = "ensayo-{$globalIndex}-".$slugBase;

                $cuerpoAleatorio = '## '.$faker->sentence()."\n\n".$faker->paragraphs(rand(20, 40), true);

                // Rotación uniforme entre los tipos reales configurados
                $categoryId = $categoryIds[$globalIndex % $totalTipos];

                $data[] = [
                    'site_id' => $site->short_name,
                    'title' => $titulo,
                    'slug' => $slugUnico,
                    'body' => $cuerpoAleatorio,
                    'type' => Post::TYPE_POST,
                    'category_id' => $categoryId,
                    'status' => 'published',
                    'keywords' => 'key-'.$globalIndex.', '.implode(', ', $faker->words(rand(1, 2))),
                    'published_at' => now(),
                    'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                    'updated_at' => now(),
                ];
            }

            Post::insert($data);
            $this->command->comment('✅ Bloque '.($i + 1)."/{$chunks} insertado...");
        }

        $this->command->info('🚀 ¡Stress test cargado con éxito, usuario creado y base de datos lista!');
    }
}
