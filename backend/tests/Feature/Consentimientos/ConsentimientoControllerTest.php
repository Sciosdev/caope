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

class ConsentimientoControllerTest extends TestCase
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

    public function test_usuario_puede_crear_consentimiento_con_archivo(): void
    {
        Storage::fake('private');

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('expedientes.manage');

        $expediente = Expediente::factory()->create([
            'creado_por' => $usuario->id,
        ]);

        $archivo = UploadedFile::fake()->create('consentimiento.pdf', 150, 'application/pdf');

        $response = $this->actingAs($usuario)->post(
            route('expedientes.consentimientos.store', $expediente),
            [
                'tipo' => 'Consentimiento informado',
                'requerido' => '1',
                'aceptado' => '1',
                'fecha' => '2024-05-10',
                'archivo' => $archivo,
            ]
        );

        $response
            ->assertRedirect(route('expedientes.show', ['expediente' => $expediente, 'tab' => 'consentimientos']))
            ->assertSessionHas('status', 'Consentimiento guardado correctamente.');

        $consentimiento = Consentimiento::query()->latest('id')->first();
        $this->assertNotNull($consentimiento);
        $this->assertSame('Consentimiento informado', $consentimiento->tratamiento);
        $this->assertTrue($consentimiento->requerido);
        $this->assertTrue($consentimiento->aceptado);
        $this->assertSame('2024-05-10', optional($consentimiento->fecha)->format('Y-m-d'));
        $this->assertSame($usuario->id, $consentimiento->subido_por);
        $this->assertNotNull($consentimiento->archivo_path);
        Storage::disk('private')->assertExists($consentimiento->archivo_path);
    }

    public function test_usuario_puede_actualizar_consentimiento_y_reemplazar_archivo(): void
    {
        Storage::fake('private');

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('expedientes.manage');

        $expediente = Expediente::factory()->create([
            'creado_por' => $usuario->id,
        ]);

        $consentimiento = Consentimiento::factory()->for($expediente)->create([
            'tratamiento' => 'Tratamiento original',
            'requerido' => false,
            'aceptado' => false,
            'archivo_path' => null,
            'subido_por' => null,
        ]);

        $archivoAnterior = UploadedFile::fake()->create('anterior.pdf', 120, 'application/pdf');
        $rutaAnterior = $archivoAnterior->storeAs(
            sprintf('expedientes/%s/consentimientos', $expediente->id),
            'anterior.pdf',
            'private'
        );

        $consentimiento->forceFill([
            'archivo_path' => $rutaAnterior,
            'subido_por' => $usuario->id,
        ])->save();

        $archivoNuevo = UploadedFile::fake()->create('nuevo.pdf', 120, 'application/pdf');

        $response = $this->actingAs($usuario)->put(
            route('expedientes.consentimientos.update', [$expediente, $consentimiento]),
            [
                'tipo' => 'Tipo actualizado',
                'requerido' => '1',
                'aceptado' => '1',
                'fecha' => '2024-05-15',
                'archivo' => $archivoNuevo,
            ]
        );

        $response
            ->assertRedirect(route('expedientes.show', ['expediente' => $expediente, 'tab' => 'consentimientos']))
            ->assertSessionHas('status', 'Consentimiento actualizado correctamente.');

        $consentimiento->refresh();

        $this->assertSame('Tipo actualizado', $consentimiento->tratamiento);
        $this->assertTrue($consentimiento->requerido);
        $this->assertTrue($consentimiento->aceptado);
        $this->assertSame('2024-05-15', optional($consentimiento->fecha)->format('Y-m-d'));
        $this->assertSame($usuario->id, $consentimiento->subido_por);
        $this->assertNotNull($consentimiento->archivo_path);
        Storage::disk('private')->assertExists($consentimiento->archivo_path);
        Storage::disk('private')->assertMissing($rutaAnterior);
    }

    public function test_usuario_puede_eliminar_consentimiento(): void
    {
        Storage::fake('private');

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('expedientes.manage');

        $expediente = Expediente::factory()->create([
            'creado_por' => $usuario->id,
        ]);

        $archivo = UploadedFile::fake()->create('consentimiento.pdf', 150, 'application/pdf');

        $ruta = $archivo->storeAs(
            sprintf('expedientes/%s/consentimientos', $expediente->id),
            'consentimiento.pdf',
            'private'
        );

        $consentimiento = Consentimiento::factory()->for($expediente)->create([
            'archivo_path' => $ruta,
            'subido_por' => $usuario->id,
        ]);

        $response = $this->actingAs($usuario)->delete(
            route('expedientes.consentimientos.destroy', [$expediente, $consentimiento])
        );

        $response
            ->assertRedirect(route('expedientes.show', ['expediente' => $expediente, 'tab' => 'consentimientos']))
            ->assertSessionHas('status', 'Consentimiento eliminado correctamente.');

        $this->assertDatabaseMissing('consentimientos', ['id' => $consentimiento->id]);
        Storage::disk('private')->assertMissing($ruta);
    }
}
