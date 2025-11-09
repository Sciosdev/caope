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

    public function test_personal_pathological_condition_labels_use_expected_spellings(): void
    {
        $expectedConditions = [
            'parotiditis' => 'Parotiditis',
            'disfunciones_endocrinas' => 'Disfunciones endócrinas',
            'enfermedades_transmision_sexual' => 'Enf. Transmisión Sexual',
            'amigdalitis_repeticion' => 'Amigdalitis de repetición',
            'transfusiones_sanguineas' => 'Transfusiones sanguíneas',
        ];

        foreach ($expectedConditions as $key => $label) {
            $this->assertArrayHasKey($key, Expediente::PERSONAL_PATHOLOGICAL_CONDITIONS);
            $this->assertSame($label, Expediente::PERSONAL_PATHOLOGICAL_CONDITIONS[$key]);
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
        $personalPayload['intervenciones_quirurgicas'] = [
            'padece' => '1',
            'fecha' => Carbon::now()->subYear()->format('Y-m-d'),
        ];

        $systemsPayload = [
            'digestivo' => 'Sin antecedentes de retraso en el desarrollo temprano.',
            'respiratorio' => 'Estado mental orientado y colaborador.',
            'cardiovascular' => null,
            'musculo_esqueletico' => 'Sin dolor musculoesquelético referido.',
            'genito_urinario' => 'Control de esfínteres acorde a la edad.',
            'linfohematatico' => 'Sin antecedentes de infecciones frecuentes.',
            'endocrino' => 'Sin alteraciones hormonales reportadas.',
            'nervioso' => 'Refiere episodios de ansiedad bajo control.',
            'tegumentario' => 'No se observan lesiones cutáneas.',
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
            'antecedente_padecimiento_actual' => 'Acude por seguimiento psicológico posterior a cirugía de rodilla.',
            'aparatos_sistemas' => $systemsPayload,
            'plan_accion' => 'Plan de acompañamiento individual con sesiones quincenales.',
            'diagnostico' => 'Diagnóstico inicial registrado por el alumno.',
            'dsm_tr' => 'F40.01 Trastorno de pánico con agorafobia',
            'observaciones_relevantes' => 'Observaciones clínicas iniciales aportadas por el alumno.',
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
        $this->assertTrue($expediente->antecedentes_personales_patologicos['intervenciones_quirurgicas']['padece']);
        $this->assertSame(
            $personalPayload['intervenciones_quirurgicas']['fecha'],
            $expediente->antecedentes_personales_patologicos['intervenciones_quirurgicas']['fecha']
        );
        $this->assertSame(
            $payload['antecedentes_personales_observaciones'],
            $expediente->antecedentes_personales_observaciones
        );
        $this->assertSame(
            $payload['antecedente_padecimiento_actual'],
            $expediente->antecedente_padecimiento_actual
        );
        $this->assertSame(
            $payload['plan_accion'],
            $expediente->plan_accion
        );
        $this->assertSame(
            $systemsPayload,
            $expediente->aparatos_sistemas
        );
        $this->assertSame($payload['diagnostico'], $expediente->diagnostico);
        $this->assertSame($payload['dsm_tr'], $expediente->dsm_tr);
        $this->assertSame($payload['observaciones_relevantes'], $expediente->observaciones_relevantes);

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
        $this->assertSame(
            $expediente->antecedente_padecimiento_actual,
            $creacionEvento->payload['datos']['antecedente_padecimiento_actual']
        );
        $this->assertSame(
            $expediente->aparatos_sistemas,
            $creacionEvento->payload['datos']['aparatos_sistemas']
        );
        $this->assertSame(
            $expediente->plan_accion,
            $creacionEvento->payload['datos']['plan_accion']
        );
        $this->assertSame($expediente->diagnostico, $creacionEvento->payload['datos']['diagnostico']);
        $this->assertSame($expediente->dsm_tr, $creacionEvento->payload['datos']['dsm_tr']);
        $this->assertSame($expediente->observaciones_relevantes, $creacionEvento->payload['datos']['observaciones_relevantes']);

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
        $this->assertSame(
            $expediente->antecedente_padecimiento_actual,
            $antecedentesEvento->payload['datos']['padecimiento_actual']
        );
        $this->assertSame(
            $expediente->aparatos_sistemas,
            $antecedentesEvento->payload['datos']['aparatos_sistemas']
        );
        $this->assertSame(
            $expediente->plan_accion,
            $antecedentesEvento->payload['datos']['plan_accion']
        );
        $this->assertSame($expediente->diagnostico, $antecedentesEvento->payload['datos']['diagnostico']);
        $this->assertSame($expediente->dsm_tr, $antecedentesEvento->payload['datos']['dsm_tr']);
        $this->assertSame(
            $expediente->observaciones_relevantes,
            $antecedentesEvento->payload['datos']['observaciones_relevantes']
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
        $initialPersonal['intervenciones_quirurgicas']['padece'] = false;
        $initialSystems = Expediente::defaultSystemsReview();
        $initialSystems['digestivo'] = 'Antecedentes de crianza significativa con apoyo familiar.';
        $initialSystems['respiratorio'] = 'Estado mental inicial sin alteraciones.';
        $initialSystems['cardiovascular'] = null;
        $initialSystems['musculo_esqueletico'] = null;
        $initialSystems['genito_urinario'] = null;
        $initialSystems['linfohematatico'] = null;
        $initialSystems['endocrino'] = null;
        $initialSystems['nervioso'] = null;
        $initialSystems['tegumentario'] = null;

        $expediente = Expediente::factory()->create([
            'creado_por' => $alumno->id,
            'estado' => 'abierto',
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'antecedentes_familiares' => $initialFamily,
            'antecedentes_observaciones' => 'Observaciones iniciales.',
            'antecedentes_personales_patologicos' => $initialPersonal,
            'antecedentes_personales_observaciones' => 'Asma controlada con inhalador.',
            'antecedente_padecimiento_actual' => 'Consulta inicial por crisis de ansiedad.',
            'plan_accion' => 'Plan inicial con seguimiento mensual.',
            'aparatos_sistemas' => $initialSystems,
            'diagnostico' => 'Diagnóstico clínico inicial con énfasis en ansiedad.',
            'dsm_tr' => 'F41.1 Trastorno de ansiedad generalizada',
            'observaciones_relevantes' => 'Observaciones relevantes recopiladas en la primera sesión.',
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
        $updatedPersonal['intervenciones_quirurgicas'] = [
            'padece' => '1',
            'fecha' => Carbon::now()->subMonths(6)->format('Y-m-d'),
        ];

        $updatedSystems = [
            'digestivo' => 'Se reporta avance en habilidades sociales durante la infancia.',
            'respiratorio' => 'Estado mental actual con ligera ansiedad situacional.',
            'cardiovascular' => 'Observaciones clínicas sin cambios relevantes.',
            'musculo_esqueletico' => 'Sin limitaciones en la movilidad ni dolor articular.',
            'genito_urinario' => 'Función urinaria conservada, sin síntomas asociados.',
            'linfohematatico' => 'Sin antecedentes de adenopatías o alteraciones hematológicas.',
            'endocrino' => 'No refiere cambios en apetito o peso recientes.',
            'nervioso' => 'Refiere ocasionales cefaleas de baja intensidad.',
            'tegumentario' => 'Sin lesiones dérmicas observables al momento.',
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
            'antecedente_padecimiento_actual' => 'Seguimiento posterior a cirugía con buena evolución.',
            'aparatos_sistemas' => $updatedSystems,
            'plan_accion' => 'Plan actualizado con actividades semanales.',
            'diagnostico' => 'Diagnóstico actualizado con enfoque interdisciplinario.',
            'dsm_tr' => 'F32.1 Episodio depresivo moderado',
            'observaciones_relevantes' => 'Observaciones relevantes posteriores a la intervención.',
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
        $this->assertTrue($expediente->antecedentes_personales_patologicos['intervenciones_quirurgicas']['padece']);
        $this->assertSame(
            $updatedPersonal['intervenciones_quirurgicas']['fecha'],
            $expediente->antecedentes_personales_patologicos['intervenciones_quirurgicas']['fecha']
        );
        $this->assertSame(
            $payload['antecedentes_personales_observaciones'],
            $expediente->antecedentes_personales_observaciones
        );
        $this->assertSame(
            $payload['antecedente_padecimiento_actual'],
            $expediente->antecedente_padecimiento_actual
        );
        $this->assertSame(
            $payload['plan_accion'],
            $expediente->plan_accion
        );
        $this->assertSame(
            $updatedSystems,
            $expediente->aparatos_sistemas
        );
        $this->assertSame($payload['diagnostico'], $expediente->diagnostico);
        $this->assertSame($payload['dsm_tr'], $expediente->dsm_tr);
        $this->assertSame($payload['observaciones_relevantes'], $expediente->observaciones_relevantes);

        $actualizacionEvento = $expediente->timelineEventos()
            ->where('evento', 'expediente.actualizado')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($actualizacionEvento);
        $this->assertContains('antecedentes_familiares', $actualizacionEvento->payload['campos']);
        $this->assertContains('antecedentes_observaciones', $actualizacionEvento->payload['campos']);
        $this->assertContains('antecedentes_personales_patologicos', $actualizacionEvento->payload['campos']);
        $this->assertContains('antecedentes_personales_observaciones', $actualizacionEvento->payload['campos']);
        $this->assertContains('antecedente_padecimiento_actual', $actualizacionEvento->payload['campos']);
        $this->assertContains('aparatos_sistemas', $actualizacionEvento->payload['campos']);
        $this->assertContains('plan_accion', $actualizacionEvento->payload['campos']);
        $this->assertContains('diagnostico', $actualizacionEvento->payload['campos']);
        $this->assertContains('dsm_tr', $actualizacionEvento->payload['campos']);
        $this->assertContains('observaciones_relevantes', $actualizacionEvento->payload['campos']);

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
        $this->assertSame(
            'Consulta inicial por crisis de ansiedad.',
            $antecedentesEvento->payload['antes']['padecimiento_actual']
        );
        $this->assertSame(
            $payload['antecedente_padecimiento_actual'],
            $antecedentesEvento->payload['despues']['padecimiento_actual']
        );
        $this->assertSame(
            $initialSystems,
            $antecedentesEvento->payload['antes']['aparatos_sistemas']
        );
        $this->assertSame(
            $updatedSystems,
            $antecedentesEvento->payload['despues']['aparatos_sistemas']
        );
        $this->assertSame(
            'Plan inicial con seguimiento mensual.',
            $antecedentesEvento->payload['antes']['plan_accion']
        );
        $this->assertSame(
            $payload['plan_accion'],
            $antecedentesEvento->payload['despues']['plan_accion']
        );
        $this->assertSame(
            'Diagnóstico clínico inicial con énfasis en ansiedad.',
            $antecedentesEvento->payload['antes']['diagnostico']
        );
        $this->assertSame(
            $payload['diagnostico'],
            $antecedentesEvento->payload['despues']['diagnostico']
        );
        $this->assertSame(
            'F41.1 Trastorno de ansiedad generalizada',
            $antecedentesEvento->payload['antes']['dsm_tr']
        );
        $this->assertSame(
            $payload['dsm_tr'],
            $antecedentesEvento->payload['despues']['dsm_tr']
        );
        $this->assertSame(
            'Observaciones relevantes recopiladas en la primera sesión.',
            $antecedentesEvento->payload['antes']['observaciones_relevantes']
        );
        $this->assertSame(
            $payload['observaciones_relevantes'],
            $antecedentesEvento->payload['despues']['observaciones_relevantes']
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
            'antecedente_padecimiento_actual' => str_repeat('c', 1200),
            'aparatos_sistemas' => [
                'digestivo' => str_repeat('d', 1200),
                'respiratorio' => str_repeat('e', 1005),
                'cardiovascular' => null,
                'musculo_esqueletico' => 'Movilidad conservada.',
                'genito_urinario' => 'Sin alteraciones referidas.',
                'linfohematatico' => 'No se detectan adenopatías.',
                'endocrino' => 'Hormonas bajo control médico.',
                'nervioso' => 'Sueño regular.',
                'tegumentario' => 'Piel íntegra.',
            ],
            'plan_accion' => str_repeat('p', 1200),
            'diagnostico' => str_repeat('d', 1100),
            'dsm_tr' => str_repeat('f', 260),
            'observaciones_relevantes' => str_repeat('o', 1100),
        ];

        $response = $this->actingAs($alumno)->post(route('expedientes.store'), $payload);

        $response->assertSessionHasErrors([
            'antecedentes_familiares.diabetes_mellitus.madre',
            'antecedentes_observaciones',
            'antecedentes_personales_patologicos.asma.padece',
            'antecedentes_personales_patologicos.asma.fecha',
            'antecedentes_personales_observaciones',
            'antecedente_padecimiento_actual',
            'aparatos_sistemas.digestivo',
            'aparatos_sistemas.respiratorio',
            'plan_accion',
            'diagnostico',
            'dsm_tr',
            'observaciones_relevantes',
        ]);
    }
}
