<?php

namespace Tests\Feature\Admin;

use App\Models\Parametro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ParametrosControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_ver_listado_de_parametros(): void
    {
        $user = $this->createAdminUser();

        Parametro::factory()->create([
            'clave' => 'demo.parametro',
            'valor' => 'valor',
            'tipo' => Parametro::TYPE_STRING,
        ]);

        $response = $this->actingAs($user)->get(route('admin.parametros.index'));

        $response->assertOk();
        $response->assertViewIs('admin.parametros.index');
        $response->assertSeeText('demo.parametro');
    }

    public function test_admin_actualiza_parametro_y_cambios_aplican_de_inmediato(): void
    {
        $user = $this->createAdminUser();

        $parametro = Parametro::factory()->create([
            'clave' => 'uploads.anexos.max',
            'valor' => '1',
            'tipo' => Parametro::TYPE_INTEGER,
        ]);

        $this->assertSame(1, Parametro::obtener('uploads.anexos.max'));

        Artisan::spy();

        $response = $this->actingAs($user)
            ->from(route('admin.parametros.index'))
            ->put(route('admin.parametros.update', $parametro), ['valor' => 5]);

        $response->assertRedirect(route('admin.parametros.index'));
        $response->assertSessionHas('status', 'Parámetro actualizado correctamente.');

        $parametro->refresh();
        $this->assertSame(5, $parametro->valor);
        $this->assertSame(5, Parametro::obtener('uploads.anexos.max'));

        Artisan::shouldHaveReceived('call')->with('config:clear');
    }

    private function createAdminUser(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::query()->firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
