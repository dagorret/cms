<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Site;
use App\Models\Tag;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('static_cms.rebuild_on_publish', false);
    }

    public function test_crea_tags_y_expone_la_relacion_con_el_sitio(): void
    {
        $site = Site::factory()->create();
        $tag = Tag::create([
            'site_id' => $site->id,
            'name' => 'Inteligencia artificial',
            'slug' => 'inteligencia-artificial',
        ]);

        $this->assertTrue($tag->site->is($site));
        $this->assertTrue($site->tags->contains($tag));
        $this->assertSame($site->id, $tag->site_id);
    }

    public function test_post_y_tag_tienen_una_relacion_muchos_a_muchos(): void
    {
        $site = Site::factory()->create();
        $post = Post::factory()->create(['site_id' => $site->short_name]);
        $tag = Tag::create(['site_id' => $site->id, 'name' => 'Laravel', 'slug' => 'laravel']);

        $post->tags()->attach($tag);

        $this->assertTrue($post->fresh()->tags->contains($tag));
        $this->assertTrue($tag->fresh()->posts->contains($post));
    }

    public function test_las_relaciones_pivote_se_eliminan_en_cascada(): void
    {
        $site = Site::factory()->create();
        $post = Post::factory()->create(['site_id' => $site->short_name]);
        $tag = Tag::create(['site_id' => $site->id, 'name' => 'CMS', 'slug' => 'cms']);
        $post->tags()->attach($tag);

        $post->delete();

        $this->assertDatabaseMissing('post_tag', ['post_id' => $post->id, 'tag_id' => $tag->id]);
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);

        $otherPost = Post::factory()->create(['site_id' => $site->short_name]);
        $otherPost->tags()->attach($tag);
        $tag->delete();

        $this->assertDatabaseMissing('post_tag', ['post_id' => $otherPost->id, 'tag_id' => $tag->id]);

        $siteTag = Tag::create(['site_id' => $site->id, 'name' => 'Sitio', 'slug' => 'sitio']);
        $otherPost->tags()->attach($siteTag);
        $site->delete();

        $this->assertDatabaseMissing('tags', ['id' => $siteTag->id]);
        $this->assertDatabaseMissing('post_tag', ['post_id' => $otherPost->id, 'tag_id' => $siteTag->id]);
    }

    public function test_slug_es_unico_dentro_del_sitio_y_reutilizable_en_otro(): void
    {
        $site = Site::factory()->create();
        $otherSite = Site::factory()->create();
        Tag::create(['site_id' => $site->id, 'name' => 'PHP', 'slug' => 'php']);
        Tag::create(['site_id' => $otherSite->id, 'name' => 'PHP', 'slug' => 'php']);

        $this->expectException(QueryException::class);
        Tag::create(['site_id' => $site->id, 'name' => 'PHP duplicado', 'slug' => 'php']);
    }
}
