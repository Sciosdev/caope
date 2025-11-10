<?php

namespace Tests\Feature\Expedientes;

use App\Models\Anexo;
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

        $expediente = Expediente::factory()->create([
            'no_control' => 'CA-2025-0100',
            'paciente' => 'Paciente Original',
            'apertura' => Carbon::now()->subDays(5),
            'carrera' => $carreraOriginal->nombre,
            'turno' => $turnoOriginal->nombre,
            'tutor_id' => $tutor->id,
            'coordinador_id' => null,
            'creado_por' => $creator->id,
        ]);

        $payload = [
            'no_control' => 'CA-2025-0200',
            'paciente' => 'Paciente Actualizado',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carreraNueva->nombre,
            'turno' => $turnoNuevo->nombre,
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
        $this->assertSame('CA-2025-0100', $evento->payload['antes']['no_control']);
        $this->assertSame('CA-2025-0200', $evento->payload['despues']['no_control']);
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

        $payload = [
            'no_control' => $expediente->no_control,
            'paciente' => $expediente->paciente,
            'apertura' => $expediente->apertura->toDateString(),
            'carrera' => $expediente->carrera,
            'turno' => $expediente->turno,
            'tutor_id' => $tutorNuevo->id,
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

        $payload = [
            'no_control' => $expediente->no_control,
            'paciente' => $expediente->paciente,
            'apertura' => $expediente->apertura->toDateString(),
            'carrera' => $expediente->carrera,
            'turno' => $expediente->turno,
        ];

        $originalPaciente = $expediente->paciente;

        $response = $this->actingAs($docente)->put(route('expedientes.update', $expediente), $payload);

        $response->assertForbidden();

        $this->assertSame($originalPaciente, $expediente->fresh()->paciente);
    }

    public function test_actualizacion_con_json_incompletos_aplica_defaults(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $creator = User::factory()->create();

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Trabajo Social',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Mixto',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $expediente = Expediente::factory()->create([
            'creado_por' => $creator->id,
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
        ]);

        $payload = [
            'no_control' => $expediente->no_control,
            'paciente' => 'Paciente Actualizado',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'antecedentes_familiares' => [
                'hipertension_arterial' => [
                    'madre' => '1',
                    'padre' => 'no',
                    'otros_maternos' => 'true',
                ],
            ],
            'antecedentes_personales_patologicos' => [
                'asma' => [
                    'padece' => 'no',
                    'fecha' => '',
                ],
                'alergias' => [
                    'padece' => 1,
                    'fecha' => '2023-05-05',
                ],
            ],
            'aparatos_sistemas' => [
                'respiratorio' => 'Paciente con ligera tos',
                'cardiovascular' => null,
                'tegumentario' => '  Observaciones cutáneas  ',
            ],
            'plan_accion' => '  Plan de acción enfocado en hábitos saludables.  ',
        ];

        $response = $this->actingAs($admin)->put(route('expedientes.update', $expediente), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Expediente actualizado correctamente.');

        $expediente->refresh();

        $defaultsFamily = Expediente::defaultFamilyHistory();
        $this->assertSame(array_keys($defaultsFamily), array_keys($expediente->antecedentes_familiares));
        $this->assertTrue($expediente->antecedentes_familiares['hipertension_arterial']['madre']);
        $this->assertFalse($expediente->antecedentes_familiares['hipertension_arterial']['padre']);
        $this->assertTrue($expediente->antecedentes_familiares['hipertension_arterial']['otros_maternos']);
        $this->assertFalse($expediente->antecedentes_familiares['hipertension_arterial']['abuela_materna']);

        $defaultsPersonal = Expediente::defaultPersonalPathologicalHistory();
        $this->assertSame(array_keys($defaultsPersonal), array_keys($expediente->antecedentes_personales_patologicos));
        $this->assertFalse($expediente->antecedentes_personales_patologicos['asma']['padece']);
        $this->assertNull($expediente->antecedentes_personales_patologicos['asma']['fecha']);
        $this->assertTrue($expediente->antecedentes_personales_patologicos['alergias']['padece']);
        $this->assertSame('2023-05-05', $expediente->antecedentes_personales_patologicos['alergias']['fecha']);
        $this->assertFalse($expediente->antecedentes_personales_patologicos['varicela']['padece']);
        $this->assertNull($expediente->antecedentes_personales_patologicos['varicela']['fecha']);

        $defaultsSystems = Expediente::defaultSystemsReview();
        $this->assertSame(array_keys($defaultsSystems), array_keys($expediente->aparatos_sistemas));
        $this->assertSame('Paciente con ligera tos', $expediente->aparatos_sistemas['respiratorio']);
        $this->assertNull($expediente->aparatos_sistemas['cardiovascular']);
        $this->assertSame('Observaciones cutáneas', $expediente->aparatos_sistemas['tegumentario']);
        $this->assertSame('Plan de acción enfocado en hábitos saludables.', $expediente->plan_accion);
    }

    public function test_update_returns_json_payload_with_loaded_relations(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Enfermería',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Matutino',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $expediente = Expediente::factory()->create([
            'creado_por' => $admin->id,
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
        ]);

        $anexo = Anexo::create([
            'expediente_id' => $expediente->id,
            'titulo' => 'Plan de trabajo',
            'tipo' => 'pdf',
            'ruta' => 'expedientes/'.$expediente->id.'/anexos/plan.pdf',
            'disk' => 'private',
            'tamano' => 1024,
            'subido_por' => $admin->id,
        ]);

        $payload = [
            'no_control' => $expediente->no_control,
            'paciente' => 'Paciente Actualizado',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
        ];

        $response = $this->actingAs($admin)->putJson(route('expedientes.update', $expediente), $payload);

        $response->assertOk();
        $response->assertJsonPath('message', 'Expediente actualizado correctamente.');
        $response->assertJsonPath('student_error_message', __('expedientes.messages.student_save_error'));
        $response->assertJsonPath('expediente.id', $expediente->id);
        $response->assertJsonPath('expediente.alumno.id', $admin->id);

        $json = $response->json('expediente.anexos');
        $this->assertIsArray($json);
        $this->assertNotEmpty($json);
        $this->assertSame($anexo->id, $json[0]['id']);
        $this->assertArrayHasKey('download_url', $json[0]);
        $this->assertArrayHasKey('preview_url', $json[0]);
        $this->assertStringContainsString((string) $anexo->id, $json[0]['download_url']);
    }

    public function test_update_accepts_json_encoded_history_payloads(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $creator = User::factory()->create();

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Trabajo Social',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Mixto',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $expediente = Expediente::factory()->create([
            'creado_por' => $creator->id,
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
        ]);

        $familyHistory = [
            'diabetes_mellitus' => [
                'madre' => true,
                'padre' => false,
            ],
            'hipertension_arterial' => [
                'otros_maternos' => true,
            ],
        ];

        $personalHistory = [
            'asma' => [
                'padece' => true,
                'fecha' => '2024-02-15',
            ],
            'alergias' => [
                'padece' => false,
                'fecha' => null,
            ],
        ];

        $systemsReview = [
            'respiratorio' => 'Actualización respiratoria proporcionada por JSON.',
            'tegumentario' => '  Observaciones desde la app móvil.  ',
        ];

        $payload = [
            'no_control' => $expediente->no_control,
            'paciente' => 'Paciente Actualizado JSON',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'antecedentes_familiares' => json_encode($familyHistory, JSON_THROW_ON_ERROR),
            'antecedentes_personales_patologicos' => json_encode($personalHistory, JSON_THROW_ON_ERROR),
            'antecedentes_personales_observaciones' => 'Observaciones personales desde JSON.',
            'antecedente_padecimiento_actual' => 'Descripción enviada como JSON.',
            'aparatos_sistemas' => json_encode($systemsReview, JSON_THROW_ON_ERROR),
            'plan_accion' => '  Plan actualizado con datos JSON.  ',
            'diagnostico' => 'Diagnóstico recibido en payload JSON.',
            'dsm_tr' => 'F41.1 Trastorno de ansiedad generalizada',
            'observaciones_relevantes' => 'Observaciones relevantes capturadas en la app.',
        ];

        $response = $this->actingAs($admin)->put(route('expedientes.update', $expediente), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Expediente actualizado correctamente.');

        $expediente->refresh();

        $this->assertSame('Paciente Actualizado JSON', $expediente->paciente);
        $this->assertTrue($expediente->antecedentes_familiares['diabetes_mellitus']['madre']);
        $this->assertFalse($expediente->antecedentes_familiares['diabetes_mellitus']['padre']);
        $this->assertTrue($expediente->antecedentes_familiares['hipertension_arterial']['otros_maternos']);

        $this->assertTrue($expediente->antecedentes_personales_patologicos['asma']['padece']);
        $this->assertSame('2024-02-15', $expediente->antecedentes_personales_patologicos['asma']['fecha']);
        $this->assertFalse($expediente->antecedentes_personales_patologicos['alergias']['padece']);
        $this->assertNull($expediente->antecedentes_personales_patologicos['alergias']['fecha']);

        $this->assertSame('Actualización respiratoria proporcionada por JSON.', $expediente->aparatos_sistemas['respiratorio']);
        $this->assertSame('Observaciones desde la app móvil.', $expediente->aparatos_sistemas['tegumentario']);

        $this->assertSame('Observaciones personales desde JSON.', $expediente->antecedentes_personales_observaciones);
        $this->assertSame('Descripción enviada como JSON.', $expediente->antecedente_padecimiento_actual);
        $this->assertSame('Plan actualizado con datos JSON.', $expediente->plan_accion);
        $this->assertSame('Diagnóstico recibido en payload JSON.', $expediente->diagnostico);
        $this->assertSame('F41.1 Trastorno de ansiedad generalizada', $expediente->dsm_tr);
        $this->assertSame('Observaciones relevantes capturadas en la app.', $expediente->observaciones_relevantes);
    }
}
