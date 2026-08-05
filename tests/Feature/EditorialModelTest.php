<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use App\Support\LegacyPostTypeMigrator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Tests\TestCase;

class EditorialModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('static_cms.rebuild_on_publish', false);
    }

    public function test_type_solo_acepta_post_o_page(): void
    {
        $site = Site::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        Post::factory()->create(['site_id' => $site->short_name, 'type' => 'essay']);
    }

    public function test_post_pertenece_a_una_sola_categoria(): void
    {
        $site = Site::factory()->create();
        $category = Category::factory()->create(['site_id' => $site->id]);
        $post = Post::factory()->create([
            'site_id' => $site->short_name,
            'type' => Post::TYPE_POST,
            'category_id' => $category->id,
        ]);

        $this->assertTrue($post->category->is($category));
        $this->assertSame($category->id, $post->category_id);
    }

    public function test_page_limpia_su_categoria(): void
    {
        $site = Site::factory()->create();
        $category = Category::factory()->create(['site_id' => $site->id]);
        $page = Post::factory()->create([
            'site_id' => $site->short_name,
            'type' => Post::TYPE_PAGE,
            'category_id' => $category->id,
        ]);

        $this->assertNull($page->category_id);
    }

    public function test_categoria_debe_pertenecer_al_mismo_sitio_del_post(): void
    {
        $site = Site::factory()->create();
        $otherSite = Site::factory()->create();
        $category = Category::factory()->create(['site_id' => $otherSite->id]);

        $this->expectException(InvalidArgumentException::class);
        Post::factory()->create([
            'site_id' => $site->short_name,
            'category_id' => $category->id,
        ]);
    }

    public function test_categoria_padre_hija_y_ruta_completa(): void
    {
        $site = Site::factory()->create();
        $parent = Category::factory()->create(['site_id' => $site->id, 'name' => 'Historia', 'slug' => 'historia']);
        $child = Category::factory()->create([
            'site_id' => $site->id,
            'parent_id' => $parent->id,
            'name' => 'Siglo XIX',
            'slug' => 'siglo-xix',
        ]);

        $this->assertTrue($parent->isRoot());
        $this->assertTrue($child->parent->is($parent));
        $this->assertSame('Historia / Siglo XIX', $child->fullPath());
    }

    public function test_rechaza_ciclos_y_padres_de_otro_sitio(): void
    {
        $site = Site::factory()->create();
        $otherSite = Site::factory()->create();
        $parent = Category::factory()->create(['site_id' => $site->id]);
        $child = Category::factory()->create(['site_id' => $site->id, 'parent_id' => $parent->id]);

        try {
            $parent->update(['parent_id' => $child->id]);
            $this->fail('Se esperaba rechazo del ciclo jerárquico.');
        } catch (InvalidArgumentException) {
            $this->assertNull($parent->fresh()->parent_id);
        }

        $foreignParent = Category::factory()->create(['site_id' => $otherSite->id]);
        $this->expectException(InvalidArgumentException::class);
        $child->update(['parent_id' => $foreignParent->id]);
    }

    public function test_slug_es_unico_por_sitio_y_reutilizable_en_otro_sitio(): void
    {
        $site = Site::factory()->create();
        $otherSite = Site::factory()->create();
        Category::factory()->create(['site_id' => $site->id, 'slug' => 'historia']);
        Category::factory()->create(['site_id' => $otherSite->id, 'slug' => 'historia']);

        $this->expectException(QueryException::class);
        Category::factory()->create(['site_id' => $site->id, 'slug' => 'historia']);
    }

    public function test_migra_type_editorial_a_categoria_y_conserva_pages(): void
    {
        $site = Site::factory()->create();
        $base = [
            'site_id' => $site->short_name,
            'body' => null,
            'keywords' => null,
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => now(),
            'static_built_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $legacyId = DB::table('posts')->insertGetId($base + [
            'title' => 'Legado',
            'slug' => 'legado',
            'type' => 'Essay',
        ]);
        $pageId = DB::table('posts')->insertGetId($base + [
            'title' => 'Página',
            'slug' => 'pagina',
            'type' => 'page',
        ]);

        (new LegacyPostTypeMigrator)->migrate();

        $legacy = Post::query()->findOrFail($legacyId);
        $page = Post::query()->findOrFail($pageId);
        $this->assertSame(Post::TYPE_POST, $legacy->type);
        $this->assertSame('essay', $legacy->category->slug);
        $this->assertSame(Post::TYPE_PAGE, $page->type);
        $this->assertNull($page->category_id);
    }

    public function test_filament_separa_tipo_tecnico_y_categoria_sin_configuracion_editorial(): void
    {
        $source = File::get(app_path('Filament/Resources/Posts/Schemas/PostForm.php'));

        $this->assertStringContainsString("Select::make('type')", $source);
        $this->assertStringContainsString("Select::make('category_id')", $source);
        $this->assertStringContainsString('Category::hierarchicalOptions', $source);
        $this->assertStringNotContainsString('static_cms.types', $source);
        $this->assertArrayNotHasKey('types', config('static_cms'));
    }

    public function test_selector_jerarquico_filtra_por_sitio(): void
    {
        $site = Site::factory()->create();
        $otherSite = Site::factory()->create();
        $parent = Category::factory()->create(['site_id' => $site->id, 'name' => 'Historia']);
        $child = Category::factory()->create(['site_id' => $site->id, 'parent_id' => $parent->id, 'name' => 'Antigua']);
        $foreign = Category::factory()->create(['site_id' => $otherSite->id, 'name' => 'Ajena']);

        $options = Category::hierarchicalOptions($site->short_name);

        $this->assertSame('Historia', $options[(string) $parent->id]);
        $this->assertSame('— Antigua', $options[(string) $child->id]);
        $this->assertArrayNotHasKey((string) $foreign->id, $options);
    }
}
