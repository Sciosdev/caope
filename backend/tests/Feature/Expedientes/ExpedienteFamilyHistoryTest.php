<?php

namespace Tests\Feature\Expedientes;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteFamilyHistoryTest extends TestCase
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

    public function test_hereditary_history_conditions_list_includes_new_entries(): void
    {
        $expectedConditions = [
            'epilepsia' => 'Epilepsia',
            'malformaciones' => 'Malformaciones congénitas',
            'sida' => 'VIH/SIDA',
            'hepatitis' => 'Hepatitis',
            'artritis' => 'Artritis',
            'otra' => 'Otro',
            'aparentemente_sano' => 'Aparentemente sano',
        ];

        foreach ($expectedConditions as $key => $label) {
            $this->assertArrayHasKey($key, Expediente::HEREDITARY_HISTORY_CONDITIONS);
            $this->assertSame($label, Expediente::HEREDITARY_HISTORY_CONDITIONS[$key]);
        }
    }

    public function test_alumno_puede_crear_expediente_con_antecedentes(): void
    {
        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Terapia',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Matutino',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $familyPayload = [];
        foreach (Expediente::HEREDITARY_HISTORY_CONDITIONS as $conditionKey => $conditionLabel) {
            $familyPayload[$conditionKey] = [];
            foreach (Expediente::FAMILY_HISTORY_MEMBERS as $memberKey => $memberLabel) {
                $familyPayload[$conditionKey][$memberKey] = '0';
            }
        }

        $familyPayload['diabetes_mellitus']['madre'] = '1';
        $familyPayload['hipertension_arterial']['padre'] = '1';
        $familyPayload['cancer']['otros_maternos'] = '1';

        $personalPayload = [];
        foreach (Expediente::PERSONAL_PATHOLOGICAL_CONDITIONS as $conditionKey => $conditionLabel) {
            $personalPayload[$conditionKey] = [
                'padece' => '0',
                'fecha' => '',
            ];
        }

        $personalPayload['asma'] = [
            'padece' => '1',
            'fecha' => Carbon::now()->subYears(2)->format('Y-m-d'),
        ];
        $personalPayload['cirugias_previas'] = [
            'padece' => '1',
            'fecha' => Carbon::now()->subYear()->format('Y-m-d'),
        ];

        $payload = [
            'no_control' => 'AL-2025-0001',
            'paciente' => 'Alumno Demo',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'antecedentes_familiares' => $familyPayload,
            'antecedentes_observaciones' => 'Antecedentes por madre y hermanos.',
            'antecedentes_personales_patologicos' => $personalPayload,
            'antecedentes_personales_observaciones' => 'Asma desde la infancia, cirugía en 2023.',
        ];

        $response = $this->actingAs($alumno)->post(route('expedientes.store'), $payload);

        $response->assertSessionHasNoErrors();

        $expediente = Expediente::where('no_control', $payload['no_control'])->first();
        $this->assertNotNull($expediente);

        $this->assertTrue($expediente->antecedentes_familiares['diabetes_mellitus']['madre']);
        $this->assertTrue($expediente->antecedentes_familiares['hipertension_arterial']['padre']);
        $this->assertTrue($expediente->antecedentes_familiares['cancer']['otros_maternos']);
        $this->assertFalse($expediente->antecedentes_familiares['obesidad']['madre']);
        $this->assertSame(
            $payload['antecedentes_observaciones'],
            $expediente->antecedentes_observaciones
        );
        $this->assertTrue($expediente->antecedentes_personales_patologicos['asma']['padece']);
        $this->assertSame(
            $personalPayload['asma']['fecha'],
            $expediente->antecedentes_personales_patologicos['asma']['fecha']
        );
        $this->assertTrue($expediente->antecedentes_personales_patologicos['cirugias_previas']['padece']);
        $this->assertSame(
            $personalPayload['cirugias_previas']['fecha'],
            $expediente->antecedentes_personales_patologicos['cirugias_previas']['fecha']
        );
        $this->assertSame(
            $payload['antecedentes_personales_observaciones'],
            $expediente->antecedentes_personales_observaciones
        );

        $this->assertDatabaseHas('timeline_eventos', [
            'expediente_id' => $expediente->id,
            'actor_id' => $alumno->id,
            'evento' => 'expediente.antecedentes_registrados',
        ]);

        $creacionEvento = $expediente->timelineEventos()
            ->where('evento', 'expediente.creado')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($creacionEvento);
        $this->assertSame(
            $expediente->antecedentes_familiares,
            $creacionEvento->payload['datos']['antecedentes_familiares']
        );
        $this->assertSame(
            $expediente->antecedentes_observaciones,
            $creacionEvento->payload['datos']['antecedentes_observaciones']
        );
        $this->assertSame(
            $expediente->antecedentes_personales_patologicos,
            $creacionEvento->payload['datos']['antecedentes_personales_patologicos']
        );
        $this->assertSame(
            $expediente->antecedentes_personales_observaciones,
            $creacionEvento->payload['datos']['antecedentes_personales_observaciones']
        );

        $antecedentesEvento = $expediente->timelineEventos()
            ->where('evento', 'expediente.antecedentes_registrados')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($antecedentesEvento);
        $this->assertSame(
            $expediente->antecedentes_familiares,
            $antecedentesEvento->payload['datos']['familiares']
        );
        $this->assertSame(
            $expediente->antecedentes_observaciones,
            $antecedentesEvento->payload['datos']['observaciones']
        );
        $this->assertSame(
            $expediente->antecedentes_personales_patologicos,
            $antecedentesEvento->payload['datos']['personales']
        );
        $this->assertSame(
            $expediente->antecedentes_personales_observaciones,
            $antecedentesEvento->payload['datos']['personales_observaciones']
        );
    }

    public function test_alumno_actualiza_antecedentes_registra_timeline(): void
    {
        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Psicología',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Vespertino',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $initialFamily = Expediente::defaultFamilyHistory();
        $initialFamily['diabetes_mellitus']['madre'] = true;
        $initialFamily['hipertension_arterial']['padre'] = false;
        $initialFamily['cancer']['otros_maternos'] = false;

        $initialPersonal = Expediente::defaultPersonalPathologicalHistory();
        $initialPersonal['asma']['padece'] = true;
        $initialPersonal['asma']['fecha'] = Carbon::now()->subYears(3)->format('Y-m-d');
        $initialPersonal['cirugias_previas']['padece'] = false;

        $expediente = Expediente::factory()->create([
            'creado_por' => $alumno->id,
            'estado' => 'abierto',
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'antecedentes_familiares' => $initialFamily,
            'antecedentes_observaciones' => 'Observaciones iniciales.',
            'antecedentes_personales_patologicos' => $initialPersonal,
            'antecedentes_personales_observaciones' => 'Asma controlada con inhalador.',
        ]);

        $updatedFamily = [];
        foreach (Expediente::HEREDITARY_HISTORY_CONDITIONS as $conditionKey => $conditionLabel) {
            $updatedFamily[$conditionKey] = [];
            foreach (Expediente::FAMILY_HISTORY_MEMBERS as $memberKey => $memberLabel) {
                $updatedFamily[$conditionKey][$memberKey] = '0';
            }
        }

        $updatedFamily['diabetes_mellitus']['madre'] = '0';
        $updatedFamily['hipertension_arterial']['padre'] = '1';
        $updatedFamily['obesidad']['hermanos'] = '1';

        $updatedPersonal = [];
        foreach (Expediente::PERSONAL_PATHOLOGICAL_CONDITIONS as $conditionKey => $conditionLabel) {
            $updatedPersonal[$conditionKey] = [
                'padece' => '0',
                'fecha' => '',
            ];
        }

        $updatedPersonal['asma'] = [
            'padece' => '0',
            'fecha' => '',
        ];
        $updatedPersonal['cirugias_previas'] = [
            'padece' => '1',
            'fecha' => Carbon::now()->subMonths(6)->format('Y-m-d'),
        ];

        $payload = [
            'no_control' => $expediente->no_control,
            'paciente' => $expediente->paciente,
            'apertura' => $expediente->apertura->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'antecedentes_familiares' => $updatedFamily,
            'antecedentes_observaciones' => 'Actualización por parte del alumno.',
            'antecedentes_personales_patologicos' => $updatedPersonal,
            'antecedentes_personales_observaciones' => 'Cirugía de rodilla en 2024.',
        ];

        $response = $this->actingAs($alumno)->put(route('expedientes.update', $expediente), $payload);

        $response->assertSessionHasNoErrors();

        $expediente->refresh();

        $this->assertFalse($expediente->antecedentes_familiares['diabetes_mellitus']['madre']);
        $this->assertTrue($expediente->antecedentes_familiares['hipertension_arterial']['padre']);
        $this->assertTrue($expediente->antecedentes_familiares['obesidad']['hermanos']);
        $this->assertSame(
            $payload['antecedentes_observaciones'],
            $expediente->antecedentes_observaciones
        );
        $this->assertFalse($expediente->antecedentes_personales_patologicos['asma']['padece']);
        $this->assertTrue($expediente->antecedentes_personales_patologicos['cirugias_previas']['padece']);
        $this->assertSame(
            $updatedPersonal['cirugias_previas']['fecha'],
            $expediente->antecedentes_personales_patologicos['cirugias_previas']['fecha']
        );
        $this->assertSame(
            $payload['antecedentes_personales_observaciones'],
            $expediente->antecedentes_personales_observaciones
        );

        $actualizacionEvento = $expediente->timelineEventos()
            ->where('evento', 'expediente.actualizado')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($actualizacionEvento);
        $this->assertContains('antecedentes_familiares', $actualizacionEvento->payload['campos']);
        $this->assertContains('antecedentes_observaciones', $actualizacionEvento->payload['campos']);
        $this->assertContains('antecedentes_personales_patologicos', $actualizacionEvento->payload['campos']);
        $this->assertContains('antecedentes_personales_observaciones', $actualizacionEvento->payload['campos']);

        $antecedentesEvento = $expediente->timelineEventos()
            ->where('evento', 'expediente.antecedentes_actualizados')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($antecedentesEvento);
        $this->assertTrue($antecedentesEvento->payload['antes']['familiares']['diabetes_mellitus']['madre']);
        $this->assertFalse($antecedentesEvento->payload['despues']['familiares']['diabetes_mellitus']['madre']);
        $this->assertSame(
            'Observaciones iniciales.',
            $antecedentesEvento->payload['antes']['observaciones']
        );
        $this->assertSame(
            $payload['antecedentes_observaciones'],
            $antecedentesEvento->payload['despues']['observaciones']
        );
        $this->assertTrue($antecedentesEvento->payload['antes']['personales']['asma']['padece']);
        $this->assertFalse($antecedentesEvento->payload['despues']['personales']['asma']['padece']);
        $this->assertSame(
            'Asma controlada con inhalador.',
            $antecedentesEvento->payload['antes']['personales_observaciones']
        );
        $this->assertSame(
            $payload['antecedentes_personales_observaciones'],
            $antecedentesEvento->payload['despues']['personales_observaciones']
        );
    }

    public function test_validacion_falla_con_datos_invalidos(): void
    {
        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');

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

        $payload = [
            'no_control' => 'AL-2025-0002',
            'paciente' => 'Alumno Prueba',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'antecedentes_familiares' => [
                'diabetes_mellitus' => [
                    'madre' => 'tal vez',
                ],
            ],
            'antecedentes_observaciones' => str_repeat('a', 600),
            'antecedentes_personales_patologicos' => [
                'asma' => [
                    'padece' => 'quizá',
                    'fecha' => '2025-99-99',
                ],
            ],
            'antecedentes_personales_observaciones' => str_repeat('b', 600),
        ];

        $response = $this->actingAs($alumno)->post(route('expedientes.store'), $payload);

        $response->assertSessionHasErrors([
            'antecedentes_familiares.diabetes_mellitus.madre',
            'antecedentes_observaciones',
            'antecedentes_personales_patologicos.asma.padece',
            'antecedentes_personales_patologicos.asma.fecha',
            'antecedentes_personales_observaciones',
        ]);
    }
}
