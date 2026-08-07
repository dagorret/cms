<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class DashPanelBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('dash'));
    }

    public function test_el_panel_usa_el_branding_propio_del_cms(): void
    {
        $this->assertSame('CMS Faro', Filament::getPanel('dash')->getBrandName());
    }

    public function test_el_dashboard_muestra_el_widget_propio_y_no_el_de_filament(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('CMS Faro')
            ->assertSee('GitHub')
            ->assertSeeHtml('https://github.com/dagorret/cms')
            ->assertDontSeeHtml('https://github.com/filamentphp/filament');
    }
}
