<?php

namespace Tests\Feature\Consentimientos;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTratamiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConsentimientoRequeridoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('consentimientos.manage', 'web');
    }

    public function test_usuario_con_permiso_puede_ver_matriz_de_tratamientos_requeridos(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('consentimientos.manage');

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Arquitectura',
        ]);

        $tratamiento = CatalogoTratamiento::create([
            'nombre' => 'Consentimiento informado',
        ]);

        $carrera->tratamientos()->attach($tratamiento->id, ['obligatorio' => true]);

        $response = $this->actingAs($usuario)->get(route('consentimientos.requeridos.index'));

        $response->assertOk();
        $response->assertViewIs('consentimientos.requeridos.index');
        $response->assertSeeText('Tratamientos requeridos por carrera');
        $response->assertSeeText($carrera->nombre);
        $response->assertSeeText($tratamiento->nombre);
    }

    public function test_usuario_sin_permiso_no_puede_ver_matriz_de_tratamientos_requeridos(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario)->get(route('consentimientos.requeridos.index'));

        $response->assertForbidden();
    }

    public function test_usuario_con_permiso_puede_actualizar_tratamientos_requeridos(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('consentimientos.manage');

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Ingeniería Biomédica',
        ]);

        $tratamientoA = CatalogoTratamiento::create([
            'nombre' => 'Carta de consentimiento',
        ]);

        $tratamientoB = CatalogoTratamiento::create([
            'nombre' => 'Autorización de laboratorio',
        ]);

        $response = $this->actingAs($usuario)->put(route('consentimientos.requeridos.update'), [
            'requeridos' => [
                $carrera->id => [$tratamientoA->id, $tratamientoB->id],
            ],
        ]);

        $response->assertRedirect(route('consentimientos.requeridos.index'));
        $response->assertSessionHas('status', 'Los tratamientos requeridos se actualizaron correctamente.');

        $this->assertDatabaseHas('carrera_tratamiento', [
            'catalogo_carrera_id' => $carrera->id,
            'catalogo_tratamiento_id' => $tratamientoA->id,
            'obligatorio' => true,
        ]);

        $this->assertDatabaseHas('carrera_tratamiento', [
            'catalogo_carrera_id' => $carrera->id,
            'catalogo_tratamiento_id' => $tratamientoB->id,
            'obligatorio' => true,
        ]);

        $this->assertSame(2, $carrera->fresh()->tratamientosRequeridos()->count());
    }

    public function test_usuario_sin_permiso_no_puede_actualizar_tratamientos_requeridos(): void
    {
        $usuario = User::factory()->create();
        $carrera = CatalogoCarrera::create([
            'nombre' => 'Arquitectura',
        ]);

        $tratamiento = CatalogoTratamiento::create([
            'nombre' => 'Carta de consentimiento',
        ]);

        $response = $this->actingAs($usuario)->put(route('consentimientos.requeridos.update'), [
            'requeridos' => [
                $carrera->id => [$tratamiento->id],
            ],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('carrera_tratamiento', 0);
    }

    public function test_actualizar_tratamientos_permite_limpiar_selecciones(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('consentimientos.manage');

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Psicología',
        ]);

        $tratamiento = CatalogoTratamiento::create([
            'nombre' => 'Formato de confidencialidad',
        ]);

        $carrera->tratamientos()->attach($tratamiento->id, ['obligatorio' => true]);

        $response = $this->actingAs($usuario)->put(route('consentimientos.requeridos.update'), [
            'requeridos' => [],
        ]);

        $response->assertRedirect(route('consentimientos.requeridos.index'));
        $response->assertSessionHas('status', 'Los tratamientos requeridos se actualizaron correctamente.');

        $this->assertDatabaseMissing('carrera_tratamiento', [
            'catalogo_carrera_id' => $carrera->id,
            'catalogo_tratamiento_id' => $tratamiento->id,
        ]);

        $this->assertSame(0, $carrera->fresh()->tratamientos()->count());
    }
}
