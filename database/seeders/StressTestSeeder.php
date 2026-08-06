<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StressTestSeeder extends Seeder
{
    public function run(): void
    {
        // Ejecutar únicamente mediante:
        // php artisan db:seed --class=StressTestSeeder

        $this->command->warn('👤 Verificando usuario administrador...');

        User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrador',
                'password' => bcrypt('123456'),
            ]
        );

        $this->command->info('✅ Usuario listo.');

        // Sitio de pruebas
        $site = Site::firstOrCreate(
            ['short_name' => 'ensayos'],
            [
                'long_name' => 'Bitácora de Ensayos Masivos',
                'slogan' => 'Laboratorio de pruebas de alta densidad',
                'meta_description' => 'Sitio de pruebas de rendimiento FARO CMS.',
                'domain' => 'localhost',
                'subdir' => '/',
                'dist_path' => 'dist',
            ]
        );

        $this->command->warn('🧹 Eliminando posts existentes...');

        Post::query()->delete();

        $faker = Faker::create('es_ES');

        // ==========================================
        // Configuración
        // ==========================================

        $totalPosts = 300000;
        $batchSize = 500;

        $chunks = (int) ceil($totalPosts / $batchSize);

        $this->command->info(
            "🏗️ Generando {$totalPosts} posts en {$chunks} bloques de {$batchSize}..."
        );

        // ==========================================
        // Categorías
        // ==========================================

        $categories = collect([
            'Cuaderno',
            'Ensayo',
            'Fuente',
            'Mapa',
            'Conversación',
        ])->map(
            fn (string $name): Category => Category::firstOrCreate(
                [
                    'site_id' => $site->getKey(),
                    'slug' => Str::slug($name),
                ],
                [
                    'name' => $name,
                ]
            )
        );

        $categoryIds = $categories->pluck('id')->values();

        $totalCategories = $categoryIds->count();

        // ==========================================
        // Inserción masiva
        // ==========================================

        for ($i = 0; $i < $chunks; $i++) {

            $rows = [];

            for ($j = 0; $j < $batchSize; $j++) {

                $index = ($i * $batchSize) + $j + 1;

                if ($index > $totalPosts) {
                    break;
                }

                $sentence = rtrim(
                    $faker->sentence(random_int(6, 12)),
                    '.'
                );

                $title = "Ensayo #{$index} - {$sentence}";

                $slug = 'ensayo-'
                    .$index.'-'
                    .Str::limit(Str::slug($sentence), 150, '');

                $markdown =
                    '## '.$faker->sentence()
                    ."\n\n"
                    .$faker->paragraphs(random_int(20, 40), true);

                $categoryId = $categoryIds[$index % $totalCategories];

                $rows[] = [

                    'site_id' => $site->short_name,

                    'title' => $title,

                    'slug' => $slug,

                    // Documento Editor.js compatible con FARO V2
                    'body' => json_encode([
                        'time' => now()->getTimestampMs(),
                        'blocks' => [
                            [
                                'id' => Str::random(10),
                                'type' => 'markdown',
                                'data' => [
                                    'source' => $markdown,
                                ],
                            ],
                        ],
                        'version' => '2.28.2',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),

                    'type' => Post::TYPE_POST,

                    'category_id' => $categoryId,

                    'status' => Post::STATUS_PUBLISHED,

                    'keywords' => implode(
                        ', ',
                        $faker->words(random_int(2, 4))
                    ),

                    'published_at' => now(),

                    'created_at' => $faker->dateTimeBetween('-1 year', 'now'),

                    'updated_at' => now(),
                ];
            }

            Post::insert($rows);

            $this->command->comment(
                sprintf(
                    '✅ Bloque %d/%d (%d registros)',
                    $i + 1,
                    $chunks,
                    min(($i + 1) * $batchSize, $totalPosts)
                )
            );
        }

        $this->command->newLine();
        $this->command->info('==========================================');
        $this->command->info('🚀 Stress Test finalizado');
        $this->command->info("Posts generados : {$totalPosts}");
        $this->command->info("Batch size      : {$batchSize}");
        $this->command->info("Bloques         : {$chunks}");
        $this->command->info('==========================================');
    }
}
