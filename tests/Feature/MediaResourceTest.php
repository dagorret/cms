<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Post;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class MediaResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('dash'));
    }

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    /**
     * Attaches a real uploaded image to the post's EditorJS media collection so
     * Spatie generates the original file and the "preview" conversion on disk.
     */
    private function attachImage(Post $post, string $name): Media
    {
        return $post
            ->addMedia(UploadedFile::fake()->image($name, 800, 600))
            ->toMediaCollection('body_images');
    }

    private function pointBodyToMedia(Post $post, Media $media): void
    {
        $body = [
            'time' => now()->getTimestampMs(),
            'version' => '2.28.2',
            'blocks' => [
                [
                    'id' => Str::random(10),
                    'type' => 'image',
                    'data' => [
                        'file' => ['media_id' => $media->id, 'url' => $media->getUrl()],
                        'caption' => '',
                    ],
                ],
            ],
        ];

        // Bypasses Post's "updating" model event (which prunes unreferenced EditorJS
        // media on every save) so we can deliberately set up a referenced/orphan pair.
        Post::query()->whereKey($post->getKey())->update([
            'body' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $post->refresh();
    }

    public function test_lista_los_medios_existentes(): void
    {
        $post = Post::factory()->create();
        $media = $this->attachImage($post, 'foto.png');

        Livewire::actingAs($this->actingUser())
            ->test(ListMedia::class)
            ->assertCanSeeTableRecords([$media]);
    }

    public function test_muestra_el_post_propietario_del_medio(): void
    {
        $post = Post::factory()->create(['title' => 'Un post con imagen']);
        $media = $this->attachImage($post, 'foto.png');

        Livewire::actingAs($this->actingUser())
            ->test(ListMedia::class)
            ->assertTableColumnStateSet('owner', "#{$post->getKey()} — Un post con imagen", $media)
            ->assertTableColumnStateSet('model_id', (string) $post->getKey(), $media);
    }

    public function test_el_preview_usa_la_conversion_preview_de_spatie(): void
    {
        $post = Post::factory()->create();
        $media = $this->attachImage($post, 'foto.png');

        $this->assertTrue($media->hasGeneratedConversion('preview'));
        $this->assertSame($media->getUrl('preview'), $media->getAvailableUrl(['preview']));

        Livewire::actingAs($this->actingUser())
            ->test(ListMedia::class)
            ->assertTableColumnStateSet('preview', $media->getUrl('preview'), $media);
    }

    public function test_un_medio_referenciado_por_editorjs_no_puede_eliminarse(): void
    {
        $post = Post::factory()->create(['title' => 'Post protegido']);
        $media = $this->attachImage($post, 'usada.png');
        $this->pointBodyToMedia($post, $media);

        Livewire::actingAs($this->actingUser())
            ->test(ListMedia::class)
            ->callTableAction('delete', $media)
            ->assertNotified('No se puede eliminar');

        $this->assertModelExists($media);
    }

    public function test_un_medio_no_referenciado_puede_eliminarse(): void
    {
        $post = Post::factory()->create();
        $usedMedia = $this->attachImage($post, 'usada.png');
        $orphanMedia = $this->attachImage($post, 'huerfana.png');
        $this->pointBodyToMedia($post, $usedMedia);

        Livewire::actingAs($this->actingUser())
            ->test(ListMedia::class)
            ->callTableAction('delete', $orphanMedia)
            ->assertNotified('Medio eliminado');

        $this->assertModelMissing($orphanMedia);
    }

    public function test_al_eliminar_un_medio_desaparecen_sus_archivos_y_conversiones(): void
    {
        $post = Post::factory()->create();
        $media = $this->attachImage($post, 'huerfana.png');

        $originalPath = $media->getPath();
        $previewPath = $media->getPath('preview');

        Storage::disk('public')->assertExists(str_replace(Storage::disk('public')->path(''), '', $originalPath));
        Storage::disk('public')->assertExists(str_replace(Storage::disk('public')->path(''), '', $previewPath));

        Livewire::actingAs($this->actingUser())
            ->test(ListMedia::class)
            ->callTableAction('delete', $media);

        $this->assertModelMissing($media);
        $this->assertFileDoesNotExist($originalPath);
        $this->assertFileDoesNotExist($previewPath);
    }
}
