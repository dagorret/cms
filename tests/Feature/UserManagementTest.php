<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('dash'));
    }

    public function test_usuario_autenticado_puede_acceder_al_panel(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Filament::getPanel('dash')->hasProfile());
        $this->assertSame(EditProfile::class, Filament::getPanel('dash')->getProfilePage());
        $this->assertSame('Mi perfil', EditProfile::getLabel());

        $this->actingAs($user)
            ->get(UserResource::getUrl('index'))
            ->assertOk();
    }

    public function test_usuario_no_autenticado_es_enviado_al_login(): void
    {
        $this->get(UserResource::getUrl('index'))
            ->assertRedirect(route('filament.dash.auth.login'));
    }

    public function test_usuario_autenticado_puede_crear_otro_usuario_con_password_hasheado(): void
    {
        $actor = User::factory()->create();

        Livewire::actingAs($actor)
            ->test(CreateUser::class)
            ->set('data', [
                'name' => 'Nueva Usuaria',
                'email' => 'nueva@example.test',
                'password' => 'Clave-Segura-123!',
                'password_confirmation' => 'Clave-Segura-123!',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::query()->where('email', 'nueva@example.test')->firstOrFail();

        $this->assertNotSame('Clave-Segura-123!', $created->password);
        $this->assertTrue(Hash::check('Clave-Segura-123!', $created->password));
    }

    public function test_email_de_usuario_debe_ser_unico(): void
    {
        $actor = User::factory()->create(['email' => 'existente@example.test']);

        Livewire::actingAs($actor)
            ->test(CreateUser::class)
            ->set('data', [
                'name' => 'Duplicada',
                'email' => 'existente@example.test',
                'password' => 'Clave-Segura-123!',
                'password_confirmation' => 'Clave-Segura-123!',
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);

        $this->assertSame(1, User::query()->count());
    }

    public function test_confirmacion_de_password_es_obligatoria_al_crear(): void
    {
        $actor = User::factory()->create();

        Livewire::actingAs($actor)
            ->test(CreateUser::class)
            ->set('data', [
                'name' => 'Sin confirmación',
                'email' => 'sin-confirmacion@example.test',
                'password' => 'Clave-Segura-123!',
                'password_confirmation' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['password_confirmation' => 'required']);
    }

    public function test_puede_editar_nombre_y_email(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        Livewire::actingAs($actor)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertSet('data.password', null)
            ->set('data', [
                'name' => 'Nombre actualizado',
                'email' => 'actualizado@example.test',
                'password' => null,
                'password_confirmation' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nombre actualizado', $target->fresh()->name);
        $this->assertSame('actualizado@example.test', $target->fresh()->email);
    }

    public function test_editar_sin_password_conserva_el_password_anterior(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['password' => 'Clave-Anterior-123!']);
        $passwordHash = $target->password;

        Livewire::actingAs($actor)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->set('data', [
                'name' => 'Sin cambio de clave',
                'email' => $target->email,
                'password' => '',
                'password_confirmation' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($passwordHash, $target->fresh()->password);
        $this->assertTrue(Hash::check('Clave-Anterior-123!', $target->fresh()->password));
    }

    public function test_editar_con_password_nuevo_cambia_el_password(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['password' => 'Clave-Anterior-123!']);

        Livewire::actingAs($actor)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->set('data', [
                'name' => $target->name,
                'email' => $target->email,
                'password' => 'Clave-Nueva-456!',
                'password_confirmation' => 'Clave-Nueva-456!',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();
        $this->assertTrue(Hash::check('Clave-Nueva-456!', $target->password));
        $this->assertFalse(Hash::check('Clave-Anterior-123!', $target->password));
    }

    public function test_perfil_permite_cambiar_password_validando_el_actual(): void
    {
        $user = User::factory()->create(['password' => 'Clave-Anterior-123!']);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('data', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'Clave-Propia-456!',
                'passwordConfirmation' => 'Clave-Propia-456!',
                'currentPassword' => 'Clave-Anterior-123!',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('Clave-Propia-456!', $user->password));
        $this->assertFalse(Hash::check('Clave-Anterior-123!', $user->password));
    }

    public function test_perfil_rechaza_un_password_actual_incorrecto(): void
    {
        $user = User::factory()->create(['password' => 'Clave-Anterior-123!']);
        $passwordHash = $user->password;

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('data', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'Clave-Propia-456!',
                'passwordConfirmation' => 'Clave-Propia-456!',
                'currentPassword' => 'incorrecta',
            ])
            ->call('save')
            ->assertHasFormErrors(['currentPassword' => 'current_password']);

        $this->assertSame($passwordHash, $user->fresh()->password);
    }

    public function test_usuario_puede_eliminar_otro_usuario(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        Livewire::actingAs($actor)
            ->test(ListUsers::class)
            ->callTableAction('delete', $target);

        $this->assertModelMissing($target);
        $this->assertModelExists($actor);
    }

    public function test_usuario_no_puede_eliminarse_a_si_mismo(): void
    {
        $actor = User::factory()->create();
        User::factory()->create();

        Livewire::actingAs($actor)
            ->test(ListUsers::class)
            ->assertTableActionHidden('delete', $actor);

        try {
            $actor->delete();
            $this->fail('La eliminación propia debería haber sido rechazada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertModelExists($actor);
    }

    public function test_no_se_puede_eliminar_el_ultimo_usuario(): void
    {
        $lastUser = User::factory()->create();

        try {
            $lastUser->delete();
            $this->fail('La eliminación del último usuario debería haber sido rechazada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertModelExists($lastUser);
    }

    public function test_registro_publico_sigue_deshabilitado(): void
    {
        $this->assertFalse(Route::has('filament.dash.auth.register'));
        $this->assertFalse(Route::has('filament.dash.auth.password-reset.request'));

        $this->get('/dash/register')->assertNotFound();
    }
}
