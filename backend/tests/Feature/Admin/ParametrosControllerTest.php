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

    public function test_admin_cannot_enable_active_web_content_for_uploads(): void
    {
        $user = $this->createAdminUser();
        $anexos = Parametro::factory()->create([
            'clave' => 'uploads.anexos.mimes',
            'valor' => 'pdf,jpg',
            'tipo' => Parametro::TYPE_STRING,
        ]);
        $consentimientos = Parametro::factory()->create([
            'clave' => 'uploads.consentimientos.mimes',
            'valor' => 'pdf,png',
            'tipo' => Parametro::TYPE_STRING,
        ]);

        $this->actingAs($user)
            ->from(route('admin.parametros.index'))
            ->put(route('admin.parametros.update', $anexos), ['valor' => 'pdf,svg,html'])
            ->assertRedirect(route('admin.parametros.index'))
            ->assertSessionHasErrorsIn('parametro-'.$anexos->id, 'valor');

        $this->actingAs($user)
            ->from(route('admin.parametros.index'))
            ->put(route('admin.parametros.update', $consentimientos), ['valor' => 'pdf,svg'])
            ->assertRedirect(route('admin.parametros.index'))
            ->assertSessionHasErrorsIn('parametro-'.$consentimientos->id, 'valor');

        $this->assertSame('pdf,jpg', $anexos->fresh()->getRawOriginal('valor'));
        $this->assertSame('pdf,png', $consentimientos->fresh()->getRawOriginal('valor'));
    }

    public function test_admin_cannot_raise_upload_limits_above_the_server_caps(): void
    {
        $user = $this->createAdminUser();
        $anexos = Parametro::factory()->create([
            'clave' => 'uploads.anexos.max',
            'valor' => '51200',
            'tipo' => Parametro::TYPE_INTEGER,
        ]);
        $consentimientos = Parametro::factory()->create([
            'clave' => 'uploads.consentimientos.max',
            'valor' => '5120',
            'tipo' => Parametro::TYPE_INTEGER,
        ]);

        $this->actingAs($user)
            ->put(route('admin.parametros.update', $anexos), ['valor' => 51201])
            ->assertSessionHasErrorsIn('parametro-'.$anexos->id, 'valor');
        $this->actingAs($user)
            ->put(route('admin.parametros.update', $consentimientos), ['valor' => 5121])
            ->assertSessionHasErrorsIn('parametro-'.$consentimientos->id, 'valor');

        $this->assertSame(51200, $anexos->fresh()->valor);
        $this->assertSame(5120, $consentimientos->fresh()->valor);
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
