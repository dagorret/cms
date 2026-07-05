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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // <--- ¡Esta es la que te está reclamando!
            $table->string('slug')->unique();
            $table->text('body')->nullable();
            $table->string('keywords')->nullable();
            $table->string('type')->default('notebook');
            $table->string('status')->default('draft');
            $table->string('site_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('static_built_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
