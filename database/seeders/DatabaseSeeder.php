<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Llamamos explícitamente a tu seeder corregido
        $this->call(PostSeeder::class);
    }
}
