<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('short_name')->unique();    // Clave interna (ej: 'ensayos')
            $table->string('long_name');               // Título principal (ej: 'Bitácora de Ensayos')
            $table->string('slogan')->nullable();      // La bajada humana visible en la Home
            $table->string('meta_description', 160)->nullable(); // SEO estricto para Google
            $table->string('domain');                  // Dominio completo (ej: https://carlos.dev)
            $table->string('subdir')->nullable();      // Por si usás /blog, sino null
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
