<?php

namespace Tests\Feature\Consentimientos;

use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConsentimientoUploadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'expedientes.manage']);
        Permission::firstOrCreate(['name' => 'expedientes.view']);
        Role::firstOrCreate(['name' => 'alumno']);
    }

    public function test_usuario_con_permiso_puede_subir_consentimiento(): void
    {
        Storage::fake('private');

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('expedientes.manage');

        $expediente = Expediente::factory()->create([
            'creado_por' => $usuario->id,
        ]);

        $consentimiento = Consentimiento::factory()->for($expediente)->create([
            'aceptado' => false,
            'fecha' => null,
            'archivo_path' => null,
            'subido_por' => null,
        ]);

        $archivo = UploadedFile::fake()->create('consentimiento.pdf', 120, 'application/pdf');

        $response = $this->actingAs($usuario)->post(route('consentimientos.upload', $consentimiento), [
            'archivo' => $archivo,
            'aceptado' => '1',
            'fecha' => '2024-01-15',
        ]);

        $response
            ->assertRedirect(route('expedientes.show', $expediente))
            ->assertSessionHas('status', 'Consentimiento actualizado correctamente.');

        $consentimiento->refresh();

        $this->assertTrue($consentimiento->aceptado);
        $this->assertSame('2024-01-15', optional($consentimiento->fecha)->format('Y-m-d'));
        $this->assertNotNull($consentimiento->archivo_path);
        Storage::disk('private')->assertExists($consentimiento->archivo_path);
        $this->assertSame($usuario->id, $consentimiento->subido_por);
    }

    public function test_usuario_sin_autorizacion_no_puede_subir_archivo(): void
    {
        $creador = User::factory()->create();
        $creador->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'creado_por' => $creador->id,
        ]);

        $consentimiento = Consentimiento::factory()->for($expediente)->create([
            'aceptado' => false,
            'archivo_path' => null,
            'subido_por' => null,
        ]);

        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario)->post(route('consentimientos.upload', $consentimiento), [
            'archivo' => UploadedFile::fake()->create('archivo.pdf', 100, 'application/pdf'),
            'aceptado' => '1',
        ]);

        $response->assertForbidden();
        $this->assertNull($consentimiento->fresh()->archivo_path);
    }

    public function test_validacion_de_tipo_de_archivo(): void
    {
        Storage::fake('private');

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('expedientes.manage');

        $expediente = Expediente::factory()->create([
            'creado_por' => $usuario->id,
        ]);

        $consentimiento = Consentimiento::factory()->for($expediente)->create([
            'aceptado' => false,
            'archivo_path' => null,
            'subido_por' => null,
        ]);

        $response = $this->actingAs($usuario)->post(route('consentimientos.upload', $consentimiento), [
            'archivo' => UploadedFile::fake()->create('archivo.txt', 20, 'text/plain'),
            'aceptado' => '1',
        ]);

        $response->assertSessionHasErrorsIn(
            sprintf('consentimientoUpload-%s', $consentimiento->id),
            ['archivo']
        );
        $this->assertNull($consentimiento->fresh()->archivo_path);
        Storage::disk('private')->assertDirectoryEmpty('');
    }

    public function test_estado_del_consentimiento_se_muestra_en_el_detalle_del_expediente(): void
    {
        $subidor = User::factory()->create(['name' => 'Usuario Firmante']);
        $subidor->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'creado_por' => $subidor->id,
        ]);

        $consentimiento = Consentimiento::factory()->for($expediente)->create([
            'tratamiento' => 'Carta de consentimiento',
            'aceptado' => true,
            'fecha' => now()->startOfDay(),
            'archivo_path' => 'expedientes/'.$expediente->id.'/consentimientos/consentimiento-firmado.pdf',
            'subido_por' => $subidor->id,
        ]);

        $response = $this->actingAs($subidor)->get(route('expedientes.show', $expediente));

        $response
            ->assertOk()
            ->assertSeeText('Carta de consentimiento')
            ->assertSee('Ver archivo')
            ->assertSeeText('consentimiento-firmado.pdf')
            ->assertSeeText($subidor->name)
            ->assertSeeText(now()->format('Y-m-d'));
    }
}
