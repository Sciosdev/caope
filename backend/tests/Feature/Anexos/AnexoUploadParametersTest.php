<?php

namespace Tests\Feature\Anexos;

use App\Models\Expediente;
use App\Models\Parametro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AnexoUploadParametersTest extends TestCase
{
    use RefreshDatabase;

    public function test_limites_de_anexos_se_actualizan_en_el_momento(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $admin = $this->createAdminUser();

        Parametro::factory()->create([
            'clave' => 'uploads.anexos.mimes',
            'valor' => 'txt',
            'tipo' => Parametro::TYPE_STRING,
        ]);

        $parametroMax = Parametro::factory()->create([
            'clave' => 'uploads.anexos.max',
            'valor' => '1',
            'tipo' => Parametro::TYPE_INTEGER,
        ]);

        $expediente = Expediente::factory()->create();

        $this->assertSame(1, Parametro::obtener('uploads.anexos.max'));

        $archivoGrande = UploadedFile::fake()->create('documento.txt', 2, 'text/plain');

        $response = $this->actingAs($admin)
            ->from(route('expedientes.show', $expediente))
            ->post(route('expedientes.anexos.store', $expediente), [
                'archivo' => $archivoGrande,
                'es_privado' => true,
            ]);

        $response->assertSessionHasErrors('archivo');

        Artisan::spy();

        $this->actingAs($admin)
            ->put(route('admin.parametros.update', $parametroMax), ['valor' => 10])
            ->assertRedirect(route('admin.parametros.index'));

        $this->assertSame(10, Parametro::obtener('uploads.anexos.max'));

        Artisan::shouldHaveReceived('call')->with('config:clear');

        $archivoPermitido = UploadedFile::fake()->create('documento.txt', 2, 'text/plain');

        $response = $this->actingAs($admin)
            ->from(route('expedientes.show', $expediente))
            ->post(route('expedientes.anexos.store', $expediente), [
                'archivo' => $archivoPermitido,
                'es_privado' => true,
            ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('expedientes.show', $expediente));
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
