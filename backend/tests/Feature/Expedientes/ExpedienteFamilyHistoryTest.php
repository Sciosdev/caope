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

        $clinicalPayload = [];
        foreach (Expediente::CLINICAL_HISTORY_CONDITIONS as $conditionKey => $conditionLabel) {
            $clinicalPayload[$conditionKey] = [];
            foreach (Expediente::FAMILY_HISTORY_MEMBERS as $memberKey => $memberLabel) {
                $clinicalPayload[$conditionKey][$memberKey] = '0';
            }
        }

        $clinicalPayload['diabetes']['madre'] = '1';
        $clinicalPayload['hipertension_arterial']['padre'] = '1';
        $clinicalPayload['cancer']['tios'] = '1';

        $payload = [
            'no_control' => 'AL-2025-0001',
            'paciente' => 'Alumno Demo',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'antecedentes_familiares' => [
                'madre' => '1',
                'padre' => '0',
                'hermanos' => '1',
                'abuelos' => '0',
                'tios' => '0',
                'otros' => '0',
            ],
            'antecedentes_observaciones' => 'Antecedentes por madre y hermanos.',
            'antecedentes_clinicos' => $clinicalPayload,
            'antecedentes_clinicos_otros' => 'Asma en abuelo.',
            'antecedentes_clinicos_observaciones' => 'Seguimiento semestral recomendado.',
        ];

        $response = $this->actingAs($alumno)->post(route('expedientes.store'), $payload);

        $response->assertSessionHasNoErrors();

        $expediente = Expediente::where('no_control', $payload['no_control'])->first();
        $this->assertNotNull($expediente);

        $this->assertTrue($expediente->antecedentes_familiares['madre']);
        $this->assertFalse($expediente->antecedentes_familiares['padre']);
        $this->assertTrue($expediente->antecedentes_familiares['hermanos']);
        $this->assertSame(
            $payload['antecedentes_observaciones'],
            $expediente->antecedentes_observaciones
        );
        $this->assertTrue($expediente->antecedentes_clinicos['diabetes']['madre']);
        $this->assertTrue($expediente->antecedentes_clinicos['hipertension_arterial']['padre']);
        $this->assertFalse($expediente->antecedentes_clinicos['obesidad']['madre']);
        $this->assertSame('Asma en abuelo.', $expediente->antecedentes_clinicos_otros);
        $this->assertSame('Seguimiento semestral recomendado.', $expediente->antecedentes_clinicos_observaciones);

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
            $expediente->antecedentes_clinicos,
            $creacionEvento->payload['datos']['antecedentes_clinicos']
        );
        $this->assertSame(
            $expediente->antecedentes_clinicos_otros,
            $creacionEvento->payload['datos']['antecedentes_clinicos_otros']
        );
        $this->assertSame(
            $expediente->antecedentes_clinicos_observaciones,
            $creacionEvento->payload['datos']['antecedentes_clinicos_observaciones']
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
            $expediente->antecedentes_clinicos,
            $antecedentesEvento->payload['datos']['clinicos']['padecimientos']
        );
        $this->assertSame(
            $expediente->antecedentes_clinicos_otros,
            $antecedentesEvento->payload['datos']['clinicos']['otros']
        );
        $this->assertSame(
            $expediente->antecedentes_clinicos_observaciones,
            $antecedentesEvento->payload['datos']['clinicos']['observaciones']
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

        $initialClinical = Expediente::defaultClinicalHistory();
        $initialClinical['diabetes']['madre'] = true;
        $initialClinical['hipertension_arterial']['padre'] = false;
        $initialClinical['cancer']['tios'] = false;

        $expediente = Expediente::factory()->create([
            'creado_por' => $alumno->id,
            'estado' => 'abierto',
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'antecedentes_familiares' => [
                'madre' => true,
                'padre' => false,
                'hermanos' => false,
                'abuelos' => false,
                'tios' => false,
                'otros' => false,
            ],
            'antecedentes_observaciones' => 'Observaciones iniciales.',
            'antecedentes_clinicos' => $initialClinical,
            'antecedentes_clinicos_otros' => 'Control anual.',
            'antecedentes_clinicos_observaciones' => 'Sin incidencias recientes.',
        ]);

        $updatedClinical = [];
        foreach (Expediente::CLINICAL_HISTORY_CONDITIONS as $conditionKey => $conditionLabel) {
            $updatedClinical[$conditionKey] = [];
            foreach (Expediente::FAMILY_HISTORY_MEMBERS as $memberKey => $memberLabel) {
                $updatedClinical[$conditionKey][$memberKey] = '0';
            }
        }

        $updatedClinical['diabetes']['madre'] = '0';
        $updatedClinical['hipertension_arterial']['padre'] = '1';
        $updatedClinical['obesidad']['hermanos'] = '1';

        $payload = [
            'no_control' => $expediente->no_control,
            'paciente' => $expediente->paciente,
            'apertura' => $expediente->apertura->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'antecedentes_familiares' => [
                'madre' => '0',
                'padre' => '1',
                'hermanos' => '1',
                'abuelos' => '0',
                'tios' => '0',
                'otros' => '0',
            ],
            'antecedentes_observaciones' => 'Actualización por parte del alumno.',
            'antecedentes_clinicos' => $updatedClinical,
            'antecedentes_clinicos_otros' => 'Tratamiento en curso.',
            'antecedentes_clinicos_observaciones' => 'Cambios recientes detectados.',
        ];

        $response = $this->actingAs($alumno)->put(route('expedientes.update', $expediente), $payload);

        $response->assertSessionHasNoErrors();

        $expediente->refresh();

        $this->assertFalse($expediente->antecedentes_familiares['madre']);
        $this->assertTrue($expediente->antecedentes_familiares['padre']);
        $this->assertTrue($expediente->antecedentes_familiares['hermanos']);
        $this->assertSame(
            $payload['antecedentes_observaciones'],
            $expediente->antecedentes_observaciones
        );
        $this->assertTrue($expediente->antecedentes_clinicos['hipertension_arterial']['padre']);
        $this->assertTrue($expediente->antecedentes_clinicos['obesidad']['hermanos']);
        $this->assertFalse($expediente->antecedentes_clinicos['diabetes']['madre']);
        $this->assertSame('Tratamiento en curso.', $expediente->antecedentes_clinicos_otros);
        $this->assertSame('Cambios recientes detectados.', $expediente->antecedentes_clinicos_observaciones);

        $actualizacionEvento = $expediente->timelineEventos()
            ->where('evento', 'expediente.actualizado')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($actualizacionEvento);
        $this->assertContains('antecedentes_familiares', $actualizacionEvento->payload['campos']);
        $this->assertContains('antecedentes_observaciones', $actualizacionEvento->payload['campos']);
        $this->assertContains('antecedentes_clinicos', $actualizacionEvento->payload['campos']);
        $this->assertContains('antecedentes_clinicos_otros', $actualizacionEvento->payload['campos']);
        $this->assertContains('antecedentes_clinicos_observaciones', $actualizacionEvento->payload['campos']);

        $antecedentesEvento = $expediente->timelineEventos()
            ->where('evento', 'expediente.antecedentes_actualizados')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($antecedentesEvento);
        $this->assertTrue($antecedentesEvento->payload['antes']['familiares']['madre']);
        $this->assertFalse($antecedentesEvento->payload['despues']['familiares']['madre']);
        $this->assertSame(
            'Observaciones iniciales.',
            $antecedentesEvento->payload['antes']['observaciones']
        );
        $this->assertSame(
            $payload['antecedentes_observaciones'],
            $antecedentesEvento->payload['despues']['observaciones']
        );
        $this->assertTrue($antecedentesEvento->payload['antes']['clinicos']['padecimientos']['diabetes']['madre']);
        $this->assertFalse($antecedentesEvento->payload['despues']['clinicos']['padecimientos']['diabetes']['madre']);
        $this->assertSame(
            'Control anual.',
            $antecedentesEvento->payload['antes']['clinicos']['otros']
        );
        $this->assertSame(
            'Tratamiento en curso.',
            $antecedentesEvento->payload['despues']['clinicos']['otros']
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
                'madre' => 'tal vez',
            ],
            'antecedentes_observaciones' => str_repeat('a', 600),
            'antecedentes_clinicos' => [
                'diabetes' => [
                    'madre' => 'quizá',
                ],
            ],
            'antecedentes_clinicos_observaciones' => str_repeat('b', 600),
        ];

        $response = $this->actingAs($alumno)->post(route('expedientes.store'), $payload);

        $response->assertSessionHasErrors([
            'antecedentes_familiares.madre',
            'antecedentes_observaciones',
            'antecedentes_clinicos.diabetes.madre',
            'antecedentes_clinicos_observaciones',
        ]);
    }
}
