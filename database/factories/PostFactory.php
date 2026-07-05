<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Site;

class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->sentence(rand(4, 8));

        // Nos aseguramos de que el sitio de pruebas exista en la BD.
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

        return [
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title),
            'body' => "## " . $this->faker->sentence() . "\n\n" . $this->faker->paragraphs(rand(30,60), true),
            'keywords' => implode(', ', $this->faker->words(rand(1, 3))),
            'type' => $this->faker->randomElement(['Cuaderno', 'Ensayo', 'Fuente', 'Mapa']),
            'status' => 'published',
            'site_id' => $site->short_name,
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
