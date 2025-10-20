<?php

namespace Tests\Feature\Sesiones;

use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\TimelineEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SesionRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::create(['name' => 'alumno']);
        Role::create(['name' => 'docente']);
        Permission::create(['name' => 'expedientes.manage']);
    }

    public function test_registrar_sesion_crea_evento_en_timeline(): void
    {
        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'creado_por' => $alumno->id,
            'estado' => 'abierto',
            'tutor_id' => null,
        ]);

        $response = $this->actingAs($alumno)->post(route('expedientes.sesiones.store', $expediente), [
            'fecha' => now()->toDateString(),
            'tipo' => 'Seguimiento',
            'referencia_externa' => null,
            'nota' => '<p>Notas de prueba</p>',
        ]);

        $response->assertRedirect();

        $sesion = $expediente->sesiones()->latest('id')->first();

        $this->assertNotNull($sesion);
        $this->assertDatabaseHas('timeline_eventos', [
            'expediente_id' => $expediente->id,
            'evento' => 'sesion.creada',
        ]);

        $evento = TimelineEvento::where('expediente_id', $expediente->id)
            ->where('evento', 'sesion.creada')
            ->latest('created_at')
            ->first();

        $this->assertSame(null, $evento->payload['estado_anterior']);
        $this->assertSame('pendiente', $evento->payload['estado_nuevo']);
        $this->assertSame($sesion->id, $evento->payload['sesion_id']);
    }

    public function test_docente_puede_observar_sesion_y_registra_evento(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole('docente');

        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'creado_por' => $alumno->id,
            'tutor_id' => $docente->id,
            'estado' => 'revision',
        ]);

        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $alumno->id,
            'status_revision' => 'pendiente',
        ]);

        $payload = [
            'observaciones' => 'Falta adjuntar plan de trabajo',
            'form_action' => 'observe',
        ];

        $response = $this->actingAs($docente)->post(route('expedientes.sesiones.observe', [$expediente, $sesion]), $payload);

        $response->assertRedirect(route('expedientes.sesiones.show', [$expediente, $sesion]));

        $sesion->refresh();

        $this->assertSame('observada', $sesion->status_revision);
        $this->assertNull($sesion->validada_por);

        $evento = TimelineEvento::where('expediente_id', $expediente->id)
            ->where('evento', 'sesion.observada')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($evento);
        $this->assertSame('pendiente', $evento->payload['estado_anterior']);
        $this->assertSame('observada', $evento->payload['estado_nuevo']);
        $this->assertSame($payload['observaciones'], $evento->payload['observaciones']);
        $this->assertSame($sesion->id, $evento->payload['sesion_id']);
    }

    public function test_docente_puede_validar_sesion_y_registra_evento(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole('docente');

        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'creado_por' => $alumno->id,
            'tutor_id' => $docente->id,
            'estado' => 'revision',
        ]);

        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $alumno->id,
            'status_revision' => 'pendiente',
        ]);

        $payload = [
            'observaciones' => 'Cumple con los criterios establecidos',
            'form_action' => 'validate',
        ];

        $response = $this->actingAs($docente)->post(route('expedientes.sesiones.validate', [$expediente, $sesion]), $payload);

        $response->assertRedirect(route('expedientes.sesiones.show', [$expediente, $sesion]));

        $sesion->refresh();

        $this->assertSame('validada', $sesion->status_revision);
        $this->assertSame($docente->id, $sesion->validada_por);

        $evento = TimelineEvento::where('expediente_id', $expediente->id)
            ->where('evento', 'sesion.validada')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($evento);
        $this->assertSame('pendiente', $evento->payload['estado_anterior']);
        $this->assertSame('validada', $evento->payload['estado_nuevo']);
        $this->assertSame($payload['observaciones'], $evento->payload['observaciones']);
        $this->assertSame($sesion->id, $evento->payload['sesion_id']);
    }

    public function test_alumno_no_puede_editar_sesion_validada(): void
    {
        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'creado_por' => $alumno->id,
            'tutor_id' => null,
            'estado' => 'revision',
        ]);

        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $alumno->id,
            'status_revision' => 'validada',
            'validada_por' => User::factory()->create()->id,
        ]);

        $this->assertSame($expediente->id, $sesion->expediente_id);

        $response = $this->actingAs($alumno)->put(route('expedientes.sesiones.update', [$expediente, $sesion]), [
            'fecha' => now()->toDateString(),
            'tipo' => 'Seguimiento',
            'referencia_externa' => 'REF-001',
            'nota' => '<p>Intento de actualización</p>',
        ]);

        $response->assertForbidden();
    }
}
