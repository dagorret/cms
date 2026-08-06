<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->sentence(rand(4, 8));
        $markdown = '## '.$this->faker->sentence()."\n\n".$this->faker->paragraphs(rand(30, 60), true);

        return [
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title), // Esto se va a sobreescribir en el Seeder
            'body' => [
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
                // Coincide con la version que serializa Editor.js incluida por el paquete instalado.
                'version' => '2.28.2',
            ],
            'keywords' => implode(', ', $this->faker->words(rand(1, 3))),
            'type' => Post::TYPE_POST,
            'category_id' => null,
            'status' => 'published',
            'site_id' => 'ensayos',
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
