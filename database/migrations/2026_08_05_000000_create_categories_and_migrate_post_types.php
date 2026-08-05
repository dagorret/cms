<?php

declare(strict_types=1);

use App\Support\LegacyPostTypeMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->index(['site_id', 'parent_id', 'sort_order']);
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->string('type')->default('post')->change();
            $table->foreignId('category_id')
                ->nullable()
                ->after('site_id')
                ->constrained('categories')
                ->nullOnDelete();
        });

        (new LegacyPostTypeMigrator)->migrate();
    }

    public function down(): void
    {
        if (Schema::hasTable('categories') && Schema::hasColumn('posts', 'category_id')) {
            DB::table('posts')
                ->where('type', 'post')
                ->whereNotNull('category_id')
                ->orderBy('id')
                ->chunkById(1000, function ($posts): void {
                    foreach ($posts as $post) {
                        $categorySlug = DB::table('categories')->where('id', $post->category_id)->value('slug');

                        if ($categorySlug) {
                            DB::table('posts')->where('id', $post->id)->update(['type' => $categorySlug]);
                        }
                    }
                });
        }

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
            $table->string('type')->default('post')->change();
        });

        Schema::dropIfExists('categories');
    }
};
