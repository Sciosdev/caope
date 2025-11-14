<?php

namespace Tests\Feature\Expedientes;

use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\TimelineEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteChangeStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'expedientes.manage']);
    }

    public function test_no_se_puede_cerrar_sin_sesion_validada(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('expedientes.manage');

        $expediente = Expediente::factory()->create([
            'estado' => 'abierto',
            'creado_por' => $usuario->id,
        ]);

        Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'status_revision' => 'pendiente',
        ]);

        $response = $this->actingAs($usuario)->post(route('expedientes.change-state', $expediente), [
            'estado' => 'cerrado',
        ]);

        $response->assertSessionHasErrors('estado');
        $this->assertSame('abierto', $expediente->fresh()->estado);
    }

    public function test_no_se_puede_cerrar_con_consentimientos_requeridos_pendientes(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('expedientes.manage');

        $expediente = Expediente::factory()->create([
            'estado' => 'revision',
            'creado_por' => $usuario->id,
        ]);

        Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'status_revision' => 'validada',
            'validada_por' => User::factory()->create()->id,
        ]);

        Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'status_revision' => 'observada',
        ]);

        Consentimiento::factory()->create([
            'expediente_id' => $expediente->id,
            'requerido' => true,
            'aceptado' => false,
            'archivo_path' => null,
        ]);

        $response = $this->actingAs($usuario)->post(route('expedientes.change-state', $expediente), [
            'estado' => 'cerrado',
        ]);

        $response->assertSessionHasErrors('estado');
        $this->assertSame('revision', $expediente->fresh()->estado);
    }

    public function test_no_se_puede_cerrar_con_sesiones_observadas(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('expedientes.manage');

        $expediente = Expediente::factory()->create([
            'estado' => 'revision',
            'creado_por' => $usuario->id,
        ]);

        Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'status_revision' => 'observada',
        ]);

        $response = $this->actingAs($usuario)->post(route('expedientes.change-state', $expediente), [
            'estado' => 'cerrado',
        ]);

        $response->assertSessionHasErrors('estado');
        $this->assertSame('revision', $expediente->fresh()->estado);
    }

    public function test_cierre_exitoso_registra_evento_en_timeline(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('expedientes.manage');

        $expediente = Expediente::factory()->create([
            'estado' => 'revision',
            'creado_por' => $usuario->id,
        ]);

        $validador = User::factory()->create();

        Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'status_revision' => 'validada',
            'validada_por' => $validador->id,
        ]);

        Consentimiento::factory()->create([
            'expediente_id' => $expediente->id,
            'requerido' => true,
            'aceptado' => true,
            'archivo_path' => 'consentimientos/prueba.pdf',
        ]);

        $response = $this->actingAs($usuario)->post(route('expedientes.change-state', $expediente), [
            'estado' => 'cerrado',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Estado del expediente actualizado correctamente.');

        $expediente->refresh();

        $this->assertSame('cerrado', $expediente->estado);
        $this->assertDatabaseHas('timeline_eventos', [
            'expediente_id' => $expediente->id,
            'evento' => 'expediente.estado_cambiado',
        ]);

        $evento = TimelineEvento::where('expediente_id', $expediente->id)
            ->where('evento', 'expediente.estado_cambiado')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($evento);
        $this->assertSame('revision', $evento->payload['antes']);
        $this->assertSame('cerrado', $evento->payload['despues']);
    }
}
