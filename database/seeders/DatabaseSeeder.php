<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Post; // <--- ¡ESTE IMPORT ES EL QUE FALTA!

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Tu código de creación de usuario
        User::factory()->create([
            'name' => 'Carlos',
            'email' => 'dagorret@gmail.com',
            'password' => bcrypt('123456'),
        ]);

        // Ahora sí, tus 100 ensayos de prueba con el factory
        Post::factory(100)->create();
    }
}
