<?php

namespace Tests\Feature\Expedientes;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\Expediente;
use App\Models\User;
use App\Notifications\TutorAssignedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();
    }

    public function test_admin_actualiza_campos_basicos_y_registra_timeline(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $creator = User::factory()->create();
        $tutor = User::factory()->create();

        $carreraOriginal = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Psicología',
            'activo' => true,
        ]);

        $carreraNueva = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Enfermería',
            'activo' => true,
        ]);

        $turnoOriginal = CatalogoTurno::create([
            'nombre' => 'Matutino',
            'activo' => true,
        ]);

        $turnoNuevo = CatalogoTurno::create([
            'nombre' => 'Vespertino',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $originalAntecedentes = array_fill_keys(array_keys(Expediente::ANTECEDENTES_FAMILIARES_OPTIONS), false);
        $originalAntecedentes['madre'] = true;
        $originalAntecedentes['padre'] = true;

        $expediente = Expediente::factory()->create([
            'no_control' => 'CA-2025-0100',
            'paciente' => 'Paciente Original',
            'apertura' => Carbon::now()->subDays(5),
            'carrera' => $carreraOriginal->nombre,
            'turno' => $turnoOriginal->nombre,
            'tutor_id' => $tutor->id,
            'coordinador_id' => null,
            'creado_por' => $creator->id,
            'antecedentes_familiares' => $originalAntecedentes,
            'antecedentes_observaciones' => 'Observaciones iniciales.',
        ]);

        $expectedAntecedentes = $originalAntecedentes;
        $expectedAntecedentes['madre'] = false;
        $expectedAntecedentes['hermanos'] = true;

        $payload = [
            'no_control' => 'CA-2025-0200',
            'paciente' => 'Paciente Actualizado',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carreraNueva->nombre,
            'turno' => $turnoNuevo->nombre,
            'antecedentes_familiares' => [
                'madre' => '0',
                'padre' => '1',
                'hermanos' => '1',
            ],
            'antecedentes_observaciones' => 'Se agrega antecedente por parte de hermanos.',
        ];

        $response = $this->actingAs($admin)->put(route('expedientes.update', $expediente), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Expediente actualizado correctamente.');
        $response->assertRedirect(route('expedientes.show', $expediente));

        $expediente->refresh();

        $this->assertSame($payload['no_control'], $expediente->no_control);
        $this->assertSame($payload['paciente'], $expediente->paciente);
        $this->assertSame($payload['carrera'], $expediente->carrera);
        $this->assertSame($payload['turno'], $expediente->turno);
        $this->assertSame($expectedAntecedentes, $expediente->antecedentes_familiares);
        $this->assertSame($payload['antecedentes_observaciones'], $expediente->antecedentes_observaciones);

        $this->assertDatabaseHas('timeline_eventos', [
            'expediente_id' => $expediente->id,
            'actor_id' => $admin->id,
            'evento' => 'expediente.actualizado',
        ]);

        $evento = $expediente->timelineEventos()
            ->where('evento', 'expediente.actualizado')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($evento);
        $this->assertContains('no_control', $evento->payload['campos']);
        $this->assertContains('paciente', $evento->payload['campos']);
        $this->assertContains('carrera', $evento->payload['campos']);
        $this->assertContains('turno', $evento->payload['campos']);
        $this->assertContains('antecedentes_familiares', $evento->payload['campos']);
        $this->assertContains('antecedentes_observaciones', $evento->payload['campos']);
        $this->assertSame('CA-2025-0100', $evento->payload['antes']['no_control']);
        $this->assertSame('CA-2025-0200', $evento->payload['despues']['no_control']);
        $this->assertSame($originalAntecedentes, $evento->payload['antes']['antecedentes_familiares']);
        $this->assertSame($expectedAntecedentes, $evento->payload['despues']['antecedentes_familiares']);
        $this->assertSame('Observaciones iniciales.', $evento->payload['antes']['antecedentes_observaciones']);
        $this->assertSame($payload['antecedentes_observaciones'], $evento->payload['despues']['antecedentes_observaciones']);
    }

    public function test_reasignacion_de_tutor_notifica_y_registra_en_timeline(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $creator = User::factory()->create();
        $tutorAnterior = User::factory()->create();
        $tutorNuevo = User::factory()->create();

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Psicología',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Matutino',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $expediente = Expediente::factory()->create([
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'tutor_id' => $tutorAnterior->id,
            'creado_por' => $creator->id,
        ]);

        $antecedentesPayload = [];

        foreach (array_keys(Expediente::ANTECEDENTES_FAMILIARES_OPTIONS) as $key) {
            $antecedentesPayload[$key] = ($expediente->antecedentes_familiares[$key] ?? false) ? '1' : '0';
        }

        $payload = [
            'no_control' => $expediente->no_control,
            'paciente' => $expediente->paciente,
            'apertura' => $expediente->apertura->toDateString(),
            'carrera' => $expediente->carrera,
            'turno' => $expediente->turno,
            'tutor_id' => $tutorNuevo->id,
            'antecedentes_familiares' => $antecedentesPayload,
            'antecedentes_observaciones' => $expediente->antecedentes_observaciones ?? '',
        ];

        $response = $this->actingAs($admin)->put(route('expedientes.update', $expediente), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Expediente actualizado correctamente.');
        $response->assertRedirect(route('expedientes.show', $expediente));

        $expediente->refresh();

        $this->assertSame($tutorNuevo->id, $expediente->tutor_id);

        $evento = $expediente->timelineEventos()
            ->where('evento', 'expediente.actualizado')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($evento);
        $this->assertContains('tutor_id', $evento->payload['campos']);
        $this->assertSame($tutorAnterior->id, $evento->payload['antes']['tutor_id']);
        $this->assertSame($tutorNuevo->id, $evento->payload['despues']['tutor_id']);

        Notification::assertSentTo(
            $tutorNuevo,
            TutorAssignedNotification::class,
            function (TutorAssignedNotification $notification) use ($expediente, $admin, $tutorNuevo) {
                $data = $notification->toArray($tutorNuevo);

                return $data['expediente_id'] === $expediente->id
                    && $data['actor_id'] === $admin->id;
            }
        );
    }

    public function test_usuario_sin_permisos_no_puede_actualizar(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole('docente');

        $creador = User::factory()->create();

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Psicología',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Matutino',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $expediente = Expediente::factory()->create([
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'tutor_id' => null,
            'creado_por' => $creador->id,
        ]);

        $antecedentesPayload = [];

        foreach (array_keys(Expediente::ANTECEDENTES_FAMILIARES_OPTIONS) as $key) {
            $antecedentesPayload[$key] = ($expediente->antecedentes_familiares[$key] ?? false) ? '1' : '0';
        }

        $payload = [
            'no_control' => $expediente->no_control,
            'paciente' => $expediente->paciente,
            'apertura' => $expediente->apertura->toDateString(),
            'carrera' => $expediente->carrera,
            'turno' => $expediente->turno,
            'antecedentes_familiares' => $antecedentesPayload,
            'antecedentes_observaciones' => $expediente->antecedentes_observaciones ?? '',
        ];

        $originalPaciente = $expediente->paciente;

        $response = $this->actingAs($docente)->put(route('expedientes.update', $expediente), $payload);

        $response->assertForbidden();

        $this->assertSame($originalPaciente, $expediente->fresh()->paciente);
    }
}
