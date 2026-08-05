<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Post;
use App\Models\Site;
use App\Services\MenuRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MenuSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_renderiza_una_jerarquia_y_todos_los_destinos_sin_n_mas_uno(): void
    {
        config()->set('static_cms.rebuild_on_publish', false);
        $site = Site::factory()->create();
        $category = Category::factory()->create(['site_id' => $site->id, 'name' => 'Ensayos', 'slug' => 'ensayos']);
        $post = Post::factory()->create(['site_id' => $site->short_name, 'slug' => 'un-post', 'type' => Post::TYPE_POST]);
        $page = Post::factory()->create(['site_id' => $site->short_name, 'slug' => 'sobre', 'type' => Post::TYPE_PAGE]);
        $menu = Menu::query()->create(['site_id' => $site->id, 'name' => 'Principal', 'location' => 'primary', 'is_active' => true]);

        $parent = MenuItem::query()->create([
            'menu_id' => $menu->id, 'label' => 'Ensayos', 'type' => 'category',
            'category_id' => $category->id, 'target' => '_self', 'sort_order' => 1,
        ]);
        MenuItem::query()->create([
            'menu_id' => $menu->id, 'parent_id' => $parent->id, 'label' => 'Un post',
            'type' => 'post', 'post_id' => $post->id, 'target' => '_self', 'sort_order' => 1,
        ]);
        MenuItem::query()->create([
            'menu_id' => $menu->id, 'label' => 'Sobre', 'type' => 'page',
            'post_id' => $page->id, 'target' => '_self', 'sort_order' => 2,
        ]);
        MenuItem::query()->create([
            'menu_id' => $menu->id, 'label' => 'Archivo', 'type' => 'internal_url',
            'url' => '/archive/', 'target' => '_self', 'sort_order' => 3,
        ]);
        $external = MenuItem::query()->create([
            'menu_id' => $menu->id, 'label' => '<GitHub>', 'type' => 'external_url',
            'url' => 'https://github.com/example', 'target' => '_blank', 'sort_order' => 4,
        ]);

        $structure = (new MenuRenderer)->structure($site, 'primary');
        $html = (new MenuRenderer)->render($site, 'primary');
        $categoryHtml = (new MenuRenderer)->renderStructure($structure, '/category/ensayos/page/2/');

        $this->assertSame('/category/ensayos/', $structure[0]['url']);
        $this->assertSame('/data/categories/ensayos/page-1.json', $structure[0]['json_url']);
        $this->assertSame('/un-post/', $structure[0]['children'][0]['url']);
        $this->assertSame('/sobre/', $structure[1]['url']);
        $this->assertSame('/archive/', $structure[2]['url']);
        $this->assertSame('noopener noreferrer', $external->fresh()->rel);
        $this->assertStringContainsString('<ul', $html);
        $this->assertStringContainsString('data-category="ensayos"', $html);
        $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('&lt;GitHub&gt;', $html);
        $this->assertStringNotContainsString('<GitHub>', $html);
        $this->assertStringContainsString('href="/category/ensayos/" aria-current="page"', $categoryHtml);
    }

    public function test_impide_ciclos_cruces_de_sitio_y_mas_de_tres_niveles(): void
    {
        config()->set('static_cms.rebuild_on_publish', false);
        config()->set('static_cms.menu_max_depth', 3);
        $site = Site::factory()->create();
        $other = Site::factory()->create();
        $foreignCategory = Category::factory()->create(['site_id' => $other->id]);
        $menu = Menu::query()->create(['site_id' => $site->id, 'name' => 'Principal', 'location' => 'primary']);
        $root = MenuItem::query()->create(['menu_id' => $menu->id, 'label' => 'Uno', 'type' => 'internal_url', 'url' => '/', 'target' => '_self']);
        $second = MenuItem::query()->create(['menu_id' => $menu->id, 'parent_id' => $root->id, 'label' => 'Dos', 'type' => 'internal_url', 'url' => '/dos/', 'target' => '_self']);
        $third = MenuItem::query()->create(['menu_id' => $menu->id, 'parent_id' => $second->id, 'label' => 'Tres', 'type' => 'internal_url', 'url' => '/tres/', 'target' => '_self']);

        try {
            MenuItem::query()->create(['menu_id' => $menu->id, 'parent_id' => $third->id, 'label' => 'Cuatro', 'type' => 'internal_url', 'url' => '/cuatro/', 'target' => '_self']);
            $this->fail('Se aceptó un cuarto nivel.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('profundidad', $exception->getMessage());
        }

        try {
            $root->update(['parent_id' => $third->id]);
            $this->fail('Se aceptó un ciclo.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('ciclo', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        MenuItem::query()->create([
            'menu_id' => $menu->id, 'label' => 'Ajena', 'type' => 'category',
            'category_id' => $foreignCategory->id, 'target' => '_self',
        ]);
    }

    public function test_solo_un_menu_activo_ocupa_cada_posicion_del_sitio(): void
    {
        config()->set('static_cms.rebuild_on_publish', false);
        $site = Site::factory()->create();
        $first = Menu::query()->create(['site_id' => $site->id, 'name' => 'Primero', 'location' => 'primary', 'is_active' => true]);
        $second = Menu::query()->create(['site_id' => $site->id, 'name' => 'Segundo', 'location' => 'primary', 'is_active' => true]);

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertCount(6, config('static_cms.menu_locations'));
    }
}
