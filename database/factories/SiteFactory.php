<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SiteFactory extends Factory
{
    public function definition(): array
    {
        $shortName = Str::slug($this->faker->unique()->words(2, true));

        return [
            'short_name' => $shortName,
            'long_name' => Str::title(str_replace('-', ' ', $shortName)),
            'slogan' => $this->faker->sentence(),
            'meta_description' => $this->faker->sentence(),
            'domain' => "https://{$shortName}.example.test",
            'subdir' => null,
            'dist_path' => storage_path("framework/testing/{$shortName}"),
        ];
    }
}
